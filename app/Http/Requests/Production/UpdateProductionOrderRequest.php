<?php

namespace App\Http\Requests\Production;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('edit.production');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $productionOrderId = $this->route('production_order');
        return [
            'order_code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('production_orders', 'order_code')->ignore($productionOrderId)
            ],
            'product_id' => ['sometimes', 'required', 'exists:products,id'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'status' => ['sometimes', 'nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'end_date' => ['sometimes', 'nullable', 'date', 'after:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_code.unique' => 'Mã lệnh sản xuất đã tồn tại.',
            'product_id.exists' => 'Sản phẩm không tồn tại.',
            'quantity.min' => 'Số lượng phải lớn hơn 0.',
            'status.in' => 'Trạng thái không hợp lệ.',
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
    }

    /**
     * Additional validation for status changes
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('status')) {
                $productionOrder = \App\Models\ProductionOrder::find($this->route('production_order'));

                // Cannot change status from completed
                if ($productionOrder && $productionOrder->status === 'completed' && $this->status !== 'completed') {
                    $validator->errors()->add(
                        'status',
                        'Không thể thay đổi trạng thái của lệnh sản xuất đã hoàn thành.'
                    );
                }

                // Must have start_date when status is in_progress
                if ($this->status === 'in_progress' && !$this->start_date && !$productionOrder->start_date) {
                    $validator->errors()->add(
                        'start_date',
                        'Phải có ngày bắt đầu khi chuyển sang trạng thái đang sản xuất.'
                    );
                }

                // Must have end_date when status is completed
                if ($this->status === 'completed' && !$this->end_date) {
                    $validator->errors()->add(
                        'end_date',
                        'Phải có ngày kết thúc khi hoàn thành sản xuất.'
                    );
                }
            }
        });
    }
}
