<?php

namespace App\Http\Controllers;

use App\Http\Requests\MenuRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Menu;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("menu:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Menu::query()
            ->with("module")
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->orderBy("module_id", "asc")
            ->orderBy("position", "asc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(MenuRequest $request)
    {
        if (!auth()->user()->tokenCan("menu:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $sql = Menu::create($request->validated() + [
                'created_by' => auth()->id(),
                'updated_at' => null,
            ]);

            if (isset($request->slug)) {
                $sql->permissions()->createMany([
                    [
                        'name' => $sql->slug . ":browse",
                        'created_at' => now(),
                    ],
                    [
                        'name' => $sql->slug . ":read",
                        'created_at' => now(),
                    ],
                    [
                        'name' => $sql->slug . ":edit",
                        'created_at' => now(),
                    ],
                    [
                        'name' => $sql->slug . ":add",
                        'created_at' => now(),
                    ],
                    [
                        'name' => $sql->slug . ":delete",
                        'created_at' => now(),
                    ],
                ]);
            }

            DB::commit();

            return new StoreResource($sql);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Menu $menu)
    {
        if (!auth()->user()->tokenCan("menu:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($menu);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MenuRequest $request, Menu $menu)
    {
        if (!auth()->user()->tokenCan("menu:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::beginTransaction();

        try {
            $menu->update($request->validated() + [
                'updated_by' => auth()->id(),
            ]);

            foreach ($menu->permissions as $permission) {
                $sql = Permission::find($permission->id);
                $sql->name = $menu->slug . ":" . explode(":", $sql->name)[1];
                $sql->save();
            }

            DB::commit();

            return new UpdateResource($menu);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Menu $menu)
    {
        if (!auth()->user()->tokenCan("menu:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $menu->delete();

        return new DeleteResource($menu);
    }
}
