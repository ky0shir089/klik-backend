<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\SetupUserRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("user:browse")) {
            return response()->json([
                "message" => "Unauthorized",
                "status" => false
            ], 403);
        }

        // DB::listen(fn($query) => info($query->toRawSql()));

        $query = User::query()
            ->with(["role"])
            ->when($request->search, function ($query, $search) {
                return $query->whereAny([
                    "user_id",
                    "name"
                ], "ilike", "%$search%");
            })
            ->orderBy("id", "asc")
            ->paginate($request->show);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateUserRequest $request)
    {
        if (!auth()->user()->tokenCan("user:create")) {
            return response()->json([
                "message" => "Unauthorized",
                "status" => false
            ], 403);
        }

        $user = User::where('user_id', $request->user_id)->first();

        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists'
            ], 400);
        }

        DB::beginTransaction();

        try {
            $sql = User::create($request->safe()->only(["user_id", "name", "change_password"]) + [
                'password' => 12345678,
            ]);

            $sql->roles()->syncWithPivotValues($request->safe()->only(["role_id"]), [
                "created_by" => auth()->id(),
                "created_at" => now(),
                "updated_at" => null
            ]);

            DB::commit();

            return new StoreResource($sql);
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
     * Display the specified resource.
     */
    public function show(User $user)
    {
        if (!auth()->user()->tokenCan("user:read")) {
            return response()->json([
                "message" => "Unauthorized",
                "status" => false
            ], 403);
        }

        return new GetResource($user->load("role"));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SetupUserRequest $request, User $user)
    {
        if (!auth()->user()->tokenCan("user:edit")) {
            return response()->json([
                "message" => "Unauthorized",
                "status" => false
            ], 403);
        }

        DB::beginTransaction();

        try {
            if ($request->change_password === true) {
                $user->update($request->safe()->only(["change_password"]) + [
                    "password" => 12345678,
                ]);
            }

            $user->roles()->syncWithPivotValues($request->safe()->only(["role_id"]), [
                "created_by" => auth()->id(),
                "created_at" => now(),
                "updated_at" => null
            ]);

            DB::commit();

            return new UpdateResource($user);
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "status" => false,
                "message" => $th->getMessage(),
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        if (!auth()->user()->tokenCan("user:delete")) {
            return response()->json([
                "message" => "Unauthorized",
                "status" => false
            ], 403);
        }

        $user->delete();
        
        return new DeleteResource($user);
    }
}
