<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'programmer_id' => 'nullable|exists:programmers,id',
            'status' => 'sometimes|in:todo,in_progress,review,done,cancelled',
            'estimated_hours' => 'sometimes|integer|min:1|max:500',
            'actual_hours' => 'nullable|integer|min:0|max:1000',
            'deadline' => 'sometimes|date|after:today',
            'priority' => 'nullable|integer|min:1|max:10',
            'complexity' => 'nullable|in:low,medium,high,critical',
            'progress_percentage' => 'nullable|integer|min:0|max:100',
            'required_skills' => 'nullable|array',
            'required_skills.*' => 'string',
            'completion_notes' => 'nullable|string|max:2000',
            'quality_score' => 'nullable|integer|min:1|max:10',
            'quality_feedback' => 'nullable|string|max:1000',
            'is_blocked' => 'nullable|boolean',
            'block_reason' => 'nullable|string|required_if:is_blocked,true|max:500',
            'started_at' => 'nullable|date',
            'completed_at' => 'nullable|date',
            'reviewed_at' => 'nullable|date',
            'reviewed_by' => 'nullable|exists:programmers,id',
            'attachments' => 'nullable|array',
        'attachments.*' => 'file|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required',
            'title.max' => 'Task title must not exceed 255 characters',
            'estimated_hours.min' => 'Estimated hours must be at least 1 hour',
            'estimated_hours.max' => 'Estimated hours must not exceed 500 hours',
            'actual_hours.max' => 'Actual hours must not exceed 1000 hours',
            'deadline.after' => 'Deadline must be a future date',
            'programmer_id.exists' => 'The selected programmer does not exist',
            'priority.min' => 'Priority must be between 1 and 10',
            'priority.max' => 'Priority must be between 1 and 10',
            'complexity.in' => 'Complexity must be one of: low, medium, high, critical',
            'progress_percentage.min' => 'Progress percentage must be between 0 and 100',
            'progress_percentage.max' => 'Progress percentage must be between 0 and 100',
            'required_skills.array' => 'Required skills must be an array',
            'quality_score.min' => 'Quality score must be between 1 and 10',
            'quality_score.max' => 'Quality score must be between 1 and 10',
            'block_reason.required_if' => 'Block reason is required when the task is blocked',
            'reviewed_by.exists' => 'The reviewing programmer does not exist',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('status') && $this->status === 'in_progress' && empty($this->started_at)) {
            $this->merge(['started_at' => now()]);
        }

        if ($this->has('status') && $this->status === 'done' && empty($this->completed_at)) {
            $this->merge(['completed_at' => now()]);
        }

        if ($this->has('progress_percentage') && $this->progress_percentage >= 100) {
            $this->merge(['status' => 'review']);
        }
    }
}
