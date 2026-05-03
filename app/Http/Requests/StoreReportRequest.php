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
        'report_type' => 'required|in:harassment,inappropriate_content,spam,fake_account,cheating,offensive_behavior,other',
        'description' => 'required|string|min:10|max:1000',
        'evidence' => 'nullable|array', // or json
    ];
}

    public function messages(): array
    {
        return [
            'target_user_id.required' => 'Please specify the user you want to report',
            'target_user_id.exists' => 'The reported user does not exist',
            'target_user_id.not_in' => 'You cannot report yourself',
            'report_type.required' => 'Please select a report type',
            'report_type.in' => 'Invalid report type selected',
            'description.required' => 'Please provide a description',
            'description.min' => 'Description must be at least 10 characters',
            'description.max' => 'Description must not exceed 1000 characters',
            'evidence.array' => 'Evidence must be an array',
            'evidence.*.url' => 'Each evidence must be a valid URL',
        ];
    }
}
