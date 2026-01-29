<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name'
            ],
            'description' => [
                'nullable',
                'string',
                'max:255'
            ],
            'permission_ids' => ['array'],
            'permissions.*' => [
                'exists:permissions,id'
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */

    public function messages()
    {
        return [
            'name.required' => 'Tên vai trò là bắt buộc',
            'name.unique' => 'Tên vai trò đã tồn tại',
            'permission_ids.*.exists' => 'Quyền không tồn tại.',
        ];
    }
}
