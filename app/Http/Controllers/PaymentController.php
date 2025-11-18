<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Payment;
use App\Models\PaymentVoucher;
use App\Models\RV;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("repayment:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Payment::query()
            ->with(["units", "rvs", "customer"])
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaymentRequest $request)
    {
        if (!auth()->user()->tokenCan("repayment:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $rvs = [];
            foreach ($request->rvs as $rv) {
                $rvData = RV::find($rv);
                if ($rv) {
                    $rvs[] = [
                        "rv_id" => $rv,
                        "rv_amount" => $rvData->ending_balance,
                        "created_by" => auth()->id(),
                    ];
                }
            }
            $total_rv = collect($rvs)->sum("rv_amount");

            $units = [];
            $totalAmount = 0;
            foreach ($request->units as $unit) {
                $unitData = Unit::find($unit);
                $totalAmount += $unitData->amount;
                $units[] = [
                    "unit_id" => $unit,
                    "created_by" => auth()->id(),
                ];
            }

            if ($total_rv < $totalAmount) {
                return response()->json([
                    "success" => false,
                    "message" => "RV amount is less than unit amount",
                ]);
            }

            $sql = Payment::create($request->safe()->except(["units", "rvs"]) + [
                'total_unit' => count($units),
                'total_amount' => $totalAmount,
                'status' => "REQUEST",
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]);

            $sql->units()->createMany($units);
            $sql->rvs()->createMany($rvs);

            Unit::whereIn("id", $request->units)->update([
                'payment_status' => 'REQUEST',
            ]);
            RV::whereIn("id", $request->rvs)->update([
                'status' => 'USED',
            ]);

            $sql->pv()->create([
                "supplier_id" => 1,
                "supplier_account_id" => 1,
                "pv_amount" => $totalAmount,
                "rv_amount" => $total_rv,
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
        if (!auth()->user()->tokenCan("repayment:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($payment->load([
            "units",
            "units.unit",
            "units.unit.auction",
            "rvs",
            "rvs.rv:id,rv_no,date,description",
            "customer"
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PaymentRequest $request, Payment $payment)
    {
        if (!auth()->user()->tokenCan("repayment:edit")) {
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
        if (!auth()->user()->tokenCan("repayment:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $payment->delete();

        return new DeleteResource($payment);
    }
}
