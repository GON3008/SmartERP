<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
        // return $this->user()->hasPermission('edit.roles');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roleId = $this->route('role');
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:roles,name',
                Rule::unique('roles', 'name')->ignore($roleId),
            ],
            'description' => [
                'nullable',
                'string',
                'max:255'
            ],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['exists:permissions,id'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */

    public function messages(): array
    {
        return [
            'name.required' => 'Tên vai trò là bắt buộc',
            'name.unique' => 'Tên vai trò đã tồn tại',
            'permission_ids.*.exists' => 'Quyền không tồn tại.',
        ];
    }

    public function attributes()
    {
        return parent::attributes();
    }
}
