<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockOutRequest extends FormRequest
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
            'export_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => [
                'nullable',
                'string',
                Rule::in(['Sale', 'Production', 'Damaged', 'Return', 'Transfer', 'Sample'])
            ],
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
            'export_date.required' => 'Ngày xuất là bắt buộc.',
            'export_date.before_or_equal' => 'Ngày xuất không được sau ngày hôm nay.',
            'reason.in' => 'Lý do xuất kho không hợp lệ.',
        ];
    }

    /**
     * Additional custom validation
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if warehouse has enough stock
            if ($this->product_id && $this->warehouse_id && $this->quantity) {
                $inventory = \App\Models\Inventory::where('product_id', $this->product_id)
                    ->where('warehouse_id', $this->warehouse_id)
                    ->first();

                if (!$inventory || $inventory->quantity < $this->quantity) {
                    $validator->errors()->add(
                        'quantity',
                        'Kho không đủ hàng. Tồn kho hiện tại: ' . ($inventory->quantity ?? 0)
                    );
                }
            }
        });
    }
}
