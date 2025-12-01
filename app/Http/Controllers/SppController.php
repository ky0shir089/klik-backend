<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreResource;
use App\Models\Payment;
use App\Models\PaymentVoucher;
use App\Models\Spp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SppController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->tokenCan("repayment:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $pv_amount = 0;
            $rv_amount = 0;
            $spp_no = Spp::latest()->first() + 1;

            foreach ($request->id as $id) {
                $payment = Payment::find($id);
                $payment->status = "REQUEST";
                $payment->save();

                $spp = new Spp;
                $spp->spp_no = $spp_no;
                $spp->payment_id = $id;
                $spp->created_by = auth()->id();
                $spp->updated_at = null;
                $spp->save();

                $pv_amount += $payment->total_amount;
                $rv_amount += $payment->rv->ending_balance;
            }

            $sql = new PaymentVoucher();
            $sql->supplier_id = 1;
            $sql->supplier_account_id = 1;
            $sql->pv_amount = $pv_amount;
            $sql->rv_amount = $rv_amount;
            $sql->status = "NEW";
            $sql->trx_dtl_id = 2;
            $sql->processable_type = "App\Models\Spp";
            $sql->processable_id = $spp_no;
            $sql->created_by = auth()->id();
            $spp->updated_at = null;
            $sql->save();

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
    public function show(Spp $spp)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Spp $spp)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Spp $spp)
    {
        //
    }
}
