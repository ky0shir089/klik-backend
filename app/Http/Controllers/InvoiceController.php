<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\Pph;
use App\Services\FileUploadService;
use App\Services\FonnteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("invoice:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Invoice::query()
            ->with([
                "supplier:id,name",
                "supplier_account:id,account_number,bank_id",
                "supplier_account.bank:id,name",
                "type_trx",
            ])
            ->when(auth()->user()->role->id == 3, function ($query) {
                $query->where("created_by", auth()->id());
            })
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "invoice_no",
                    "description",
                    "total_amount",
                ], "ilike", "%$search%");
            })
            ->when($request->type_trx_id, function ($query, $type_trx_id) {
                $query->where("trx_id", $type_trx_id);
            })
            ->when($request->method, function ($query, $method) {
                $query->where("payment_method", $method);
            })
            ->latest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function inbox(Request $request)
    {
        if (!auth()->user()->tokenCan("invoice:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Invoice::query()
            ->with([
                "supplier:id,name",
                "supplier_account:id,account_number,bank_id",
                "supplier_account.bank:id,name",
                "type_trx",
            ])
            ->where("status", "REQUEST")
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "invoice_no",
                    "description",
                    "amount",
                ], "ilike", "%$search%");
            })
            ->latest()
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceRequest $request)
    {
        if (!auth()->user()->tokenCan("invoice:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $year = date('y');
            $invoice_no = 'KLIK/' . date("m") . '/' . $year . '/' . Str::padLeft(Invoice::count() + 1, 3, '0');
            $authId = auth()->id();

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $sql = Invoice::create($request->safe()->except(["attachment"]) + [
                'invoice_no' => $invoice_no,
                'file_upload_id' => $file->id ?? null,
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            $details = [];

            foreach ($request->details as $detail) {
                $pphAmount = 0;
                $ppnAmount = 0;
                if (isset($detail['pph_id'])) {
                    $pph = Pph::find($detail['pph_id']);
                    $pphAmount = round($detail['item_amount'] * $pph->rate / 100);
                }
                if (isset($detail['ppn_rate'])) {
                    $ppnAmount = round($detail['item_amount'] * $detail['ppn_rate'] / 100);
                }
                $totalAmount = round($detail['item_amount'] - $pphAmount + $ppnAmount);


                $details[] = [
                    'invoice_id' => $sql->id,
                    'inv_coa_id' => $detail['inv_coa_id'],
                    'description' => $detail['description'],
                    'item_amount' => $detail['item_amount'],
                    'pph_id' => isset($detail['pph_id']) ? $detail['pph_id'] : null,
                    'pph_amount' => $pphAmount,
                    'ppn_rate' => $detail['ppn_rate'],
                    'ppn_amount' => $ppnAmount,
                    'rv_id' => isset($detail['rv_id']) ? $detail['rv_id'] : null,
                    'total_amount' => $totalAmount,
                    'created_by' => $authId,
                    'created_at' => now(),
                    'updated_at' => null,
                ];
            }

            $sql->total_amount = collect($details)->sum("total_amount");
            $sql->save();

            InvoiceDetail::insert($details);

            (new FonnteService($sql));

            DB::commit();
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], 500);
        }

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($invoice->load([
            "type_trx",
            "supplier_account",
            "supplier_account.supplier",
            "supplier_account.bank",
            "attachment",
            "details",
            "details.coa",
            "details.pph",
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $authId = auth()->id();

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $invoice->update($request->safe()->except(["attachment"]) + [
                'file_upload_id' => $file->id ?? $invoice->file_upload_id,
                'status' => $request->status,
                "signature" => $request->signature,
                'updated_by' => $authId,
            ]);

            if ($request->status == "REQUEST") {
                $invoice->details()->delete();

                $details = [];
                $sumTotalAmount = 0;

                foreach ($request->details as $detail) {
                    $pphAmount = 0;
                    $ppnAmount = 0;
                    if (isset($detail['pph_id'])) {
                        $pph = Pph::find($detail['pph_id']);
                        $pphAmount = round($detail['item_amount'] * $pph->rate / 100);
                    }
                    if (isset($detail['ppn_rate'])) {
                        $ppnAmount = round($detail['item_amount'] * $detail['ppn_rate'] / 100);
                    }
                    $totalAmount = round($detail['item_amount'] - $pphAmount + $ppnAmount);
                    $sumTotalAmount += $totalAmount;

                    $details[] = [
                        'invoice_id' => $invoice->id,
                        'inv_coa_id' => $detail['inv_coa_id'],
                        'description' => $detail['description'],
                        'item_amount' => $detail['item_amount'],
                        'pph_id' => isset($detail['pph_id']) ? $detail['pph_id'] : null,
                        'pph_amount' => $pphAmount,
                        'ppn_rate' => $detail['ppn_rate'],
                        'ppn_amount' => $ppnAmount,
                        'rv_id' => isset($detail['rv_id']) ? $detail['rv_id'] : null,
                        'total_amount' => $totalAmount,
                        'created_by' => $authId,
                        'created_at' => now(),
                        'updated_at' => null,
                    ];
                }

                $invoice->details()->insert($details);
                $invoice->total_amount = $sumTotalAmount;
                $invoice->save();
            }

            if ($request->status == "APPROVE") {
                $invoice->pv()->create([
                    "supplier_id" => $invoice->supplier_account->supplier->id,
                    "supplier_account_id" => $invoice->payment_method == "BANK" ? $invoice->supplier_account_id : null,
                    "pv_amount" => $invoice->total_amount,
                    "status" => "NEW",
                    "trx_dtl_id" => $invoice->trx_id,
                    "created_by" => $authId,
                ]);
            }

            if ($request->status == "CANCEL") {
                $invoice->pv()->delete();
            }

            DB::commit();

            return new UpdateResource($invoice);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollback();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $invoice->delete();

        return new DeleteResource($invoice);
    }
}
