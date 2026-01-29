<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('create.orders');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'order_code' => ['required', 'string', 'max:255', 'unique:orders,order_code'],
            'order_date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:pending,processing,completed,cancelled'],

            // Order Items validation
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Khách hàng là bắt buộc.',
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'order_code.required' => 'Mã đơn hàng là bắt buộc.',
            'order_code.unique' => 'Mã đơn hàng đã tồn tại.',
            'order_date.required' => 'Ngày đặt hàng là bắt buộc.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'items.required' => 'Đơn hàng phải có ít nhất 1 sản phẩm.',
            'items.*.product_id.required' => 'Sản phẩm là bắt buộc.',
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.quantity.required' => 'Số lượng là bắt buộc.',
            'items.*.quantity.min' => 'Số lượng phải lớn hơn 0.',
            'items.*.price.required' => 'Giá là bắt buộc.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('order_code')) {
            $this->merge([
                'order_code' => strtoupper($this->order_code),
            ]);
        }
    }

    protected function passedValidation(): void
    {
        // Calculate total amount
        $totalAmount = 0;
        if ($this->has('items')) {
            foreach ($this->items as $item) {
                $totalAmount += ($item['quantity'] * $item['price']);
            }
        }

        $this->merge([
            'total_amount' => $totalAmount,
        ]);
    }
}
