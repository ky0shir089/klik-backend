<?php

namespace App\Http\Controllers;

use App\Http\Requests\PvRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\GL;
use App\Models\PaymentVoucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentVoucherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("pv:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = PaymentVoucher::query()
            ->with([
                'processable',
                'processable.customer',
                'supplier',
                'supplier_account',
                'supplier_account.supplier',
                'supplier_account.bank',
                'bank_account',
                'bank_account.bank',
            ])
            ->where("status", "PAID")
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PvRequest $request)
    {
        if (!auth()->user()->tokenCan("pv:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $currentYear = date('y');
            $findLastRvDate = PaymentVoucher::select("created_at")->latest()->first();
            $lastPvDate = $findLastRvDate->date ?? now();
            $lastPvYear = Carbon::parse($lastPvDate)->format('y');
            if ($currentYear > $lastPvYear) {
                $countPv = 1;
            } else {
                $countPv = PaymentVoucher::query()
                    ->where("created_at", ">=", date('Y') . "-01-01")
                    ->where("created_at", "<=", date('Y') . "-12-31")
                    ->count() + 1;
            }
            $pvNo = 'PV' . $currentYear . Str::padLeft($countPv++, 5, '0');

            $total_amount = 0;
            $trx_coa_id = 0;
            $bank_coa_id = 0;
            $authId = auth()->id();

            foreach ($request->pvs as $payment) {
                $pv = PaymentVoucher::find($payment);
                // $pv_amount = $pv->pv_amount;


                // if ($pv->trx_dtl_id == 2) {
                //     $rvs = $pv->processable->rvs;

                //     foreach ($rvs as $rv) {
                //         if ($pv_amount > 0) {
                //             $ending_balance = $rv->rv->ending_balance;
                //             $starting_balance = $rv->rv->starting_balance;

                //             $used_balance = $ending_balance >= $pv_amount ? $pv_amount : $starting_balance;
                //             $ending_balance = $ending_balance - $used_balance;

                //             $pv_amount = $pv_amount - $used_balance;

                //             $rv->rv()->update([
                //                 "used_balance" => $used_balance,
                //                 "ending_balance" => $ending_balance,
                //                 "status" => $ending_balance == 0 ? "CLOSED" : "NEW",
                //                 "customer_id" => $ending_balance == 0 ? $rv->rv->customer_id : NULL,
                //                 "updated_by" => $authId,
                //             ]);
                //         }
                //     }
                // }

                $pv->pv_no = $pvNo;
                $pv->description = $request->description;
                $pv->bank_account_id = $request->bank_account_id;
                $pv->status = "PAID";
                $pv->paid_date = now();
                $pv->updated_by = $authId;
                $pv->save();

                $total_amount += $pv->pv_amount;
                $trx_coa_id = $pv->trx_dtl->trx->id;
                $bank_coa_id = $pv->bank_account->coa_id;

                $pv->processable()->update([
                    "status" => "PAID",
                    "updated_by" => $authId,
                ]);
            }

            $gl = [
                "gl_no" => $pvNo,
                "date" => now(),
                "type" => 'OUT',
                "description" => $request->description,
                "created_by" => $authId,
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
    public function update(PvRequest $request, PaymentVoucher $pv)
    {
        if (!auth()->user()->tokenCan("pv:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = $pv->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($sql);
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
