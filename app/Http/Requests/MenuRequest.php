<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuRequest extends FormRequest
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
            'url' => ['required', 'string'],
            'position' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
            'slug' => ['nullable', 'string'],
            'module_id' => ['required', 'integer', 'exists:modules,id'],
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
