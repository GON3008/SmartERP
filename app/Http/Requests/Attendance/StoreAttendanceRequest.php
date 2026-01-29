<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('create.attendances');
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
            'date' => ['required', 'date', 'before_or_equal:today'],
            'check_in' => ['required', 'date_format:H:i:s'],
            'check_out' => ['nullable', 'date_format:H:i:s', 'after:check_in'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Nhân viên là bắt buộc.',
            'employee_id.exists' => 'Nhân viên không tồn tại.',
            'date.required' => 'Ngày là bắt buộc.',
            'date.before_or_equal' => 'Ngày không được sau ngày hôm nay.',
            'check_in.required' => 'Giờ vào là bắt buộc.',
            'check_in.date_format' => 'Giờ vào không đúng định dạng (HH:MM:SS).',
            'check_out.date_format' => 'Giờ ra không đúng định dạng (HH:MM:SS).',
            'check_out.after' => 'Giờ ra phải sau giờ vào.',
        ];
    }
}
