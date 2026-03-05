<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!auth()->check() || auth()->user()->role !== 'admin') {
            return false;
        }

        $project = $this->route('project');
        if (!$project) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {
        $projectId = $this->route('project') ? $this->route('project')->id : null;

        return [
            'title' => 'sometimes|string|max:255|unique:projects,title,' . $projectId,
            'description' => 'sometimes|string|min:50|max:5000',
            'category' => 'sometimes|string|max:100',
            'difficulty' => 'sometimes|in:beginner,intermediate,advanced,expert',
            'estimated_duration_days' => 'sometimes|integer|min:1|max:365',
            'max_teams' => 'sometimes|integer|min:1|max:10',
            'team_size' => 'sometimes|integer|min:3|max:20',
            'skills' => 'sometimes|array|min:1',
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
    'title.unique' => 'A project with this title already exists',
    'title.max' => 'Project title must not exceed 255 characters',

    'description.min' => 'Description must be at least 50 characters',
    'description.max' => 'Description must not exceed 5000 characters',

    'difficulty.in' => 'Difficulty level must be one of: beginner, intermediate, advanced, expert',

    'estimated_duration_days.integer' => 'Estimated duration must be a number',
    'estimated_duration_days.min' => 'Minimum duration is 1 day',
    'estimated_duration_days.max' => 'Maximum duration is 365 days',

    'max_teams.integer' => 'Maximum number of teams must be a number',
    'max_teams.min' => 'At least 1 team is required',
    'max_teams.max' => 'Maximum allowed is 10 teams',

    'team_size.integer' => 'Team size must be a number',
    'team_size.min' => 'Minimum team size is 3 members',
    'team_size.max' => 'Maximum team size is 20 members',

    'skills.array' => 'Skills must be provided as an array',
    'skills.min' => 'At least one skill is required',
    'skills.*.exists' => 'This skill does not exist in the database',
];

    }

    protected function prepareForValidation(): void
    {
        $project = $this->route('project');

        if ($this->has('max_teams') && $project) {
            $currentTeamsCount = $project->teams()->count();
            if ($this->max_teams < $currentTeamsCount) {
                abort(400, 'max_teams cannot be less than the current number of existing teams (' . $currentTeamsCount . ')');
            }
        }

        if ($this->has('team_size') && $project) {
            $maxTeamMembers = $project->teams()
                ->withCount('activeMembers')
                ->get()
                ->max('active_members_count');

            if ($this->team_size < $maxTeamMembers) {
                abort(400, 'team_size cannot be less than the largest existing team (' . $maxTeamMembers . ')');
            }
        }

        if ($this->has('skills') && is_string($this->skills)) {
            $this->merge([
                'skills' => explode(',', $this->skills)
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
        $project = $this->route('project');

        logger()->info('Project update validation passed', [
            'user_id' => auth()->id(),
            'project_id' => $project->id,
            'project_title' => $project->title,
            'changes' => array_keys($this->validated())
        ]);
    }

    public function getFilteredValidated(): array
    {
        $validated = $this->validated();
        $project = $this->route('project');

        foreach ($validated as $key => $value) {
            if ($project->$key == $value) {
                unset($validated[$key]);
            }
        }

        return $validated;
    }
}
