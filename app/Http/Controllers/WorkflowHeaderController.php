<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkflowRequest;
use App\Http\Resources\GetResource;
use App\Models\WorkflowHeader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkflowHeaderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->tokenCan("workflow:browse")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $query = WorkflowHeader::query()
            ->when($request->search, function ($query, $search) {
                $query->where("name", "ilike", "%$search%");
            })
            ->oldest("id")
            ->paginate($request->size);

        return new GetResource($query);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(WorkflowRequest $request)
    {
        if (!auth()->user()->tokenCan("workflow:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request) {
            $authId = auth()->id();

            $sql = WorkflowHeader::create($request->safe()->except(["details"]) + [
                'created_by' => $authId,
                'updated_at' => null,
            ]);

            foreach ($request->details as $detail) {
                $details[] = [
                    "user_id" => $detail["user_id"],
                    "sequence" => $detail["sequence"],
                    "created_by" => $authId,
                    "updated_at" => null,
                ];
            }

            $sql->details()->createMany($details);
        });

        return response()->json([
            "success" => true,
            "message" => "Data Saved",
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(WorkflowHeader $workflow)
    {
        if (!auth()->user()->tokenCan("workflow:read")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        return new GetResource($workflow->load([
            "details",
            "details.user",
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(WorkflowRequest $request, WorkflowHeader $workflow)
    {
        if (!auth()->user()->tokenCan("workflow:edit")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        DB::transaction(function () use ($request, $workflow) {
            $authId = auth()->id();

            $workflow->update($request->safe()->except(["details"]) + [
                'updated_by' => $authId,
            ]);

            $workflow->details()->delete();

            foreach ($request->details as $detail) {
                $details[] = [
                    "user_id" => $detail["user_id"],
                    "sequence" => $detail["sequence"],
                    "created_by" => $authId,
                    "updated_at" => null,
                ];
            }

            $workflow->details()->createMany($details);
        });

        return response()->json([
            "success" => true,
            "message" => "Data Updated",
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WorkflowHeader $workflow)
    {
        if (!auth()->user()->tokenCan("workflow:delete")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        $workflow->delete();

        return new DeleteResource($workflow);
    }
}
