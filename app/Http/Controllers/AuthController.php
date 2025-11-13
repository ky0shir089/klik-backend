<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\SignInRequest;
use App\Http\Requests\SignUpRequest;
use App\Http\Resources\GetResource;
use App\Http\Resources\UpdateResource;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function signUp(SignUpRequest $request)
    {
        $user = User::where('user_id', $request->user_id)->first();

        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'User already exists'
            ], 400);
        }

        User::create($request->validated() + [
            'password' => 12345678,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully'
        ]);
    }

    public function signIn(SignInRequest $request)
    {
        $user = User::where('user_id', $request->user_id)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'User not found'
            ], 400);
        }


        if (Hash::check($request->password, $user->password)) {
            $user->ip_address = $request->ip();
            $user->user_agent = $request->userAgent();
            $user->save();

            $user->tokens()->delete();
            $abilities = $user->id == 1 ? ["*"] : $user->role->permissions->pluck("name")->toArray();
            $access_token = $user->createToken('access_token', $abilities, now()->addDay())->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User logged in successfully',
                'data' => $user->except(["role"]),
                'access_token' => $access_token,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 400);
        }
    }

    public function changePassword(ChangePasswordRequest $request)
    {
        $user = auth()->user();
        $userCurrentPassword = Hash::check($request->current_password, $user->password);

        if (!$userCurrentPassword) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid current password'
            ]);
        }

        $user->update([
            'password' => $request->password_confirmation,
            'change_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }

    public function refreshToken(Request $request)
    {
        $request->user()->tokens()
            ->where("id", "!=", $request->user()->currentAccessToken()->id)
            ->delete();
        $accessToken = $request->user()->createToken('access_token', ["*"], now()->addHour());
        return response()->json([
            'success' => true,
            'message' => 'Token generated',
            'access_token' => $accessToken->plainTextToken,
        ]);
    }

    public function navigation()
    {
        $userRoleId = auth()->user()->role->id;

        $query = Module::query()
            ->with([
                "menus" => function ($q) use ($userRoleId) {
                    $q->select("menus.id", "role_id", "menu_id")
                        ->with("menu:id,name,url,position")
                        ->where("menu_role.is_active", true)
                        ->where("menus.is_active", true)
                        ->where("role_id", $userRoleId)
                        ->orderBy("position", "asc");
                }
            ])
            ->whereRelation("menus", "role_id", $userRoleId)
            ->orderBy("position", "asc")
            ->get();

        return new GetResource($query);
    }
}
