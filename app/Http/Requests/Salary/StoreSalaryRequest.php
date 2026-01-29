<?php

namespace App\Http\Requests\Salary;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('create.salaries');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'base_salary' => ['required', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Nhân viên là bắt buộc.',
            'base_salary.required' => 'Lương cơ bản là bắt buộc.',
            'base_salary.min' => 'Lương cơ bản không được âm.',
            'month.required' => 'Tháng là bắt buộc.',
            'month.between' => 'Tháng phải từ 1 đến 12.',
            'year.required' => 'Năm là bắt buộc.',
        ];
    }

    protected function passedValidation(): void
    {
        // Auto calculate total salary
        $baseSalary = $this->base_salary ?? 0;
        $allowance = $this->allowance ?? 0;
        $deduction = $this->deduction ?? 0;

        $this->merge([
            'total_salary' => $baseSalary + $allowance - $deduction,
        ]);
    }
}
