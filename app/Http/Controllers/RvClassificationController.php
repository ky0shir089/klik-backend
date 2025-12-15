<?php

namespace App\Http\Controllers;

use App\Http\Requests\RvClassificationRequest;
use App\Http\Resources\GetResource;
use App\Models\Customer;
use App\Models\RV;
use App\Models\RvClassification;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RvClassificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("rv-classification:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Customer::select("klik_bidder_id", "name", "va_number")
            ->withCount([
                "units" => function ($query) {
                    $query->where("payment_status", "LUNAS")
                        ->where(function ($query) {
                            $query->whereNull("spp_status")
                                ->orWhere("spp_status", "UPLOADED");
                        });
                }
            ])
            ->withSum([
                "units" => function ($query) {
                    $query->where("payment_status", "LUNAS")
                        ->where(function ($query) {
                            $query->whereNull("spp_status")
                                ->orWhere("spp_status", "UPLOADED");
                        });
                }
            ], "price")
            ->withSum([
                "units" => function ($query) {
                    $query->where("payment_status", "LUNAS")
                        ->where(function ($query) {
                            $query->whereNull("spp_status")
                                ->orWhere("spp_status", "UPLOADED");
                        });
                }
            ], "admin_fee")
            ->withSum([
                "units" => function ($query) {
                    $query->where("payment_status", "LUNAS")
                        ->where(function ($query) {
                            $query->whereNull("spp_status")
                                ->orWhere("spp_status", "UPLOADED");
                        });
                }
            ], "final_price")
            ->whereHas("units", function ($query) {
                $query->where("payment_status", "LUNAS")
                    ->where(function ($query) {
                        $query->whereNull("spp_status")
                            ->orWhere("spp_status", "UPLOADED");
                    });
            })
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "name",
                    "va_number",
                ], "ilike", "%$search%");
            })
            ->oldest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RvClassificationRequest $request)
    {
        if (!auth()->user()->tokenCan("rv-classification:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $units = $request->units;
        $rvs = $request->rvs;

        $authId = auth()->id();

        $totalAmount = 0;
        $totalRv = 0;

        $classifications = [];

        DB::transaction(function () use ($units, $rvs, $authId, &$totalAmount, &$totalRv, $classifications) {
            foreach ($units as $unit) {
                $unitData = Unit::find($unit);
                $totalAmount += $unitData->final_price;

                foreach ($rvs as $rv) {
                    $rvData = RV::find($rv);
                    if ($rvData->ending_balance == 0) continue;
                    $totalRv += $rvData->ending_balance;

                    $calculate = $rvData->ending_balance - $unitData->final_price;

                    $classifications[] = [
                        "unit_id" => $unit,
                        "rv_id" => $rv,
                        "rv_amount" => $rvData->ending_balance,
                        "unit_final_price" => $unitData->final_price,
                        "rv_balance" => $calculate < 0 ? 0 : $calculate,
                        "created_by" => $authId,
                        "created_at" => now(),
                    ];

                    if ($totalAmount > $totalRv) {
                        info("totalAmount > totalRv");
                        info($totalAmount);
                        $rvData->used_balance = $rvData->used_balance + $rvData->ending_balance;
                        $rvData->ending_balance = $rvData->ending_balance - $rvData->used_balance;
                        $rvData->status = $rvData->ending_balance == 0 ? "CLOSED" : "NEW";
                        $rvData->updated_by = $authId;
                        $rvData->save();
                        info($rvData);

                        $totalAmount -= $rvData->used_balance;
                        $totalRv -= $rvData->ending_balance;
                        info($totalAmount);
                    }

                    if ($totalAmount <= $totalRv) {
                        info("totalAmount <= totalRv");
                        info($totalAmount);
                        $rvData->used_balance = $rvData->used_balance + $totalAmount - $unitData->admin_fee;
                        $rvData->admin_fee = $rvData->admin_fee + $unitData->admin_fee;
                        $rvData->ending_balance = $rvData->starting_balance - $rvData->used_balance - $rvData->admin_fee;
                        $rvData->status = $rvData->ending_balance == 0 ? "CLOSED" : "NEW";
                        $rvData->updated_by = $authId;
                        $rvData->save();
                        info($rvData);

                        $totalAmount -= $rvData->used_balance + $rvData->admin_fee;
                        info($totalAmount);

                        if ($totalAmount <= 0) {
                            $totalAmount = 0;
                            break;
                        };
                    }
                }

                $unitData->payment_status = "LUNAS";
                $unitData->updated_by = $authId;
                $unitData->save();
            }


            RvClassification::insert($classifications);
        });

        return response()->json([
            "success" => true,
            "message" => "Rv classification created successfully"
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $rvClassification)
    {
        if (!auth()->user()->tokenCan("rv-classification:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($rvClassification->load([
            "units" => function ($query) {
                $query->where("payment_status", "LUNAS")
                    ->where(function ($query) {
                        $query->whereNull("spp_status")
                            ->orWhere("spp_status", "UPLOADED");
                    })
                    ->oldest("id");
            },
            "units.auction",
            "rvs" => function ($query) {
                $query->select("customer_id", "id", "rv_no", "date", "description", "starting_balance")
                    ->where("status", "!=", "NEW")
                    ->oldest("id");
            },
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
