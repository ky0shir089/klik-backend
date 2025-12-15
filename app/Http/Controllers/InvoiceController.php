<?php

namespace App\Http\Controllers;

use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Invoice;
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
                "supplier_account",
                "supplier_account.supplier",
                "supplier_account.bank",
            ])
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "invoice_no",
                    "description",
                    "amount",
                ], "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
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

        $year = date('y');
        $invoice_no = 'INV' . $year . Str::padLeft(Invoice::count() + 1, 5, '0');

        $sql = Invoice::create($request->validated() + [
            'invoice_no' => $invoice_no,
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

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
            "trx_dtl",
            "trx_dtl.trx",
            "supplier_account",
            "supplier_account.supplier",
            "supplier_account.bank",
            "coa",
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Invoice $invoice)
    {
        if (!auth()->user()->tokenCan("invoice:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $invoice->update([
                'status' => $request->status,
                'updated_by' => auth()->id(),
            ]);

            if ($request->status == "APPROVE") {
                $invoice->pv()->create([
                    "supplier_id" => $invoice->supplier_account->supplier->id,
                    "supplier_account_id" => $invoice->supplier_account_id,
                    "pv_amount" => $invoice->amount,
                    "status" => "NEW",
                    "trx_dtl_id" => $invoice->trx_dtl->trx_id,
                    "created_by" => auth()->id(),
                ]);
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
