<?php

namespace App\Http\Controllers;

use App\Http\Resources\UpdateResource;
use App\Models\RV;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
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
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Unit $unit)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Unit $unit)
    {
        if (!auth()->user()->tokenCan("spp:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $success = false;
        $message = "";
        $code = 400;

        DB::transaction(function () use ($unit, &$success, &$message) {
            $unit->update([
                "payment_status" => "UNPAID",
                'updated_by' => auth()->id(),
            ]);

            $pluckedRvs = $unit->classifications()->pluck("rv_id");
            $rvs = RV::whereIn("id", $pluckedRvs)->get();
            $countRv = $rvs->count();

            if ($countRv == 1) {
                foreach ($rvs as $rv) {
                    $rv->used_balance = $rv->used_balance - $unit->price;
                    $rv->admin_fee = $rv->admin_fee - $unit->admin_fee;
                    $rv->ending_balance = $rv->starting_balance - $rv->used_balance - $rv->admin_fee;
                    $rv->status = "NEW";
                    $rv->save();
                }

                $success = true;
                $message = "Unit Cancelled";
                $code = 200;
            } else {
                $message = "Unit tidak bisa di cancel karena menggunakan lebih dari 1 RV";
            }
        });

        return response()->json([
            "success" => $success,
            "message" => $message,
        ], $code);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Unit $unit)
    {
        //
    }
}
