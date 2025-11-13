<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("role:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Role::query()
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
    public function store(RoleRequest $request)
    {
        if (!auth()->user()->tokenCan("role:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $sql = Role::create($request->validated() + [
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]);

            $sql->menus()->attach($request->menus, [
                "is_active" => true,
                "created_by" => auth()->id(),
                "created_at" => now(),
                "updated_at" => null
            ]);

            $sql->permissions()->attach($request->permissions, [
                "created_at" => now(),
                "updated_at" => null
            ]);

            DB::commit();

            return new StoreResource($sql);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollback();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Role $role)
    {
        if (!auth()->user()->tokenCan("role:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($role->load(["menus:id", "permissions:id"]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, Role $role)
    {
        if (!auth()->user()->tokenCan("role:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $role->update($request->validated() + [
                'updated_by' => auth()->id(),
            ]);

            $role->menus()->syncWithPivotValues($request->menus, [
                "is_active" => true,
                "created_by" => auth()->id(),
                "updated_by" => auth()->id(),
                "updated_at" => now()
            ]);

            $role->permissions()->syncWithPivotValues($request->permissions, [
                "created_at" => now(),
                "updated_at" => now()
            ]);

            DB::commit();

            return new UpdateResource($role);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollback();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        if (!auth()->user()->tokenCan("role:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $role->delete();

        return new DeleteResource($role);
    }
}
