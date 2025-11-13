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
            $sql = Payment::create($request->safe()->except(["units", "rvs"]) + [
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]);

            $units = [];
            foreach ($request->units as $unit) {
                $units[] = [
                    "unit_id" => $unit,
                    "created_by" => auth()->id(),
                ];
            }
            $sql->units()->createMany($units);

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
            $sql->rvs()->createMany($rvs);
            $total_rv = collect($rvs)->sum("rv_amount");

            Unit::whereIn("id", $request->units)->update([
                'payment_status' => 'REQUEST',
            ]);
            RV::whereIn("id", $request->rvs)->update([
                'status' => 'USED',
            ]);

            $pv = new PaymentVoucher;
            $pv->supplier_id = 1;
            $pv->supplier_account_id = 1;
            $pv->pv_process_id = $sql->id;
            $pv->pv_amount = $request->total_amount;
            $pv->rv_amount = $total_rv;
            $pv->status = "NEW";
            $pv->trx_dtl_id = 2;
            $pv->created_by = auth()->id();
            $pv->save();

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
