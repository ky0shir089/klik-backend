<?php

namespace App\Http\Controllers;

use App\Http\Requests\XenditRequest;
use App\Models\RV;
use App\Models\Xendit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class XenditController extends Controller
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
    public function store(XenditRequest $request)
    {
        if (!auth()->user()->tokenCan("rv:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $result = DB::transaction(function () use ($request) {
            //prod
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.klik')['token'],
            ])->get('https://api.kliklelang.co.id/api/transaksi/v6/withdrawal/withdrawals', [
                'date_from' => $request->start_date,
                'date_to' => $request->end_date,
                'offset' => 0,
                'limit' => 1,
            ]);

            //dev
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdXRob3JpemVkIjp0cnVlLCJjbGllbnRfaWQiOjE1NTA3LCJjbGllbnRfcGxhdGZvcm0iOiJtb2JpbGUiLCJjbGllbnRfcm9sZSI6InVzZXIiLCJjbGllbnRfdHlwZSI6InJlZ3VsZXIiLCJleHAiOjIwODYxNjE3NDN9.agXJOdVofDxZ8QP6ZG2AK__iVcUdBXF0GLZPZUnW_MM',
            // ])->get('https://api.devlmu.com/kliklelang/transaksi/api/transaksi/v6/withdrawal/withdrawals', [
            //     'date_from' => $request->start_date,
            //     'date_to' => $request->end_date,
            //     'offset' => 0,
            //     'limit' => 1,
            // ]);

            if (!isset($response["data"])) {
                return response()->json([
                    "success" => false,
                    "message" => $response["api_message"],
                ]);
            }

            if (count($response["data"]) == 0) {
                return response()->json([
                    "success" => false,
                    "message" => "Data not found",
                ]);
            }

            $results = $response["data"][0] ?? [];

            $check = RV::where("journal_number", "XENDIT #" . $results["reference_id"])->first();

            if ($check) {
                return response()->json([
                    "success" => false,
                    "message" => "Data already exist",
                ]);
            }

            $year = Carbon::parse($request->date)->format('y');
            $prefix = 'RV' . $year;

            $lastRv = RV::select("rv_no")
                ->where('rv_no', 'ilike', "$prefix%")
                ->latest('id')
                ->latest('rv_no')
                ->first();

            $countRv = $lastRv ? (int) Str::after($lastRv->rv_no, $prefix) + 1 : 1;
            $rv_no = $prefix . Str::padLeft($countRv, 5, '0');

            $data = [
                'rv_no' => $rv_no,
                'date' => $results["payout_date"],
                'type_trx_id' => 1,
                'description' => 'PELUNASAN XENDIT',
                'pay_method' => 'BANK',
                'bank_account_id' => '8555888799',
                'coa_id' => 157,
                'starting_balance' => $results["amount"],
                'ending_balance' => $results["amount"],
                'journal_number' => 'XENDIT #' . $results["reference_id"],
                'created_by' => auth()->id(),
                'updated_at' => null,
            ];

            RV::insert($data);

            return response()->json([
                "success" => true,
                "message" => "Success",
            ]);
        });

        return $result;
    }

    /**
     * Display the specified resource.
     */
    public function show(Xendit $xendit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Xendit $xendit)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Xendit $xendit)
    {
        //
    }
}
