<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255|unique:projects,title',
            'description' => 'required|string|min:50|max:5000',
            'category' => 'required|string|max:100',
            'team_size' => 'required|integer|min:5|max:10',
            'min_team_size' => 'required|integer|min:1|max:' . ($this->team_size ?? 20),
            'max_teams' => 'required|integer|min:1|max:10',
            'difficulty' => 'required|in:beginner,intermediate,advanced,expert',
            'estimated_duration_days' => 'required|integer|min:1|max:365',
            'skills' => 'required|array|min:1',
            'skills.*' => 'exists:skills,id',
            'requirements' => 'nullable|array',
            'requirements.*' => 'string',
            'goals' => 'nullable|array',
            'goals.*' => 'string',
            'resources' => 'nullable|array',
            'resources.*' => 'string|url',
        ];
    }

    public function messages(): array
    {
        return [
    'title.required' => 'Project title is required',
    'title.max' => 'Project title must not exceed 255 characters',
    'title.unique' => 'A project with this title already exists',

    'description.required' => 'Project description is required',
    'description.min' => 'Description must be at least 50 characters',
    'description.max' => 'Description must not exceed 5000 characters',

    'category.required' => 'Project category is required',

    'difficulty.required' => 'Difficulty level is required',
    'difficulty.in' => 'Difficulty level must be one of: beginner, intermediate, advanced, expert',

    'estimated_duration_days.required' => 'Estimated duration is required',
    'estimated_duration_days.integer' => 'Estimated duration must be a number',
    'estimated_duration_days.min' => 'Minimum duration is 1 day',
    'estimated_duration_days.max' => 'Maximum duration is 365 days',

    'max_teams.required' => 'Maximum number of teams is required',
    'max_teams.integer' => 'Maximum number of teams must be a number',
    'max_teams.min' => 'At least 1 team is required',
    'max_teams.max' => 'Maximum allowed is 10 teams',

    'team_size.required' => 'Team size is required',
    'team_size.integer' => 'Team size must be a number',
    'team_size.min' => 'Minimum team size is 3 members',
    'team_size.max' => 'Maximum team size is 20 members',

    'skills.required' => 'Required project skills are mandatory',
    'skills.array' => 'Skills must be provided as an array',
    'skills.min' => 'At least one skill is required',
    'skills.*.exists' => 'This skill does not exist in the database',
];

    }

    protected function prepareForValidation(): void
    {
        if ($this->has('skills') && is_string($this->skills)) {
            $this->merge([
                'skills' => explode(',', $this->skills)
            ]);
        }

        if ($this->has('requirements') && is_string($this->requirements)) {
            $this->merge([
                'requirements' => explode('|', $this->requirements)
            ]);
        }

        if ($this->has('goals') && is_string($this->goals)) {
            $this->merge([
                'goals' => explode('|', $this->goals)
            ]);
        }

        if ($this->has('estimated_duration_days') && is_string($this->estimated_duration_days)) {
            $this->merge([
                'estimated_duration_days' => (int) $this->estimated_duration_days
            ]);
        }

        if ($this->has('max_teams') && is_string($this->max_teams)) {
            $this->merge([
                'max_teams' => (int) $this->max_teams
            ]);
        }

        if ($this->has('team_size') && is_string($this->team_size)) {
            $this->merge([
                'team_size' => (int) $this->team_size
            ]);
        }
    }

    public function attributes(): array
    {
        return [
        'title' => 'Project Title',
        'description' => 'Project Description',
        'category' => 'Category',
        'difficulty' => 'Difficulty Level',
        'estimated_duration_days' => 'Estimated Duration (Days)',
        'max_teams' => 'Maximum Number of Teams',
        'team_size' => 'Team Size',
        'skills' => 'Required Skills',
        ];

    }

    protected function passedValidation(): void
    {
        logger()->info('Project creation validation passed', [
            'user_id' => auth()->id(),
            'project_title' => $this->title
        ]);
    }
}
