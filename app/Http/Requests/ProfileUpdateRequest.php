<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => [
                'sometimes',
                'string',
                'lowercase',
                'email',
                'max:100',
                Rule::unique(User::class, 'email')->ignore($this->user()->user_id, 'user_id'),
            ],
            'contact_no' => [
                'nullable',
                'regex:/^(09|\+639)\d{9}$/',
            ],
        ];
    }
}
