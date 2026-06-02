<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class LpjRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'trx_id' => ['required', 'numeric', 'exists:type_trxes,id'],
            'payment_method' => ['required', 'string'],
            'pv_id' => ['required', 'numeric', 'exists:payment_vouchers,id'],
            'description' => ['required', 'string'],
            'attachment' => $this->status !== 'REQUEST' ? ['nullable'] : ["nullable", File::types(['pdf'])->max(1024)],
            'details.*.inv_coa_id' => ['required', 'numeric', 'exists:chart_of_accounts,id'],
            'details.*.description' => ['required', 'string'],
            'details.*.item_amount' => ['required', 'numeric'],
            'details.*.pph_id' => ['nullable', 'numeric', 'exists:pphs,id'],
            'details.*.ppn_rate' => ['nullable', 'numeric'],
            'created_by' => ['numeric', 'exists:users,id'],
            'updated_by' => ['nullable', 'numeric', 'exists:users,id'],
        ];
    }
}
