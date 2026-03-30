<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Requests\RvRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Auction;
use App\Models\Customer;
use App\Models\GL;
use App\Models\RV;
use App\Models\Unit;
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

            $checkDuplicate = RV::select("rv_no")
                ->where("rv_no", $rv_no)
                ->count();

            while ($checkDuplicate > 0) {
                $rv_no = $rvNo . Str::padLeft($countRv++, 5, '0');
                $checkDuplicate = RV::select("rv_no")
                    ->where("rv_no", $rv_no)
                    ->count();
            }

            $sql = RV::create($request->validated() + [
                'rv_no' => $rv_no,
                'ending_balance' => $request->starting_balance,
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]);

            $gl = [
                "gl_no" => $rv_no,
                "date" => $request->date,
                "type" => 'IN',
                "description" => $request->description,
                "created_by" => auth()->id(),
                "created_at" => now(),
            ];

            $debit = [
                ...$gl,
                "coa_id" => $request->pay_method == "BANK" ? $sql->account->coa->id : 149,
                "debit" => $request->starting_balance,
                "credit" => 0,
            ];

            $credit = [
                ...$gl,
                "coa_id" => $request->coa_id,
                "debit" => 0,
                "credit" => $request->starting_balance,
            ];

            GL::insert([$debit, $credit]);

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
