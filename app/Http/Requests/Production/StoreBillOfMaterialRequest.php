<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreBillOfMaterialRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('create.production');
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
            'material_id' => ['required', 'exists:products,id', 'different:product_id'],
            'quantity_required' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Sản phẩm là bắt buộc.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'material_id.required' => 'Nguyên liệu là bắt buộc.',
            'material_id.exists' => 'Nguyên liệu không tồn tại.',
            'material_id.different' => 'Nguyên liệu không được trùng với sản phẩm.',
            'quantity_required.required' => 'Số lượng cần thiết là bắt buộc.',
            'quantity_required.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }

    /**
     * Check for duplicate BOM entry
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->product_id && $this->material_id) {
                $exists = \App\Models\BillOfMaterial::where('product_id', $this->product_id)
                    ->where('material_id', $this->material_id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'material_id',
                        'Nguyên liệu này đã có trong công thức sản xuất.'
                    );
                }
            }
        });
    }
}
