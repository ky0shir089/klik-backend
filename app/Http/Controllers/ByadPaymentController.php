<?php

namespace App\Http\Controllers;

use App\Http\Requests\ByadPaymentRequest;
use App\Http\Resources\GetResource;
use App\Models\ByadHeader;
use App\Models\ByadPayment;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ByadPaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("byad:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = ByadPayment::query()
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "date",
                    "total_unit",
                    "total_amount",
                    "status",
                ], "ilike", "%$search%");
            })
            ->latest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ByadPaymentRequest $request)
    {
        if (!auth()->user()->tokenCan("byad:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = [];

        DB::transaction(function () use ($request, &$sql) {
            $authId = auth()->id();

            $byad = ByadHeader::whereIn("id", $request->details);
            $byad->update([
                'status' => 'REQUEST',
            ]);
            $byadData = $byad->get();

            $totalUnit = $byadData->sum("total_unit");
            $totalAmount = $byadData->sum("total_amount");
            $byadAmount = $byadData->sum("byad_amount");

            $year = date('y');
            $invoice_no = 'KLIK/' . date("m") . '/' . $year . '/' . Str::padLeft(Invoice::count() + 1, 3, '0');

            $invoice = Invoice::create([
                'date' => $request->date,
                'invoice_no' => $invoice_no,
                'trx_id' => 3,
                'supplier_id' => 3,
                'payment_method' => 'BANK',
                'supplier_account_id' => 3,
                'description' => 'PEMBAYARAN BYAD',
                'total_amount' => $byadAmount,
                'status' => 'REQUEST',
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            foreach ($byadData as $detail) {
                $rows[] = [
                    'byad_id' => $detail->id,
                    'created_by' =>  $authId,
                    'updated_at' => null,
                ];

                $invoiceDetails[] = [
                    'invoice_id' => $invoice->id,
                    'inv_coa_id' => 109,
                    'description' => $detail['description'],
                    'item_amount' => $detail['byad_amount'],
                    'pph_id' => null,
                    'pph_amount' => 0,
                    'ppn_rate' => 0,
                    'ppn_amount' => 0,
                    'rv_id' => null,
                    'total_amount' => $detail['byad_amount'],
                    'created_by' => $authId,
                    'created_at' => now(),
                    'updated_at' => null,
                ];
            }

            $sql = ByadPayment::create($request->safe()->except(["details"]) + [
                'invoice_id' => $invoice->id,
                'total_unit' => $totalUnit,
                'total_amount' => $totalAmount,
                'byad_amount' => $byadAmount,
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            $sql->details()->createMany($rows);
            InvoiceDetail::insert($invoiceDetails);

            (new WorkflowService($invoice));
        });

        info($sql);

        return response()->json([
            "success" => true,
            "data" => $sql,
            "message" => "Byad Payment created successfully"
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(ByadPayment $byadPayment)
    {
        if (!auth()->user()->tokenCan("byad:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($byadPayment->load([
            "details",
            "details.byad",
            "details.byad.details",
            "details.byad.details.unit",
            "details.byad.details.unit.auction",
            "details.byad.details.unit.auction.customer",
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
