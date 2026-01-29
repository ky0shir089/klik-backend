<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Supplier;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("supplier:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Supplier::query()
            ->with(["account", "account.bank"])
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%")
                    ->orWhere("id", $search);
            })
            ->orderBy("name", "asc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SupplierRequest $request)
    {
        if (!auth()->user()->tokenCan("supplier:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = Supplier::create($request->validated() + [
            'is_active' => true,
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        $sql->account()->create([
            'bank_id' => $request->bank_id,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'created_by' => auth()->id(),
            'updated_at' => null
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Supplier $supplier)
    {
        if (!auth()->user()->tokenCan("supplier:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($supplier->load("account"));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SupplierRequest $request, Supplier $supplier)
    {
        if (!auth()->user()->tokenCan("supplier:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $supplier->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        $supplier->account()->update([
            'bank_id' => $request->bank_id,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($supplier);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Supplier $supplier)
    {
        if (!auth()->user()->tokenCan("supplier:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $supplier->delete();

        return new DeleteResource($supplier);
    }
}
