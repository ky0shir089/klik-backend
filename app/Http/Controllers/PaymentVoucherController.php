<?php

namespace App\Http\Controllers;

use App\Http\Requests\PvRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\GL;
use App\Models\PaymentVoucher;
use App\Models\Pph;
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
                    ->whereNotNull("pv_no")
                    ->where("created_at", ">=", date('Y') . "-01-01")
                    ->where("created_at", "<=", date('Y') . "-12-31")
                    ->count() + 1;
            }
            $pvNo = 'PV' . $currentYear . Str::padLeft($countPv++, 5, '0');

            $trxs = [];
            $authId = auth()->id();
            $ledger = [];

            foreach ($request->pvs as $payment) {
                $pv = PaymentVoucher::find($payment);

                $trxs[] = $pv->trx_dtl_id;

                $pv->pv_no = $pvNo;
                $pv->description = $request->description;
                $pv->payment_method = $request->payment_method;
                $pv->bank_account_id = $request->bank_account_id;
                $pv->status = "PAID";
                $pv->paid_date = now();
                $pv->updated_by = $authId;
                $pv->save();

                $gl = [
                    "gl_no" => $pvNo,
                    "date" => now(),
                    "type" => 'OUT',
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                if ($pv->trx_dtl_id == 2) {
                    $debit = [
                        ...$gl,
                        "description" => $request->description,
                        "coa_id" => $pv->trx_dtl->trx->id,
                        "debit" => $pv->pv_amount,
                        "credit" => 0,
                    ];

                    $credit = [
                        ...$gl,
                        "description" => $request->description,
                        "coa_id" => $pv->payment_method == "BANK" ? $pv->bank_account->coa_id : 149,
                        "debit" => 0,
                        "credit" => $pv->pv_amount,
                    ];

                    $ledger[] = $debit;
                    $ledger[] = $credit;
                } else {
                    foreach ($pv->processable->details as $detail) {
                        $pphAmount = 0;

                        if (isset($detail->pph_id)) {
                            $pphAmount = $detail->item_amount * ($detail->pph->rate / 100);

                            $credit2 = [
                                ...$gl,
                                "description" => $detail->description,
                                "coa_id" => $detail->pph->coa_id,
                                "debit" => 0,
                                "credit" => $pphAmount,
                            ];
                        }

                        $debit = [
                            ...$gl,
                            "description" => $detail->description,
                            "coa_id" => $detail->inv_coa_id,
                            "debit" => $detail->item_amount,
                            "credit" => 0,
                        ];

                        $credit = [
                            ...$gl,
                            "description" => $detail->description,
                            "coa_id" => $pv->payment_method == "BANK" ? $pv->bank_account->coa_id : 149,
                            "debit" => 0,
                            "credit" => $detail->item_amount - $pphAmount,
                        ];

                        $ledger[] = $debit;
                        $ledger[] = $credit;
                        if (isset($credit2)) {
                            $ledger[] = $credit2;
                        }
                    }
                }

                $pv->processable()->update([
                    "status" => "PAID",
                    "updated_by" => $authId,
                ]);
            }

            if (collect($trxs)->unique()->count() > 1) {
                return response()->json([
                    "success" => false,
                    "message" => "Transaction must be the same",
                ], 400);
            }

            GL::insert($ledger);

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
