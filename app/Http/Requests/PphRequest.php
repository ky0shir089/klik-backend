<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PphRequest extends FormRequest
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
            'name' => ['required', 'string'],
            'rate' => ['required', 'numeric'],
            'coa_id' => ['required', 'numeric', 'exists:chart_of_accounts,id'],
            'created_by' => ['numeric', 'exists:users,id'],
            'updated_by' => ['nullable', 'numeric', 'exists:users,id'],
        ];
    }
}
