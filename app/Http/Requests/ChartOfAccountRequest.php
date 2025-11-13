<?php

namespace App\Http\Requests;

use App\Enums\CoaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChartOfAccountRequest extends FormRequest
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
            'code' => ['required', 'string', 'min:7',],
            'description' => ['required', 'string'],
            'type' => ['required', 'string', Rule::enum(CoaType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'created_by' => ['integer', 'exists:users,id'],
            'updated_by' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
