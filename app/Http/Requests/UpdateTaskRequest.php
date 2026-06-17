<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // سنتحقق من الصلاحية في الكونترولر
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:todo,active,done',
            'deadline' => 'nullable|date',
            'priority' => 'nullable|string|in:low,medium,high',
            'git_link' => 'nullable|url',
            'tags' => 'nullable|array',
            'programmer_id' => 'nullable|exists:programmers,id', // إعادة تعيين المبرمج
        ];
    }
}
