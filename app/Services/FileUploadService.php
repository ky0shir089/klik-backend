<?php

namespace App\Services;

use App\Models\FileUpload;

class FileUploadService
{
    /**
     * Create a new class instance.
     */
    public function handleUpload($file)
    {
        $filename = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $path = $file->storeAs("file-uploads", $filename, 'public');

        $upload = new FileUpload();
        $upload->filename = $filename;
        $upload->path = $path;
        $upload->extension = $extension;
        $upload->created_by = auth()->id();
        $upload->updated_at = null;
        $upload->save();

        return $upload;
    }
}
