<?php

namespace App\Http\Controllers;

use App\Http\Resources\GetResource;
use App\Http\Resources\StoreResource;
use App\Models\GL;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GeneralLedgerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("journal-input:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = GL::select("gl_no", "date", "description")
            ->where("type", "JV")
            ->when($request->search, function ($query, $search) {
                $query->where("gl_no", "ilike", "%$search%");
            })
            ->groupBy("gl_no", "date", "description")
            ->orderBy("gl_no", "asc")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->tokenCan("journal-input:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        if ($request->sum_debit != $request->sum_credit) {
            return response()->json([
                "success" => false,
                "message" => "Total debit and credit must be equal",
            ], 422);
        }

        DB::beginTransaction();

        try {
            $year = Carbon::parse($request->date)->format('y');
            $seq = GL::select("gl_no")
                ->where('gl_no', 'like', 'JV%' . $year . '%')
                ->groupBy("gl_no")
                ->get()
                ->count();

            $gl_no = 'JV' . $year . Str::padLeft($seq + 1, 5, '0');

            $authId = auth()->id();

            $rows = [];

            foreach ($request->details as $row) {
                $rows[] = [
                    "gl_no" => $gl_no,
                    "date" => $request->date,
                    "type" => "JV",
                    "description" => $request->description,
                    "coa_id" => $row["coa_id"],
                    "debit" => $row["debit"],
                    "credit" => $row["credit"],
                    "created_by" => $authId,
                    "created_at" => now(),
                ];
            }

            GL::insert($rows);

            DB::commit();
        } catch (\Throwable $th) {
            info($th->getMessage());

            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => $th->getMessage(),
            ], 500);
        }

        return new StoreResource($rows);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request)
    {
        if (!auth()->user()->tokenCan("journal-input:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = GL::select("gl_no", "date", "description")
            ->where("gl_no", $request->gl_no)
            ->groupBy("gl_no", "date", "description")
            ->first();

        $details = GL::query()
            ->with("user")
            ->where("gl_no", $request->gl_no)
            ->get();

        if ($query) {
            $query->details = $details;
        }

        return new GetResource($query);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
