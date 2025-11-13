<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Models\Bank;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Menu;
use App\Models\Module;
use App\Models\Role;
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
        info($request);
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
}
