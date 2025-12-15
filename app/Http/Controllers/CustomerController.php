<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
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
            ->withCount("auctions")
            ->withSum("units", "price")
            ->withSum("units", "admin_fee")
            ->withSum("units", "final_price")
            ->whereRelation("units", "payment_status", "UNPAID")
            ->whereHas("rvs")
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
    public function store(CustomerRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        if (!auth()->user()->tokenCan("rv-classification:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($customer->load([
            "units" => function ($query) {
                $query->where("payment_status", "UNPAID");
            },
            "units.auction",
            "rvs" => function ($query) {
                $query->select("customer_id", "id", "rv_no", "date", "description", "ending_balance")
                    ->where("ending_balance", ">", 0)
                    ->where("status", "NEW")
                    ->oldest("id");
            },
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CustomerRequest $request, Customer $customer)
    {
        if (!auth()->user()->tokenCan("repayment:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $customer->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($customer);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        if (!auth()->user()->tokenCan("repayment:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $customer->delete();

        return new DeleteResource($customer);
    }
}
