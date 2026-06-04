<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\RV;
use App\Models\Spp;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\LaravelPdf\Facades\Pdf;

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
    // public function store(PaymentRequest $request)
    // {
    //     if (!auth()->user()->tokenCan("repayment:add")) {
    //         return response()->json([
    //             "success" => false,
    //             "message" => "Unauthorized",
    //         ], 403);
    //     }

    //     DB::beginTransaction();

    //     try {
    //         $authId = auth()->id();

    //         $rvs = [];
    //         foreach ($request->rvs as $rv) {
    //             $rvData = RV::find($rv);
    //             if ($rv) {
    //                 $rvs[] = [
    //                     "rv_id" => $rv,
    //                     "rv_amount" => $rvData->ending_balance,
    //                     "created_by" => $authId,
    //                 ];
    //             }
    //         }
    //         $total_rv = collect($rvs)->sum("rv_amount");

    //         $units = [];
    //         $totalAmount = 0;
    //         foreach ($request->units as $unit) {
    //             $unitData = Unit::find($unit);
    //             $totalAmount += $unitData->price;
    //             $units[] = [
    //                 "unit_id" => $unit,
    //                 "created_by" => $authId,
    //             ];
    //         }

    //         if ($total_rv < $totalAmount) {
    //             return response()->json([
    //                 "success" => false,
    //                 "message" => "RV amount is less than unit amount",
    //             ]);
    //         }

    //         $sql = Payment::create($request->safe()->except(["units", "rvs"]) + [
    //             'total_unit' => count($units),
    //             'total_amount' => $totalAmount,
    //             // 'status' => "REQUEST",
    //             'status' => "NEW",
    //             'created_by' => $authId,
    //             'updated_at' => null,
    //         ]);

    //         $sql->units()->createMany($units);
    //         $sql->rvs()->createMany($rvs);

    //         Unit::whereIn("id", $request->units)->update([
    //             'payment_status' => 'REQUEST',
    //         ]);
    //         RV::whereIn("id", $request->rvs)->update([
    //             'status' => 'USED',
    //         ]);

    //         // $sql->pv()->create([
    //         //     "supplier_id" => 1,
    //         //     "supplier_account_id" => 1,
    //         //     "pv_amount" => $totalAmount,
    //         //     "rv_amount" => $total_rv,
    //         //     "status" => "NEW",
    //         //     "trx_dtl_id" => 2,
    //         //     "created_by" => auth()->id(),
    //         // ]);

    //         DB::commit();

    //         return new StoreResource($sql);
    //     } catch (\Throwable $th) {
    //         info($th->getMessage());

    //         DB::rollback();

    //         return response()->json([
    //             "success" => false,
    //             "message" => $th->getMessage(),
    //         ], 400);
    //     }
    // }

    public function store(Request $request)
    {
        if (!auth()->user()->tokenCan("memo-payment:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $currentYear = date('y');
        $findLastPaymentDate = Payment::select("payment_date")->latest()->first();
        $lastPaymentDate = $findLastPaymentDate->date ?? now();
        $lastPaymentYear = Carbon::parse($lastPaymentDate)->format('y');
        if ($currentYear > $lastPaymentYear) {
            $countPayment = 1;
        } else {
            $countPayment = Payment::query()
                ->where("payment_date", ">=", date('Y') . "-01-01")
                ->where("payment_date", "<=", date('Y') . "-12-31")
                ->count() + 1;
        }
        $sppNo = 'KLIK/OPR/MP/' . date("m") . '/' . $currentYear . '/' . $countPayment;

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

            $sql->pv()->create([
                "supplier_id" => 1,
                "supplier_account_id" => 1,
                "pv_amount" => $totalAmount,
                "status" => "NEW",
                "trx_dtl_id" => 2,
                "created_by" => auth()->id(),
            ]);

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
