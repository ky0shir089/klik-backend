<?php

namespace App\Http\Controllers;

use App\Http\Requests\ModuleRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Module;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("module:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Module::query()
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->orderBy("position", "asc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ModuleRequest $request)
    {
        if (!auth()->user()->tokenCan("module:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $sql = Module::create($request->validated() + [
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Module $module)
    {
        if (!auth()->user()->tokenCan("module:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($module);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ModuleRequest $request, Module $module)
    {
        if (!auth()->user()->tokenCan("module:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $module->update($request->validated() + [
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($module);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Module $module)
    {
        if (!auth()->user()->tokenCan("module:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $module->delete();

        return new DeleteResource($module);
    }
}
