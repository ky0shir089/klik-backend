<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrxDtlRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\TrxDtl;
use Illuminate\Http\Request;

class TrxDetailController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("trx-dtl:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = TrxDtl::query()
            ->with(["trx", "coa"])
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
    public function store(TrxDtlRequest $request)
    {
        if (!auth()->user()->tokenCan("trx-dtl:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = TrxDtl::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(TrxDtl $trxDtl)
    {
        if (!auth()->user()->tokenCan("trx-dtl:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($trxDtl);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TrxDtlRequest $request, TrxDtl $trxDtl)
    {
        if (!auth()->user()->tokenCan("trx-dtl:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $trxDtl->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($trxDtl);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TrxDtl $trxDtl)
    {
        if (!auth()->user()->tokenCan("trx-dtl:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $trxDtl->delete();

        return new DeleteResource($trxDtl);
    }
}
