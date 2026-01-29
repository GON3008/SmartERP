<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('create.employees');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'exists:users,id', 'unique:employees,user_id'],
            'position_id' => ['required', 'exists:positions,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'employee_code' => ['required', 'string', 'max:255', 'unique:employees,employee_code'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
            'hire_date' => ['required', 'date', 'before_or_equal:today'],
            'status' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID là bắt buộc.',
            'user_id.exists' => 'User không tồn tại.',
            'user_id.unique' => 'User này đã có nhân viên liên kết.',
            'position_id.required' => 'Chức vụ là bắt buộc.',
            'department_id.required' => 'Phòng ban là bắt buộc.',
            'employee_code.required' => 'Mã nhân viên là bắt buộc.',
            'employee_code.unique' => 'Mã nhân viên này đã tồn tại.',
            'full_name.required' => 'Họ tên là bắt buộc.',
            'hire_date.required' => 'Ngày vào làm là bắt buộc.',
            'hire_date.before_or_equal' => 'Ngày vào làm không được sau ngày hôm nay.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('employee_code')) {
            $this->merge([
                'employee_code' => strtoupper($this->employee_code),
            ]);
        }
    }
}
