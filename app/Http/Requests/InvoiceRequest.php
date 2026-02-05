<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

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
            'date' => ['required', 'date'],
            'trx_id' => ['required', 'numeric', 'exists:type_trxes,id'],
            'supplier_id' => ['required', 'numeric', 'exists:suppliers,id'],
            'payment_method' => ['required', 'string'],
            'supplier_account_id' => ['nullable', 'numeric', 'exists:supplier_accounts,id'],
            'description' => ['required', 'string'],
            'attachment' => $this->status !== 'REQUEST'
                ? ['nullable']
                : ['nullable', File::types(['pdf'])->max(1024)],
            'created_by' => ['numeric', 'exists:users,id'],
            'updated_by' => ['nullable', 'numeric', 'exists:users,id'],
        ];
    }
}
