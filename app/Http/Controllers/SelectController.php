<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Module;
use App\Models\PaymentVoucher;
use App\Models\Role;
use App\Models\RV;
use App\Models\TypeTrx;
use Illuminate\Http\Request;

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
            ->when($request->type == "PARENT", function ($query) {
                $query->whereNull("parent_id");
            })
            ->when($request->type == "CHILDREN", function ($query) {
                $query->whereNotNull("parent_id");
            })
            ->orderBy("id", "asc")
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
        $query = TypeTrx::query()
            ->with(["trx_dtl", "trx_dtl.coa"])
            ->where("is_active", true)
            ->when($request->in_out, function ($query, $in_out) {
                $query->where("in_out", $in_out);
            })
            ->orderBy("id", "asc")
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
            ->with(["auctions"])
            ->whereRelation("auctions.units", "payment_status", "UNPAID")
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->orderBy("id", "desc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    public function unpaidPayment()
    {
        $query = PaymentVoucher::query()
            ->with([
                'repayment',
                'repayment.customer',
                'supplier',
                'supplier_account',
                'supplier_account.supplier',
                'supplier_account.bank',
            ])
            ->where("status", "NEW")
            ->orderBy("id", "desc")
            ->get();

        return new GetResource($query);
    }
}
