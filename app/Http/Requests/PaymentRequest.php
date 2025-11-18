<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
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
            'payment_date' => ['required', 'date'],
            'branch_id' => ['required', 'integer'],
            'branch_name' => ['required', 'string'],
            'customer_id' => ['required', 'integer', 'exists:customers,klik_bidder_id'],
            'units' => ['required', 'array', 'min:1'],
            'rvs' => ['required', 'array', 'min:1'],
            'created_by' => ['integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
