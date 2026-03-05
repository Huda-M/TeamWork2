<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'programmer';
    }


public function rules(): array
{
    $project = Project::find($this->project_id);

    if (!$project) {
        return [
            'project_id' => 'required|exists:projects,id'
        ];
    }

    return [
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'project_id' => [
            'required',
            'exists:projects,id',
            function ($attribute, $value, $fail) use ($project) {
                if (!$project->hasRoomForNewTeam()) {
                    $fail('The project has reached the maximum number of teams (' . $project->max_teams . ' teams)');
                }
            }
        ],
        'formation_type' => 'required|in:random,manual,mixed',
        'is_public' => 'required|boolean',
        'experience_level' => 'nullable|in:beginner,intermediate,advanced,expert',
        'required_skills' => 'nullable|array',
        'required_skills.*' => 'string',
        'preferred_skills' => 'nullable|array',
        'preferred_skills.*' => 'string',
    ];
}

public function messages(): array
{
    return [
    'project_id.required' => 'Project is required',
    'project_id.exists' => 'Selected project does not exist',
];
}
}
