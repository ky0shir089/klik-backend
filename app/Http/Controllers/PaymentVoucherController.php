<?php

namespace App\Http\Controllers;

use App\Http\Requests\PvRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\ByadDetail;
use App\Models\ByadHeader;
use App\Models\ByadPayment;
use App\Models\GL;
use App\Models\Payment;
use App\Models\PaymentVoucher;
use App\Models\RV;
use App\Models\Settlement;
use App\Models\Spp;
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
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "pv_no",
                    "description",
                    "pv_amount",
                ], "ilike", "%$search%")
                    ->orWhereRelation("supplier", function ($query) use ($search) {
                        $query->where("name", "ilike", "%$search%");
                    });
            })
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
            $year = Carbon::parse($request->paid_date)->format('y');
            $prefix = 'PV' . $year;

            $lastPv = PaymentVoucher::select("pv_no")
                ->whereNotNull("pv_no")
                ->where('pv_no', 'ilike', "$prefix%")
                ->latest('pv_no')
                ->first();

            $countPv = $lastPv ? (int) Str::after($lastPv->pv_no, $prefix) + 1 : 1;
            $pvNo = $prefix . Str::padLeft($countPv, 5, '0');

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
                $pv->paid_date = $request->paid_date;
                $pv->updated_by = $authId;
                $pv->save();

                $gl = [
                    "gl_no" => $pvNo,
                    "date" => $request->paid_date,
                    "type" => 'OUT',
                    "created_by" => $authId,
                    "created_at" => now(),
                    "updated_at" => null,
                ];

                if ($pv->trx_dtl_id == 2) {
                    $debit = [
                        ...$gl,
                        "description" => $request->description,
                        "coa_id" => $pv->trx_dtl->coa_id,
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

                    $sppNo = $pv->processable->invoice_no;
                    $spp = Payment::select("id", "spp_no")
                        ->with("spps")
                        ->where("spp_no", $sppNo)
                        ->first();
                    $sppIds = $spp->spps->pluck("spp_id");

                    Spp::whereIn("id", $sppIds)->update([
                        "status" => "PAID",
                        "updated_at" => null
                    ]);

                    $spp->update([
                        "status" => "PAID",
                    ]);
                } else {
                    foreach ($pv->processable->details as $detail) {
                        $pphAmount = 0;
                        $ppnAmount = 0;

                        if (isset($detail->pph_id)) {
                            $pphAmount = round($detail->item_amount * ($detail->pph->rate / 100));

                            $credit2 = [
                                ...$gl,
                                "description" => $detail->description,
                                "coa_id" => $detail->pph->coa_id,
                                "debit" => 0,
                                "credit" => $pphAmount,
                            ];
                        }

                        if ($detail->ppn_rate > 0) {
                            $ppnAmount = round($detail->item_amount * ($detail->ppn_rate / 100));

                            $debit2 = [
                                ...$gl,
                                "description" => $detail->description,
                                "coa_id" => 151,
                                "debit" => $ppnAmount,
                                "credit" => 0,
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
                            "credit" => $detail->total_amount,
                        ];

                        $ledger[] = $debit;
                        if ($ppnAmount > 0) {
                            $ledger[] = $debit2;
                        }
                        $ledger[] = $credit;
                        if ($pphAmount > 0) {
                            $ledger[] = $credit2;
                        }

                        if ($detail->rv_id) {
                            $rv = RV::find($detail->rv_id);
                            $rv->used_balance = $rv->used_balance + $detail->total_amount;
                            $rv->ending_balance = $rv->ending_balance - $detail->total_amount;
                            $rv->status = $rv->ending_balance == 0 ? "CLOSED" : "NEW";
                            $rv->save();
                        }
                    }

                    if ($pv->trx_dtl_id == 3) {
                        $byadPayment = ByadPayment::where("invoice_id", $pv->processable_id)->first();
                        if ($byadPayment) {
                            $byadPayment->status = "PAID";
                            $byadPayment->updated_by = $authId;
                            $byadPayment->save();

                            $byad_id = $byadPayment->details->pluck("byad_id");

                            ByadHeader::whereIn("id", $byad_id)->update([
                                "status" => "PAID",
                                "updated_by" => $authId,
                            ]);

                            $byadDetail = ByadDetail::whereIn("byad_id", $byad_id)->get();
                            foreach ($byadDetail as $detail) {
                                $detail->unit()->update([
                                    "byad_status" => "PAID",
                                    "updated_by" => $authId,
                                ]);
                            }
                        }
                    }

                    $isPrepayment = collect($pv->processable->details)->hasSole(fn($item) => $item["inv_coa_id"] == 21);
                    if ($isPrepayment) {
                        $pv->processable->settlement()->insert([
                            "prepayment_pv_id" => $pv->id,
                            "prepayment_amount" => $pv->pv_amount,
                            "balance" => $pv->pv_amount,
                            "created_by" => $authId,
                            "created_at" => now(),
                            "updated_at" => null
                        ]);
                    }

                    $isByhmd = collect($pv->processable->details)->hasSole(fn($item) => $item["inv_coa_id"] == 55);
                    if ($isByhmd) {
                        $settlement = Settlement::where('byhmd_invoice_id', $pv->processable_id)->first();
                        $settlement->balance = 0;
                        $settlement->status = "CLOSED";
                        $settlement->save();
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
