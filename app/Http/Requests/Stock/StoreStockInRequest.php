<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('create.stock');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'import_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Sản phẩm là bắt buộc.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'warehouse_id.required' => 'Kho là bắt buộc.',
            'warehouse_id.exists' => 'Kho không tồn tại.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'import_date.required' => 'Ngày nhập là bắt buộc.',
            'import_date.before_or_equal' => 'Ngày nhập không được sau ngày hôm nay.',
        ];
    }
}
