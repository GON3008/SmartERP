<?php

namespace App\Http\Requests\Supplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:50',
            'address'        => 'nullable|string|max:500',
            'tax_code'       => 'nullable|string|max:50',
            'bank_account'   => 'nullable|string|max:50',
            'bank_name'      => 'nullable|string|max:255',
            'notes'          => 'nullable|string|max:1000',
            'status'         => 'nullable|in:active,inactive',
        ];
    }
}
