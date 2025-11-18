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
        if (!auth()->user()->tokenCan("repayment:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Customer::query()
            ->with(["auctions"])
            ->whereRelation("auctions.units", "payment_status", "UNPAID")
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CustomerRequest $request)
    {
        if (!auth()->user()->tokenCan("repayment:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = Customer::firstOrCreate(
            ['ktp' => $request->ktp],
            $request->validated() + [
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]
        );

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        if (!auth()->user()->tokenCan("repayment:read")) {
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
                    ->where("status", "NEW")
                    ->orderBy("ending_balance", "asc");
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
