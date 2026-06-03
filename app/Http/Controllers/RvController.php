<?php

namespace App\Http\Controllers;

use App\Http\Requests\RvRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\GL;
use App\Models\Invoice;
use App\Models\InvoiceExternal;
use App\Models\RV;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RvController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("list-rv:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = RV::query()
            ->with(["type_trx", "account", "account.bank"])
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "rv_no",
                    "date",
                    "description",
                    "starting_balance",
                    "journal_number"
                ], "ilike", "%$search%");
            })
            ->when($request->type_trx_id, function ($query, $type_trx_id) {
                $query->where("type_trx_id", $type_trx_id);
            })
            ->when($request->method, function ($query, $method) {
                $query->where("pay_method", $method);
            })
            ->when($request->bank_account_id, function ($query, $bank_account_id) {
                $query->where("bank_account_id", $bank_account_id);
            })
            ->latest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RvRequest $request)
    {
        if (!auth()->user()->tokenCan("rv:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $currentYear = date('y');
            $findLastRvDate = RV::select("date")->latest()->first();
            $lastRvDate = $findLastRvDate->date ?? now();
            $lastRvYear = Carbon::parse($lastRvDate)->format('y');
            if ($currentYear > $lastRvYear) {
                $countRv = 1;
            } else {
                $countRv = RV::query()
                    ->where("date", ">=", date('Y') . "-01-01")
                    ->where("date", "<=", date('Y') . "-12-31")
                    ->count() + 1;
            }
            $rvNo = 'RV' . $currentYear;
            $rv_no = $rvNo . Str::padLeft($countRv++, 5, '0');

            $ledgers = [];

            $gl = [
                "gl_no" => $rv_no,
                "date" => $request->date,
                "type" => 'IN',
                "description" => $request->description,
                "created_by" => auth()->id(),
                "created_at" => now(),
            ];

            $type = null;
            $startingBalance = $request->starting_balance;
            $pph23 = 0;

            if ($request->invoice_id) {
                $invoice = Invoice::find($request->invoice_id);
                if ($invoice) {
                    $isCashOpname = $invoice->details->hasSole(fn($item) => $item["inv_coa_id"] == 25);
                    if ($isCashOpname) {
                        $type = Invoice::class;
                    }
                }

                $external = InvoiceExternal::find($request->invoice_id);
                if ($external) {
                    $pph23 = $external->pph23;
                    $type = InvoiceExternal::class;
                    $external->status = "CLOSE";
                    $external->save();

                    $startingBalance = $external->grand_total + $external->pph23;
                }

                $ledgers[] = [
                    ...$gl,
                    "coa_id" => 18,
                    "debit" => $pph23,
                    "credit" => 0,
                ];
            }

            $sql = RV::create($request->validated() + [
                'rv_no' => $rv_no,
                'ending_balance' => $request->starting_balance,
                'invoiceable_type' => $type,
                'invoiceable_id' => $request->invoice_id,
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]);

            $ledgers[] = [
                ...$gl,
                "coa_id" => $request->pay_method == "BANK" ? $sql->account->coa->id : 149,
                "debit" => $startingBalance - $pph23,
                "credit" => 0,
            ];

            $ledgers[] = [
                ...$gl,
                "coa_id" => $request->coa_id,
                "debit" => 0,
                "credit" => $startingBalance,
            ];

            GL::insert($ledgers);

            DB::commit();

            return new StoreResource($sql);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => "Internal Server Error",
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(RV $rv)
    {
        if (!auth()->user()->tokenCan("list-rv:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($rv->load([
            "type_trx",
            "account",
            "account.bank",
            "account.coa",
            "customer"
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, RV $rv)
    {
        if (!auth()->user()->tokenCan("list-rv:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $rv->update([
            'customer_id' => null,
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($rv);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(RV $rv)
    {
        if (!auth()->user()->tokenCan("list-rv:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $rv->delete();

        return new DeleteResource($rv);
    }
}
