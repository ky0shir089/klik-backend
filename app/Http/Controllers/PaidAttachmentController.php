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

        $authId = auth()->id();
        $files = [];

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $attachment) {
                $file = (new FileUploadService)->handleUpload($attachment);
                $files[] = [
                    'file_upload_id' => $file['id'],
                    'invoice_id' => $request->invoice_id,
                    'created_by' => $authId,
                    'created_at' => now(),
                ];
            }
        }

        if (empty($files)) {
            return response()->json([
                "success" => false,
                "message" => "No file uploaded",
            ], 400);
        }

        PaidAttachment::insert($files);

        return response()->json([
            "success" => true,
            "message" => "Attachments uploaded successfully",
        ], 201);
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
