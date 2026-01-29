<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionOrderRequest extends FormRequest
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
            'order_code' => ['required', 'string', 'max:255', 'unique:production_orders,order_code'],
            'product_id' => ['required', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_code.required' => 'Mã lệnh sản xuất là bắt buộc.',
            'order_code.unique' => 'Mã lệnh sản xuất đã tồn tại.',
            'product_id.required' => 'Sản phẩm là bắt buộc.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'quantity.required' => 'Số lượng là bắt buộc.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'start_date.after_or_equal' => 'Ngày bắt đầu không được trước ngày hôm nay.',
            'end_date.after' => 'Ngày kết thúc phải sau ngày bắt đầu.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('order_code')) {
            $this->merge([
                'order_code' => strtoupper($this->order_code),
            ]);
        }

        // Set default status
        if (!$this->has('status')) {
            $this->merge(['status' => 'pending']);
        }
    }

    /**
     * Additional custom validation
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if product has BOM (Bill of Materials)
            if ($this->product_id) {
                $hasBOM = \App\Models\BillOfMaterial::where('product_id', $this->product_id)->exists();

                if (!$hasBOM) {
                    $validator->errors()->add(
                        'product_id',
                        'Sản phẩm này chưa có công thức sản xuất (BOM).'
                    );
                }
            }
        });
    }
}
