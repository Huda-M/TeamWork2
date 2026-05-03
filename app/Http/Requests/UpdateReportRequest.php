<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
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
            'admin_action.required' => 'Please select an action',
            'admin_action.in' => 'Invalid action selected',
            'admin_notes.max' => 'Notes must not exceed 500 characters',
        ];
    }
}
