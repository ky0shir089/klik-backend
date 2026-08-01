<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaidAttachmentRequest;
use App\Http\Resources\StoreResource;
use App\Models\PaidAttachment;
use App\Services\FileUploadService;
use Illuminate\Http\Request;

class PaidAttachmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PaidAttachmentRequest $request)
    {
        if (!auth()->user()->tokenCan("invoice:add")) {
            return response()->json([
                "success" => false,
                "message" => "Unauthorized",
            ], 403);
        }

        if ($request->hasFile('attachment')) {
            $file = (new FileUploadService)->handleUpload($request->file('attachment'));
        }

        $sql = PaidAttachment::create($request->safe()->except(["attachment"]) + [
            'file_upload_id' => $file->id ?? null,
            'created_by' => auth()->id(),
            'updated_by' => null,
        ]);

        return new StoreResource($sql);
    }

    /**
     * Display the specified resource.
     */
    public function show(PaidAttachment $paidAttachment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaidAttachment $paidAttachment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaidAttachment $paidAttachment)
    {
        //
    }
}
