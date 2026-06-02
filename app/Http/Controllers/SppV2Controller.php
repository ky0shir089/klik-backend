<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Http\Resources\UpdateResource;
use App\Models\Spp;
use App\Models\SppDetail;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SppV2Controller extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("spp:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $page = $request->page;
        $rows = $request->rows;
        $offset = ($page - 1) * $rows;

        //prod
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.klik')['token'],
        ])->get('https://api.kliklelang.co.id/api/transaksi/v5/spp/list', [
            'id_mst_status_spp' => 1,
            'limit' => $rows,
            'offset' => $offset,
            'search' => $request->search,
        ]);

        //dev
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdXRob3JpemVkIjp0cnVlLCJjbGllbnRfaWQiOjE1NTM0LCJjbGllbnRfcGxhdGZvcm0iOiJtb2JpbGUiLCJjbGllbnRfcm9sZSI6ImJhbGFuZyIsImNsaWVudF90eXBlIjoiaGVhZG9mZmljZSIsImV4cCI6MTc3NDE3MDE2Mn0.7z0DOfonq1UFGpMWNUBtNglxiSGYjKU0xlnmJ9nCagQ',
        // ])->get('https://api.devlmu.com/kliklelang/transaksi/api/transaksi/v5/spp/list', [
        //     'id_mst_status_spp' => 1,
        //     'limit' => $rows,
        //     'offset' => $offset,
        //     'search' => $request->search,
        // ]);

        if ($response->forbidden()) {
            return response()->json([
                "success" => false,
                "message" => $response["api_message"],
            ]);
        }

        $results["data"] = $response["data"];
        $results["meta"] = [
            "total" => $response["count"],
            "limit" => (int) $rows,
            "last_page" => round($response["count"] / $rows),
            "offset" => $offset,
        ];

        return new GetResource($results);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        if (!auth()->user()->tokenCan("spp:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        //prod
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.klik')['token'],
        ])->get('https://api.kliklelang.co.id/api/transaksi/v5/spp/detail/' . $request->sppV2);

        //dev
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdXRob3JpemVkIjp0cnVlLCJjbGllbnRfaWQiOjE1NTM0LCJjbGllbnRfcGxhdGZvcm0iOiJtb2JpbGUiLCJjbGllbnRfcm9sZSI6ImJhbGFuZyIsImNsaWVudF90eXBlIjoiaGVhZG9mZmljZSIsImV4cCI6MTc3NDE3MDE2Mn0.7z0DOfonq1UFGpMWNUBtNglxiSGYjKU0xlnmJ9nCagQ',
        // ])->get('https://api.devlmu.com/kliklelang/transaksi/api/transaksi/v5/spp/detail/' . $request->sppV2);

        if ($response->forbidden()) {
            return response()->json([
                "success" => false,
                "message" => $response["api_message"],
            ]);
        }

        $results = $response["data"];
        $units = $results["spp_id_order"];
        $files = $results["spp_files"];
        $unitDetail = [];

        foreach ($units as $unit) {
            $mokas = Unit::with("auction")
                ->where("klik_unit_id", $unit["id_motor_bekas"])
                ->first();
            $price = $unit["harga_distribusi"] == 0 ? $unit["harga_spp"] : $unit["harga_distribusi"];
            $mokas->contract_number = $unit["no_kontrak"];
            $mokas->package_number = $results["nomor_paket"];
            $mokas->distributed_price = $price;
            $mokas->diff_price = $price - $mokas->price + $mokas->ticket_price;

            $unitDetail[] = $mokas;
        }

        $sumPrice = collect($unitDetail)->sum("price");
        $sumTicketPrice = collect($unitDetail)->sum("ticket_price");
        $sumAdminFee = collect($unitDetail)->sum("admin_fee");
        $sumFinalPrice = collect($unitDetail)->sum("final_price");
        $sumDistributedPrice = collect($unitDetail)->sum("distributed_price");
        $sumDiffPrice = collect($unitDetail)->sum("diff_price");

        $data = [
            "id" => $request->sppV2,
            "customer_id" => $results["id_app_user"],
            "bidder_name" => $results["nama_bidder"],
            "branch_name" => $results["nama_cabang"],
            "package_number" => $results["nomor_paket"],
            "sum_price" => $sumPrice,
            "sum_ticket_price" => $sumTicketPrice,
            "sum_admin_fee" => $sumAdminFee,
            "sum_final_price" => $sumFinalPrice,
            "sum_distributed_price" => $sumDistributedPrice,
            "sum_diff_price" => $sumDiffPrice,
            "units" => $unitDetail,
            "files" => $files,
        ];

        return new GetResource($data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        if (!auth()->user()->tokenCan("spp:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request) {
            //prod
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.klik')['token'],
            ])->post('https://api.kliklelang.co.id/api/transaksi/v5/siskeu/sync-status', [
                'spp_id' => $request->spp_id,
                'status' => $request->status,
                'alasan' => $request->alasan,
            ]);

            //dev
            // $response = Http::withHeaders([
            //     'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdXRob3JpemVkIjp0cnVlLCJjbGllbnRfaWQiOjE1NTM0LCJjbGllbnRfcGxhdGZvcm0iOiJtb2JpbGUiLCJjbGllbnRfcm9sZSI6ImJhbGFuZyIsImNsaWVudF90eXBlIjoiaGVhZG9mZmljZSIsImV4cCI6MTc3NDE3MDE2Mn0.7z0DOfonq1UFGpMWNUBtNglxiSGYjKU0xlnmJ9nCagQ',
            // ])->post('https://api.devlmu.com/kliklelang/transaksi/api/transaksi/v5/siskeu/sync-status', [
            //     'spp_id' => $request->spp_id,
            //     'status' => $request->status,
            //     'alasan' => $request->alasan,
            // ]);

            if ($response->forbidden()) {
                return response()->json([
                    "success" => false,
                    "message" => $response["api_message"],
                ]);
            }

            if ($request->status !== "rejected") {
                $units = collect($request->units);
                $totalUnit = $units->count();
                $totalAmount = $units->sum("distributed_price");
                $authId = auth()->id();

                $spp = new Spp();
                $spp->customer_id = $request->customer_id;
                $spp->branch_name = $request->branch_name;
                $spp->total_unit = $totalUnit;
                $spp->total_amount = $totalAmount;
                $spp->created_by = $authId;
                $spp->save();

                foreach ($units as $unit) {
                    $updateUnit = Unit::find($unit["id"]);
                    $updateUnit->package_number = $unit["package_number"];
                    $updateUnit->contract_number = $unit["contract_number"];
                    $updateUnit->distributed_price = $unit["distributed_price"];
                    $updateUnit->diff_price = $unit["distributed_price"] - $updateUnit->price + $updateUnit->ticket_price;
                    $updateUnit->spp_status = "CREATED";
                    $updateUnit->updated_by = $authId;
                    $updateUnit->save();

                    $details[] = [
                        "spp_id" => $spp->id,
                        "unit_id" => $updateUnit->id,
                        "created_by" => $authId,
                        "created_at" => now(),
                        "updated_at" => null
                    ];
                }

                SppDetail::insert($details);
            }
        });

        return response()->json([
            "success" => true,
            "message" => "Spp status updated",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
