<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthLoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'mobile' => ['nullable', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            if (!$this->filled('email') && !$this->filled('mobile')) {
                $v->errors()->add('login', 'يجب إدخال البريد الإلكتروني أو رقم الموبايل.');
            }
        });
    }

    public function authorize(): bool
    {
        return true;
    }
}
