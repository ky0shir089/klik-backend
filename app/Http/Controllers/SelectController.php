<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Models\Auction;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\ByadHeader;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\GL;
use App\Models\InvoiceDetail;
use App\Models\InvoiceExternal;
use App\Models\Menu;
use App\Models\Module;
use App\Models\Payment;
use App\Models\PaymentVoucher;
use App\Models\Pph;
use App\Models\Role;
use App\Models\RV;
use App\Models\Settlement;
use App\Models\Spp;
use App\Models\Supplier;
use App\Models\TypeTrx;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SelectController extends Controller
{
    public function module()
    {
        $query = Module::query()
            ->orderBy("position", "asc")
            ->get();

        return new GetResource($query);
    }

    public function menuPermission()
    {
        $query = Menu::select("id", "name")
            ->with("permissions")
            ->orderBy("module_id", "asc")
            ->orderBy("position", "asc")
            ->get();

        return new GetResource($query);
    }

    public function role()
    {
        $query = Role::query()
            ->orderBy("id", "asc")
            ->get();

        return new GetResource($query);
    }

    public function coa(Request $request)
    {
        $query = ChartOfAccount::query()
            ->when($request->type == "BANK", function ($query) {
                $query->where("parent_id", 4);
            })
            ->when($request->type == "KAS", function ($query) {
                $query->where("parent_id", 3);
            })
            ->when($request->type == "PARENT", function ($query) {
                $query->whereNull("parent_id");
            })
            ->when($request->type == "CHILDREN", function ($query) {
                $query->whereNotNull("parent_id");
            })
            ->orderBy("code", "asc")
            ->get();

        return new GetResource($query);
    }

    public function bank()
    {
        $query = Bank::query()
            ->orderBy("name", "asc")
            ->get();

        return new GetResource($query);
    }

    public function typeTrx(Request $request)
    {
        $isTeamKlik = auth()->user()->role->id == 3;

        $query = TypeTrx::query()
            ->with(["trx_dtl", "trx_dtl.coa"])
            ->where("is_active", true)
            ->where("id", "!=", 2)
            ->when($request->in_out, function ($query, $in_out) {
                $query->where("in_out", $in_out);
            })
            ->when($isTeamKlik, function ($query) {
                $query->where("role_id", 3);
            })
            ->orderBy("name", "asc")
            ->get();

        return new GetResource($query);
    }

    public function bankAccount()
    {
        $query = BankAccount::query()
            ->with(["bank", "coa"])
            ->orderBy("account_name", "asc")
            ->get();

        return new GetResource($query);
    }

    public function titipanPelunasan(Request $request)
    {
        $query = RV::query()
            ->with(["type_trx", "account", "account.bank"])
            ->where("coa_id", 58)
            ->whereNull("customer_id")
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "rv_no",
                    "date",
                    "description",
                    "starting_balance",
                ], "ilike", "%$search%");
            })
            ->orderBy("id", "asc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function unpaidBidder(Request $request)
    {
        $query = Customer::query()
            ->with([
                "auctions",
            ])
            ->when($request->search, function ($query, $search) {
                $query->where("auction_date", "ilike", "%$search%")
                    ->orWhere("branch_name", "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function unpaidPayment(Request $request)
    {
        $query = PaymentVoucher::query()
            ->with([
                'processable',
                'processable.customer',
                'supplier',
                'supplier_account',
                'supplier_account.supplier',
                'supplier_account.bank',
            ])
            ->where("status", "NEW")
            ->when($request->method, function ($query, $method) {
                $query->where("payment_method", $method);
            })
            ->orderBy("id", "desc")
            ->get();

        return new GetResource($query);
    }

    public function supplier()
    {
        $query = Supplier::query()
            ->with(["account", "account.bank"])
            ->where("is_active", true)
            ->orderBy("name", "asc")
            ->get();

        return new GetResource($query);
    }

    public function pph()
    {
        $query = Pph::query()
            ->oldest("name")
            ->get();

        return new GetResource($query);
    }

    public function rv(Request $request)
    {
        $query = RV::select("id", "rv_no", "description", "ending_balance")
            ->where("ending_balance", ">", 0)
            ->whereNull("customer_id")
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "rv_no",
                    "description",
                    "ending_balance",
                ], "ilike", "%$search%");
            })
            ->oldest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function byadUnit(Request $request)
    {
        $query = Unit::select("id", "auction_id", "police_number", "chassis_number", "engine_number", "price", 'byad_amount')
            ->with([
                "auction:klik_auction_id,auction_date,customer_id",
                "auction.customer:klik_bidder_id,name",
            ])
            ->where("payment_status", "LUNAS")
            ->whereNull("byad_status")
            ->where("pejabat_lelang", $request->branch_name)
            ->whereHas("auction", function ($query) use ($request) {
                $query->whereBetween("auction_date", [$request->from_date, $request->to_date]);
            })
            ->oldest("id")
            ->get();

        return new GetResource($query);
    }

    public function branch()
    {
        $query = Unit::select("pejabat_lelang")
            ->where("payment_status", "LUNAS")
            ->whereNotNull("pejabat_lelang")
            ->groupBy("pejabat_lelang")
            ->oldest("pejabat_lelang")
            ->get();

        return new GetResource($query);
    }

    public function byad(Request $request)
    {
        $query = ByadHeader::query()
            ->where("status", "NEW")
            ->when($request->size === -1, function ($query) {
                return $query->oldest()->get();
            }, function ($query) use ($request) {
                return $query->oldest()->paginate($request->size);
            });

        return new GetResource($query);
    }

    public function user()
    {
        $query = User::select("id", "name")
            ->oldest("name")
            ->get();

        return new GetResource($query);
    }

    public function prepayment()
    {
        $query = Settlement::select("prepayment_pv_id", "balance")
            ->with([
                "pv:id,pv_no,description,supplier_id,supplier_account_id,paid_date",
                "pv.supplier:id,name",
                "pv.supplier_account:id,account_number,bank_id",
                "pv.supplier_account.bank:id,name",
            ])
            ->where("status", "OPEN")
            ->where("balance", ">", 0)
            ->latest()
            ->get();

        return new GetResource($query);
    }

    public function PaidOffUnit(Request $request)
    {
        $query = Unit::query()
            ->with([
                "auction",
                "auction.customer",
            ])
            ->where("payment_status", "LUNAS")
            ->whereRelation("spp", "status", "PAID")
            ->whereHas("auction", function ($query) use ($request) {
                $query->whereBetween("auction_date", [$request->from_date, $request->to_date]);
            })
            ->doesntHave("external")
            ->when($request->search, function ($query, $search) {
                $query->whereAny([
                    "police_number",
                    "chassis_number",
                    "engine_number",
                ], "ilike", "%$search%");
            });

        $sumFeeAmount = $query->sum("fee_amount");
        $result = $query->paginate($request->size);

        return response()->json([
            "success" => true,
            "data" => $result,
            "fee_amount" => $sumFeeAmount
        ]);
    }

    public function findDuplicate()
    {
        $query = RV::query()
            ->with([
                "classifications:rv_id,unit_id",
                "classifications.unit:id,byad_amount,fee_amount"
            ])
            ->where("id", 48)
            ->oldest("id")
            ->get()
            ->pluck("classifications")
            ->flatten()
            ->pluck("unit");

        return $query;
    }

    public function moneyInTransit()
    {
        $query = InvoiceDetail::select("id", "invoice_id", "total_amount", "description")
            ->with("invoice:id,invoice_no,description")
            ->where("inv_coa_id", 25)
            ->whereRelation("invoice", "status", "PAID")
            ->doesntHave("mit")
            ->oldest("id")
            ->get();

        return new GetResource($query);
    }

    public function external()
    {
        $query = InvoiceExternal::select("id", "invoice_external_no", "grand_total", "description")
            ->where("status", "OPEN")
            ->oldest("id")
            ->get();

        return new GetResource($query);
    }
}
