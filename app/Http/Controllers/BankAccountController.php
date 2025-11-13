<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankAccountRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\bankAccount;
use Illuminate\Http\Request;

class bankAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("bank-account:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = BankAccount::query()
            ->with(["bank", "coa"])
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->orderBy("id", "asc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BankAccountRequest $request)
    {
        if (!auth()->user()->tokenCan("bank-account:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = BankAccount::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(BankAccount $bankAccount)
    {
        if (!auth()->user()->tokenCan("bank-account:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($bankAccount);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BankAccountRequest $request, BankAccount $bankAccount)
    {
        if (!auth()->user()->tokenCan("bank-account:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $bankAccount->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($bankAccount);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(BankAccount $bankAccount)
    {
        if (!auth()->user()->tokenCan("bank-account:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $bankAccount->delete();

        return new DeleteResource($bankAccount);
    }
}
