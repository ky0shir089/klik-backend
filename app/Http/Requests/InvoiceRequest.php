<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
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
            'rv_id' => ['nullable', 'integer', 'exists:receive_vouchers,id'],
            'supplier_account_id' => ['required', 'integer', 'exists:supplier_accounts,id'],
            'description' => ['required', 'string'],
            'amount' => ['required', 'integer'],
            'inv_coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
        ];
    }
}
