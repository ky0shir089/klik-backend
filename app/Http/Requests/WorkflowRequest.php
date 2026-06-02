<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WorkflowRequest extends FormRequest
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
            "name" => "required|string|max:255",
            "type_trx" => "required|array|min:1",
            "min_amount" => "required|numeric|min:0",
            "max_amount" => "nullable|numeric|min:0",
            "is_active" => "required|boolean",
            "details" => "required|array|min:1",
            "details.*.user_id" => "required|exists:users,id",
            "details.*.sequence" => "required|integer|min:1",
        ];
    }
}
