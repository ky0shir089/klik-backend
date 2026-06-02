<?php

namespace App\Http\Controllers;

use App\Http\Requests\LpjRequest;
use App\Http\Resources\GetResource;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\PaymentVoucher;
use App\Models\Settlement;
use App\Services\FileUploadService;
use App\Services\WorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SettlementController extends Controller
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

        $query = Settlement::query()
            ->with([
                "pv:id,pv_no,description,supplier_id,supplier_account_id,paid_date,processable_id",
                "pv.supplier:id,name",
                "pv.supplier_account:id,account_number,bank_id",
                "pv.supplier_account.bank:id,name",
                "invoice:id,invoice_no,status",
                "byhmd:id,invoice_no,status",
                "byhmd.pv:processable_id,pv_no",
            ])
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "prepayment_amount",
                    "lpj_amount",
                    "byhmd_amount",
                    "balance",
                    "status",
                ], "ilike", "%$search%")
                    ->orWhereHas("pv", function ($query) use ($search) {
                        $query->whereAny([
                            "pv_no",
                            "description",
                            "paid_date",
                        ], "ilike", "%$search%");
                    })
                    ->orWhereHas("invoice", function ($query) use ($search) {
                        $query->where("invoice_no", "ilike", "%$search%")
                            ->orWhereRelation("supplier", "name", "ilike", "%$search%");
                    })
                    ->orWhereRelation("byhmd", "invoice_no", "ilike", "%$search%");
            })
            ->latest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(LpjRequest $request)
    {
        if (!auth()->user()->tokenCan("invoice:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request) {
            $authId = auth()->id();

            $currentYear = date('y');
            $findLastInvDate = Invoice::select("created_at")->latest()->first();
            $lastInvDate = $findLastInvDate->date ?? now();
            $lastInvYear = Carbon::parse($lastInvDate)->format('y');
            if ($currentYear > $lastInvYear) {
                $countInv = 1;
            } else {
                $countInv = Invoice::query()
                    ->whereNotNull("invoice_no")
                    ->where("created_at", ">=", date('Y') . "-01-01")
                    ->where("created_at", "<=", date('Y') . "-12-31")
                    ->count() + 1;
            }
            $invoice_no = 'KLIK/' . date("m") . '/' . $currentYear . '/' . Str::padLeft($countInv++, 3, '0');

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $pv = PaymentVoucher::find($request->pv_id);

            $sql = Invoice::create($request->safe()->except(["pv_id", "attachment", "details"]) + [
                'invoice_no' => $invoice_no,
                'supplier_id' => $pv->supplier_id,
                'supplier_account_id' => $pv->supplier_account_id,
                'file_upload_id' => $file->id ?? null,
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            $details = [];

            foreach (json_decode($request->details, true) as $detail) {
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

            $settlement = Settlement::where('prepayment_pv_id', $request->pv_id)->first();
            if ($settlement->lpj_invoice_id != "") {
                Settlement::insert([
                    'prepayment_pv_id' => $request->pv_id,
                    'lpj_invoice_id' => $sql->id,
                    'prepayment_amount' => $settlement->balance,
                    'lpj_amount' => $sql->total_amount,
                    'balance' => $settlement->balance,
                    'status' => "USED",
                    'created_by' => $authId,
                    'created_at' => now(),
                    'updated_at' => null,
                ]);
                $settlement->status = "USED";
                $settlement->save();
            } else {
                $settlement->lpj_invoice_id = $sql->id;
                $settlement->lpj_amount = $sql->total_amount;
                $settlement->status = "USED";
                $settlement->save();
            }

            (new WorkflowService($sql));
        });

        return response()->json([
            "success" => true,
            "message" => "LPJ created successfully",
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Settlement $settlement)
    {
        if (!auth()->user()->tokenCan("invoice:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($settlement->load([
            "invoice",
            "invoice.type_trx",
            "invoice.supplier_account",
            "invoice.supplier_account.supplier",
            "invoice.supplier_account.bank",
            "invoice.attachment",
            "invoice.details",
            "invoice.details.coa",
            "invoice.details.pph",
            "invoice.wf_histories",
            "invoice.wf_histories.user:id,name"
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(LpjRequest $request, Settlement $settlement)
    {
        if (!auth()->user()->tokenCan("invoice:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request, $settlement) {
            $authId = auth()->id();
            $invoice = $settlement->invoice;
            info($settlement);
            info($settlement->invoice);

            if ($request->hasFile('attachment')) {
                $file = (new FileUploadService)->handleUpload($request->file('attachment'));
            }

            $invoice->update($request->safe()->except(["attachment"]) + [
                'file_upload_id' => $file->id ?? $invoice->file_upload_id,
                'updated_by' => $authId,
            ]);

            $invoice->details()->delete();

            $details = [];
            $sumTotalAmount = 0;

            foreach (json_decode($request->details, true) as $detail) {
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

            info($details);
            $invoice->details()->insert($details);
            $invoice->total_amount = $sumTotalAmount;
            $invoice->save();

            $invoice->wf_histories()->delete();
            $invoice->wf_approval()->delete();

            (new WorkflowService($invoice));

            $settlement = Settlement::where('prepayment_pv_id', $request->pv_id)->first();
            $settlement->lpj_amount = $invoice->total_amount;
            $settlement->save();
        });

        return response()->json([
            "success" => true,
            "message" => "LPJ updated successfully",
        ], 400);

        return "asdasdasd";
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Settlement $settlement)
    {
        //
    }
}
