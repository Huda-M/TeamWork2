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
            'admin_action' => 'required|in:approved,rejected,warning_given',
            'admin_notes' => 'nullable|string|max:500',
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
