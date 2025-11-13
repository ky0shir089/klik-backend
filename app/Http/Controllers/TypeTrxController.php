<?php

namespace App\Http\Controllers;

use App\Http\Requests\TypeTrxRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\TypeTrx;
use Illuminate\Http\Request;

class TypeTrxController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("type-trx:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = TypeTrx::query()
            ->with(["trx_dtl"])
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
    public function store(TypeTrxRequest $request)
    {
        if (!auth()->user()->tokenCan("type-trx:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = TypeTrx::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);      

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(TypeTrx $typeTrx)
    {
        if (!auth()->user()->tokenCan("type-trx:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($typeTrx);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TypeTrxRequest $request, TypeTrx $typeTrx)
    {
        if (!auth()->user()->tokenCan("type-trx:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $typeTrx->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($typeTrx);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TypeTrx $typeTrx)
    {
        if (!auth()->user()->tokenCan("type-trx:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $typeTrx->delete();

        return new DeleteResource($typeTrx);
    }
}
