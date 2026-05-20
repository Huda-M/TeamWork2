<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
{
    return [
        'target_user_id' => 'required|exists:users,id',
        'description' => 'required|string|min:10|max:1000',
    ];
}

    public function messages(): array
    {
        return [
            'target_user_id.required' => 'Please specify the user you want to report',
            'target_user_id.exists' => 'The reported user does not exist',
            'target_user_id.not_in' => 'You cannot report yourself',
            'description.required' => 'Please provide a description',
            'description.min' => 'Description must be at least 10 characters',
            'description.max' => 'Description must not exceed 1000 characters',
        ];
    }
}
