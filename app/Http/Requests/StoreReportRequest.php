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
        'target_programmer_id' => 'required|integer|exists:programmers,id',
        'description' => 'required|string|min:10|max:1000',
    ];
}

    public function messages(): array
    {
        return [
            'target_programmer_id.required' => 'Please specify the programmer you want to report',
            'target_programmer_id.exists' => 'The reported programmer does not exist',
            'target_programmer_id.not_in' => 'You cannot report yourself',
            'description.required' => 'Please provide a description',
            'description.min' => 'Description must be at least 10 characters',
            'description.max' => 'Description must not exceed 1000 characters',
        ];
    }
}
