<?php

namespace App\Http\Controllers;

use App\Http\Requests\BankRequest;
use App\Http\Resources\DeleteResource;
use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Http\Resources\UpdateResource;
use App\Models\Bank;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class BankController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("bank:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = Bank::query()
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
    public function store(BankRequest $request)
    {
        if (!auth()->user()->tokenCan("bank:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        if ($request->hasFile('logo')) {
            $file = (new FileUploadService)->handleUpload($request->logo);
        }

        $sql = Bank::create($request->validated() + [
            'logo' => $file->path ?? null,
            'created_by' => auth()->id(),
            'updated_at' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(Bank $bank)
    {
        if (!auth()->user()->tokenCan("bank:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($bank);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BankRequest $request, Bank $bank)
    {
        if (!auth()->user()->tokenCan("bank:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        if ($request->hasFile('logo')) {
            $file = (new FileUploadService)->handleUpload($request->logo);
        }

        $bank->update($request->validated() + [
            'logo' => $file->path ?? null,
            'updated_by' => auth()->id(),
        ]);

        return new UpdateResource($bank);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bank $bank)
    {
        if (!auth()->user()->tokenCan("bank:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $bank->delete();

        return new DeleteResource($bank);
    }
}
