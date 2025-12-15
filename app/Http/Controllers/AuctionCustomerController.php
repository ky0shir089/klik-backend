<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Models\AuctionCustomer;
use App\Models\Customer;
use Illuminate\Http\Request;

class AuctionCustomerController extends Controller
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

        $query = AuctionCustomer::query()
            ->with(["customer", "auction"])
            ->when($request->search, function ($query, $search) {
                $query->whereRelation("customer", "name", "ilike", "%$search%")
                    ->orWhereRelation("auction", "branch_name", "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
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
    public function show(Customer $auctionCustomer)
    {
        if (!auth()->user()->tokenCan("repayment:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($auctionCustomer->load([
            "auctions"
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
