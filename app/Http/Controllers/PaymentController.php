<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Spp;
use App\Services\WorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("memo-payment:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Payment::query()
            ->when($request->search, function ($query, $search) {
                $query->where("total_unit", "ilike", "%{$search}%")
                    ->orWhere("total_amount", "ilike", "%{$search}%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->tokenCan("memo-payment:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $month = now()->format('m');
        $year = now()->format('y');
        $prefix = 'KLIK/OPR/MP/' .  $month  . '/' . $year . '/';

        $lastSpp = Payment::select("spp_no")
            ->where('spp_no', 'ilike', "$prefix%")
            ->latest('id')
            ->orderBy('spp_no', 'desc')
            ->first();

        $countSpp = $lastSpp ? (int) Str::after($lastSpp->spp_no, $prefix) + 1 : '01';
        $sppNo = $prefix . $countSpp;

        DB::beginTransaction();

        try {
            $authId = auth()->id();

            $spps = Spp::whereIn("id", $request->spps)->get();
            $totalunit = $spps->sum("total_unit");
            $totalAmount = $spps->sum("total_amount");

            $sql = Payment::create([
                'payment_date' => now(),
                'spp_no' => $sppNo,
                'total_unit' => $totalunit,
                'total_amount' => $totalAmount,
                'status' => "NEW",
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            foreach ($spps as $spp) {
                $customers[] = [
                    "spp_id" => $spp->id,
                    "created_by" => $authId
                ];

                Spp::find($spp->id)->update([
                    'payment_id' => $sql->id,
                    'status' => 'REQUEST',
                ]);
            }

            $sql->spps()->createMany($customers);

            // $sql->pv()->create([
            //     "supplier_id" => 1,
            //     "supplier_account_id" => 1,
            //     "pv_amount" => $totalAmount,
            //     "status" => "NEW",
            //     "trx_dtl_id" => 2,
            //     "created_by" => auth()->id(),
            // ]);

            $invoice = Invoice::create([
                'date' => now(),
                'invoice_no' => $sppNo,
                'trx_id' => 2,
                'supplier_id' => 1,
                'payment_method' => 'BANK',
                'supplier_account_id' => 1,
                'description' => 'PELUNASAN FIF',
                'total_amount' => $totalAmount,
                'status' => 'REQUEST',
                'created_by' => $authId,
                'updated_at' => null,
            ]);
            $invoice->details()->create([
                'invoice_id' => $invoice->id,
                'inv_coa_id' => 157,
                'description' => "PELUNASAN FIF",
                'item_amount' => $totalAmount,
                'pph_id' => null,
                'pph_amount' => 0,
                'ppn_rate' => 0,
                'ppn_amount' => 0,
                'rv_id' => null,
                'total_amount' => $totalAmount,
                'created_by' => $authId,
                'created_at' => now(),
                'updated_at' => null,
            ]);
            
            (new WorkflowService($invoice));

            DB::commit();

            return new StoreResource($sql);
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
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        if (!auth()->user()->tokenCan("memo-payment:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($payment->load([
            "spps",
            "spps.spp",
            "spps.spp.customer",
            "spps.spp.details",
            "spps.spp.details.unit",
            "spps.spp.details.unit.auction",
        ]));
    }

    public function showInbox(Request $request)
    {
        if (!auth()->user()->tokenCan("memo-payment:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Payment::query()
            ->with([
                "spps",
                "spps.spp",
                "spps.spp.customer",
                "spps.spp.details",
                "spps.spp.details.unit",
                "spps.spp.details.unit.auction",
            ])
            ->where("spp_no", $request->invoice_no)
            ->first();

        return new GetResource($query);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentRequest $request, Payment $payment)
    {
        if (!auth()->user()->tokenCan("memo-payment:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $payment->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($payment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {
        if (!auth()->user()->tokenCan("memo-payment:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $payment->delete();

        return new DeleteResource($payment);
    }

    public function pdf(Payment $payment)
    {
        $customer = $payment->customer;
        $html = "<h1>{$customer->name}</h1>";
        return Pdf::html($html)->save(storage_path("app/public/memo/invoce.pdf"));
    }
}
