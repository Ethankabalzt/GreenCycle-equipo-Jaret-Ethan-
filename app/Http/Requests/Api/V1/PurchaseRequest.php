<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige auth:sanctum
    }

    public function rules(): array
    {
        return [
            'shop_item_id' => ['required', 'integer', 'exists:shop_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'shop_item_id.exists' => 'El ítem de la tienda no existe.',
            'quantity.min' => 'La cantidad mínima es 1.',
            'quantity.max' => 'La cantidad máxima por compra es 10.',
        ];
    }
}
