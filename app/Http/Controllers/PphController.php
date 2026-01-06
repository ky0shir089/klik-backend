<?php

namespace App\Http\Controllers;

use App\Http\Requests\PphRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Pph;
use Illuminate\Http\Request;

class PphController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("pph:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Pph::query()
            ->with("coa")
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "$search%");
            })
            ->oldest()
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PphRequest $request)
    {
        if (!auth()->user()->tokenCan("pph:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = Pph::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Pph $pph)
    {
        if (!auth()->user()->tokenCan("pph:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($pph);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PphRequest $request, Pph $pph)
    {
        if (!auth()->user()->tokenCan("pph:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $pph->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($pph);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pph $pph)
    {
        if (!auth()->user()->tokenCan("pph:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $pph->delete();

        return new DeleteResource($pph);
    }
}
