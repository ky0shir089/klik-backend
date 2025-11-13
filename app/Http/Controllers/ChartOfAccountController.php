<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChartOfAccountRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;

class ChartOfAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (!auth()->user()->tokenCan("coa:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = ChartOfAccount::query()
            ->with(["children"])
            ->whereNull("parent_id")
            ->orderBy("id", "asc")
            ->get();

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ChartOfAccountRequest $request)
    {
        if (!auth()->user()->tokenCan("coa:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = ChartOfAccount::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(ChartOfAccount $coa)
    {
        if (!auth()->user()->tokenCan("coa:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($coa);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ChartOfAccountRequest $request, ChartOfAccount $coa)
    {
        if (!auth()->user()->tokenCan("coa:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $coa->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($coa);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ChartOfAccount $coa)
    {
        if (!auth()->user()->tokenCan("coa:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $coa->delete();

        return new DeleteResource($coa);
    }
}
