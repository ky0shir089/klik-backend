<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\UpdateResource;
use App\Models\Customer;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ->withCount([
                "units as units_count" => function ($query) {
                    $query->where("payment_status", "UNPAID");
                }
            ])
            ->withSum([
                "units" => function ($query) {
                    $query->where("payment_status", "UNPAID");
                }
            ], "price")
            ->withSum([
                "units" => function ($query) {
                    $query->where("payment_status", "UNPAID");
                }
            ], "admin_fee")
            ->withSum([
                "units" => function ($query) {
                    $query->where("payment_status", "UNPAID");
                }
            ], "final_price")
            ->whereHas("units", function ($query) {
                $query->where("payment_status", "UNPAID")
                    ->whereNull("reference_id");
            })
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "name",
                    "va_number",
                ], "ilike", "%$search%");
            })
            ->when($request->tab == "rv", function ($query) {
                $query->whereHas("rvs");
            }, function ($query) {
                $query->whereDoesntHave("rvs");
            })
            ->oldest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function vaAuto(Request $request)
    {
        if (!auth()->user()->tokenCan("rv-classification:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $from = $request->from_date ? $request->from_date . ' 00:00:00' : null;
        $to = $request->to_date ? $request->to_date . ' 23:59:59' : null;

        $query =  Unit::select(
            "customers.name",
            "units.paid_date",
            "units.reference_id",
            DB::raw('COUNT(units.id) as total_units'),
            DB::raw('SUM(price) as total_price'),
            DB::raw('SUM(ticket_price) as total_ticket_price'),
            DB::raw('SUM(admin_fee) as total_admin_fee'),
            DB::raw('SUM(final_price) as total_final_price'),
        )
            ->join('auctions', 'units.auction_id', '=', 'auctions.klik_auction_id')
            ->join('customers', 'auctions.customer_id', '=', 'customers.klik_bidder_id')
            ->where("payment_status", "UNPAID")
            ->whereNotNull("reference_id")
            ->whereNotNull("paid_date")
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "customers.name",
                    "units.reference_id",
                    "units.police_number",
                ], "ilike", "%$search%");
            })
            ->when($from || $to, function ($query) use ($from, $to) {
                $query->whereBetween('units.paid_date', [$from, $to]);
            })
            ->oldest("units.paid_date")
            ->groupBy("customers.name", "units.paid_date", "units.reference_id")
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
                $query->whereNull("reference_id")
                    ->where("payment_status", "UNPAID")
                    ->oldest("id");
            },
            "units.auction" => function ($query) {
                $query->oldest("auction_date");
            },
            "rvs" => function ($query) {
                $query->select("customer_id", "id", "rv_no", "date", "description", "ending_balance")
                    ->whereIn("coa_id", [58, 157])
                    ->where("ending_balance", ">", 0)
                    ->where("status", "NEW")
                    ->oldest("date")
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
