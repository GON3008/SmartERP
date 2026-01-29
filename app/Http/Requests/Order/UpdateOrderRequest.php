<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('edit.orders');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $orderId = $this->route('order');
        return [
            'customer_id' => ['sometimes', 'required', 'exists:customers,id'],
            'order_code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('orders', 'order_code')->ignore($orderId)
            ],
            'order_date' => ['sometimes', 'required', 'date'],
            'status' => ['sometimes', 'nullable', 'string', 'in:pending,processing,completed,cancelled'],

            // Order Items validation
            'items' => ['sometimes', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.exists' => 'Khách hàng không tồn tại.',
            'order_code.unique' => 'Mã đơn hàng đã tồn tại.',
            'status.in' => 'Trạng thái không hợp lệ.',
            'items.*.product_id.exists' => 'Sản phẩm không tồn tại.',
            'items.*.quantity.min' => 'Số lượng phải lớn hơn 0.',
        ];
    }

    protected function passedValidation(): void
    {
        // Recalculate total amount if items updated
        if ($this->has('items')) {
            $totalAmount = 0;
            foreach ($this->items as $item) {
                $totalAmount += ($item['quantity'] * $item['price']);
            }
            $this->merge(['total_amount' => $totalAmount]);
        }
    }
}
