<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('edit.employees');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employeeId = $this->route('employee');
        return [
            'user_id' => [
                'sometimes',
                'required',
                'exists:users,id',
                Rule::unique('employees', 'user_id')->ignore($employeeId)
            ],
            'position_id' => ['sometimes', 'required', 'exists:positions,id'],
            'department_id' => ['sometimes', 'required', 'exists:departments,id'],
            'employee_code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('employees', 'employee_code')->ignore($employeeId)
            ],
            'full_name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'hire_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.unique' => 'User này đã có nhân viên liên kết.',
            'employee_code.unique' => 'Mã nhân viên này đã tồn tại.',
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
