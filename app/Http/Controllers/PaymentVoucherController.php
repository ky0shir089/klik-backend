<?php

namespace App\Http\Controllers;

use App\Http\Requests\PvRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\GL;
use App\Models\PaymentVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!auth()->user()->tokenCan("pv:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = PaymentVoucher::query()
            ->with([
                'repayment',
                'repayment.customer',
                'supplier',
                'supplier_account',
                'supplier_account.supplier',
                'supplier_account.bank',
            ])
            ->orderBy("id", "desc")
            ->get();

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PvRequest $request)
    {
        if (!auth()->user()->tokenCan("pv:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $year = date("y");
            $pv_no = 'PV' . $year   . Str::padLeft(PaymentVoucher::count() + 1, 5, '0');
            $total_amount = 0;
            $trx_coa_id = 0;
            $bank_coa_id = 0;

            foreach ($request->pvs as $payment) {
                $pv = PaymentVoucher::find($payment);
                $pv->pv_no = $pv_no;
                $pv->description = $request->description;
                $pv->bank_account_id = $request->bank_account_id;
                $pv->rv_balance = $pv->rv_amount - $pv->pv_amount;
                $pv->status = "PAID";
                $pv->paid_date = now();
                $pv->updated_by = auth()->id();
                $pv->save();

                $total_amount += $pv->pv_amount;
                $trx_coa_id = $pv->trx_dtl->trx->id;
                $bank_coa_id = $pv->bank_account->coa_id;
            }

            $gl = [
                "gl_no" => $pv_no,
                "date" => now(),
                "type" => 'OUT',
                "description" => $request->description,
                "created_by" => auth()->id(),
                "updated_at" => null,
            ];

            $debit = [
                ...$gl,
                "coa_id" => $trx_coa_id,
                "debit" => $total_amount,
                "credit" => 0,
            ];

            $credit = [
                ...$gl,
                "coa_id" => $bank_coa_id,
                "debit" => 0,
                "credit" => $total_amount,
            ];

            GL::insert([$debit, $credit]);

            DB::commit();

            return new StoreResource($gl);
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
    public function show(PaymentVoucher $pv)
    {
        if (!auth()->user()->tokenCan("pv:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($pv);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PvRequest $request)
    {
        if (!auth()->user()->tokenCan("pv:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $year = date("y");
            $pv_no = 'PV' . $year   . Str::padLeft(PaymentVoucher::count() + 1, 5, '0');
            $total_amount = 0;
            $trx_coa_id = 0;
            $bank_coa_id = 0;

            foreach ($request->pvs as $payment) {
                $pv = PaymentVoucher::find($payment['id']);
                $pv->update($request->validated() + [
                    "pv_no" => $pv_no,
                    'updated_by' => auth()->id(),
                ]);
                $total_amount += $payment['pv_amount'];
                $trx_coa_id = $pv->trx_dtl->trx->id;
                $bank_coa_id = $pv->bank_account->coa_id;
            }

            $gl = [
                "gl_no" => $pv_no,
                "date" => now(),
                "type" => 'OUT',
                "description" => $request->description,
                "created_by" => auth()->id(),
                "updated_at" => null,
            ];

            $debit = [
                ...$gl,
                "coa_id" => $trx_coa_id,
                "debit" => $total_amount,
                "credit" => 0,
            ];

            $credit = [
                ...$gl,
                "coa_id" => $bank_coa_id,
                "debit" => 0,
                "credit" => $total_amount,
            ];

            GL::insert([$debit, $credit]);

            DB::commit();

            return new UpdateResource($gl);
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
    public function destroy(PaymentVoucher $pv)
    {
        if (!auth()->user()->tokenCan("pv:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $pv->delete();

        return new DeleteResource($pv);
    }
}
