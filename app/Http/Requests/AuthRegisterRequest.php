<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthRegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['nullable', 'string', 'max:20', 'unique:users,mobile'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب.',
            'name.max' => 'الاسم يجب ألا يتجاوز 255 حرفاً.',
            'email.required' => 'البريد الإلكتروني مطلوب.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'email.unique' => 'البريد الإلكتروني مسجل مسبقاً.',
            'mobile.unique' => 'رقم الموبايل مسجل مسبقاً.',
            'password.min' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل.',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
