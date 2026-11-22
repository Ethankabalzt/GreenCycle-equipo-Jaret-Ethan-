<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UseInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige auth:sanctum
    }

    public function rules(): array
    {
        return [
            'tree_id' => ['required', 'integer', 'exists:trees,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'tree_id.exists' => 'El árbol indicado no existe.',
        ];
    }
}
