<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
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
            'account_number' => ['required', 'string'],
            'account_name' => ['required', 'string'],
            'bank_id' => ['required', 'integer', 'exists:banks,id'],
            'coa_id'    => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'is_active' => ['required', 'boolean'],
            'created_by' => ['integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'is_active' => $this->toBoolean($this->is_active),
        ]);
    }

    private function toBoolean($booleable)
    {
        return filter_var($booleable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
