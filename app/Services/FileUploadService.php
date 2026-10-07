<?php

namespace App\Services;

use App\Models\FileUpload;
use Str;

class FileUploadService
{
    /**
     * Create a new class instance.
     */
    private function normalizeFilename(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Convert to lowercase
        $name = Str::lower($name);

        // Transliterate characters to ASCII
        $name = Str::ascii($name);

        // Replace anything except letters, numbers, spaces, _ and -
        $name = preg_replace('/[^a-z0-9\s_-]/', '', $name);

        // Replace spaces and multiple separators with -
        $name = preg_replace('/[\s_-]+/', '-', $name);

        // Remove leading/trailing -
        $name = trim($name, '-');

        // Fallback if filename becomes empty
        $name = $name ?: 'file';

        return $name . ($extension ? '.' . Str::lower($extension) : '');
    }

    public function handleUpload($file)
    {
        $filename = $this->normalizeFilename($file->getClientOriginalName());
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
