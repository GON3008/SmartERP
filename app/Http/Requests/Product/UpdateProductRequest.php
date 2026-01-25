<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // lấy product id từ route parameter
        $productId = $this->route('product');
        return [
            'sku' => [
                'sometimes', //chỉ validate nếu có trong request
                'required',
                'string',
                'max: 255',
                // unique nhưng ignored product hiện tại
                Rule::unique('products', 'sku')->ignore($productId),
            ],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max: 255',
            ],
            'category' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::in([
                    'Electronics',
                    'Furniture',
                    'Food & Beverage',
                    'Textiles',
                    'Raw Materials',
                    'Packaging',
                ]),
            ],
            'unit' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::in(['pcs', 'kg', 'liter', 'box', 'meter', 'dozen']),
            ],
            'price' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'min_stock' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'SKU là bắt buộc.',
            'sku.unique' => 'SKU này đã tồn tại trong hệ thống.',
            'name.required' => 'Tên sản phẩm là bắt buộc.',
            'category.in' => 'Danh mục không hợp lệ.',
            'unit.required' => 'Đơn vị tính là bắt buộc.',
            'unit.in' => 'Đơn vị tính không hợp lệ.',
            'price.required' => 'Giá sản phẩm là bắt buộc.',
            'price.numeric' => 'Giá sản phẩm phải là số.',
            'price.min' => 'Giá sản phẩm không được nhỏ hơn 0.',
            'min_stock.required' => 'Tồn kho tối thiểu là bắt buộc.',
            'min_stock.integer' => 'Tồn kho tối thiểu phải là số nguyên.',
            'min_stock.min' => 'Tồn kho tối thiểu không được nhỏ hơn 0.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'sku' => 'mã SKU',
            'name' => 'tên sản phẩm',
            'category' => 'danh mục',
            'unit' => 'đơn vị tính',
            'price' => 'giá',
            'min_stock' => 'tồn kho tối thiểu',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('sku')) {
            $this->merge([
                'sku' => strtoupper($this->sku),
            ]);
        }

        if ($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }
}
