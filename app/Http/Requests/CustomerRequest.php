<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
            'klik_bidder_id' => ['required', 'integer'],
            'ktp' => ['required', 'string', 'min:16'],
            'name' => ['required', 'string'],
            'branch_id' => ['required', 'integer'],
            'branch_name' => ['required', 'string'],
            'lelang' => ['required', 'array'],
            'created_by' => ['integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id']
        ];
    }
}
