<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PvRequest extends FormRequest
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
            'paid_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'pvs' => ['required', 'array', 'min:1'],
        ];
    }
}
