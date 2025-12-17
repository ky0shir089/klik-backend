<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Models\Customer;
use App\Models\Spp;
use App\Models\SppDetail;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SppController extends Controller
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

        $query = Spp::query()
            ->with([
                "customer:klik_bidder_id,name",
            ])
            ->where("status", "NEW")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->tokenCan("spp:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $units = Unit::select("id", "distributed_price", "auction_id")
                ->with("auction")
                ->whereIn("id", $request->units)
                ->get();

            $totalUnit = $units->count();
            $totalAmount = $units->sum("distributed_price");
            $authId = auth()->id();

            $spp = new Spp;
            $spp->customer_id = $request->customer_id;
            $spp->branch_name = $units[0]->auction->branch_name;
            $spp->total_unit = $totalUnit;
            $spp->total_amount = $totalAmount;
            $spp->created_by = $authId;
            $spp->save();

            foreach ($units as $unit) {
                $details[] = [
                    "spp_id" => $spp->id,
                    "unit_id" => $unit->id,
                    "created_by" => $authId,
                    "created_at" => now(),
                ];

                Unit::find($unit->id)->update([
                    "spp_status" => "CREATED",
                    "updated_by" => $authId,
                ]);
            }

            SppDetail::insert($details);

            DB::commit();

            return new StoreResource($spp);
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
        if (!auth()->user()->tokenCan("spp:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $spp->load([
            "customer",
            "details",
            "details.unit",
            "details.unit.auction",
        ]);

        return new GetResource($spp);
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
