<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
        return [
            'sku' => [
                'required',
                'string',
                'max: 255',
                'unique:products,sku',
            ],
            'name' => [
                'required',
                'string',
                'max: 255',
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
                'required',
                'string',
                'max:50',
                Rule::in([
                    'pcs',
                    'kg',
                    'liter',
                    'box',
                    'meter',
                    'dozen',
                ]),
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'min_stock' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'SKU là bắt buộc',
            'sku.unique' => 'SKU đã tồn tại',
            'name.required' => 'Tên sản phẩm là bắt buộc',
            'category.in' => 'Danh mục không hợp lệ',
            'category.required' => 'Danh mục là bắt buộc',
            'unit.required' => 'Đơn vị là bắt buộc',
            'unit.in' => 'Đơn vị không hợp lệ',
            'price.required' => 'Giá là bắt buộc',
            'price.numeric' => 'Giá phải là số',
            'price.min' => 'Giá phải lớn hơn 0',
            'min_stock.required' => 'Tồn kho tối thiểu là bắt buộc',
            'min_stock.integer' => 'Tồn kho tối thiểu phải là số nguyên',
            'min_stock.min' => 'Tồn kho tối thiểu phải lớn hơn 0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'sku' => 'mã SKU',
            'name'=> 'tên sản phẩm',
            'category' => 'danh mục',
            'unit' => 'đơn vị',
            'price' => 'giá',
            'min_stock' => 'tồn kho tối thiểu',
        ];
    }

    /**
     * prepare the data for validation
     * @return void
     * được gọi khi validation chạy
     */
    protected function prepareForValidation(): void
    {
        // tự động uppercase SKU
        if($this->has('sku')) {
            $this->merge([
                'sku' => strtoupper($this->sku),
            ]);
        }

        // trim whitespace cho name
        if($this->has('name')) {
            $this->merge([
                'name' => trim($this->name),
            ]);
        }
    }

    /**
     * Handle a passed validation attempt.
     * được gọi sau khi validation pass
     * @return void
     */
    // protected function passedValidation()
    // {
    //     return parent::passedValidation();
    // }
}
