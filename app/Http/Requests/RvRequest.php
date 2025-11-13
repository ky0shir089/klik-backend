<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RvRequest extends FormRequest
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
            'type_trx_id' => ['required', 'integer', 'exists:type_trxes,id'],
            'description' => ['required', 'string'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'coa_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'starting_balance' => ['required', 'integer'],
            'used_balance' => ['integer'],
            'ending_balance' => ['integer'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'created_by' => ['integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
