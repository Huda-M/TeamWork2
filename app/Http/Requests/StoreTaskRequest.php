<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'programmer_id' => 'nullable|exists:programmers,id',
            'status' => 'nullable|in:todo,in_progress,review,done,cancelled',
            'estimated_hours' => 'required|integer|min:1|max:500',
            'deadline' => 'required|date|after:today',
            'priority' => 'nullable|integer|min:1|max:10',
            'complexity' => 'nullable|in:low,medium,high,critical',
            'required_skills' => 'nullable|array',
            'required_skills.*' => 'string',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required',
            'title.max' => 'Task title must not exceed 255 characters',
            'estimated_hours.required' => 'Estimated hours are required',
            'estimated_hours.min' => 'Estimated hours must be at least 1 hour',
            'estimated_hours.max' => 'Estimated hours must not exceed 500 hours',
            'deadline.required' => 'Deadline is required',
            'deadline.after' => 'Deadline must be a future date',
            'programmer_id.exists' => 'The selected programmer does not exist',
            'priority.min' => 'Priority must be between 1 and 10',
            'priority.max' => 'Priority must be between 1 and 10',
            'complexity.in' => 'Complexity must be one of: low, medium, high, critical',
            'required_skills.array' => 'Required skills must be an array',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->status ?? 'todo',
            'priority' => $this->priority ?? 5,
            'complexity' => $this->complexity ?? 'medium',
        ]);
    }
}
