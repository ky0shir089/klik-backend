<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ByadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'branch' => ['required', 'string'],
            'description' => ['required', 'string'],
            'attachment' => $this->status !== 'NEW' ? ['nullable'] : ["nullable", File::types(['pdf'])->max(1024)],
            'status' => ['required', 'string'],
        ];
    }
}
