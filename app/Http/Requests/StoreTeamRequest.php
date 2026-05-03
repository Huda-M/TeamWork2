<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Project;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'programmer';
    }

    public function rules(): array
    {
        $project = Project::find($this->project_id);
        $maxTeams = $project ? $project->max_teams : 1;

        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'project_id' => [
                'required',
                'exists:projects,id',
                function ($attribute, $value, $fail) use ($project, $maxTeams) {
                    if ($project && $project->teams()->count() >= $maxTeams) {
                        $fail('The project has reached the maximum number of teams (' . $maxTeams . ').');
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
            'category' => 'nullable|array',
'category.*' => 'string|max:100',
'required_role' => 'nullable|array',
'required_role.*' => 'string|max:100',

            // الحقول الجديدة
            'github_url' => 'nullable|url',

            // دعوات للمبرمجين (إذا كان الفريق خاص)
            'invitations' => 'nullable|array',
            'invitations.*' => 'exists:programmers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'project_id.required' => 'Project is required',
            'project_id.exists' => 'Selected project does not exist',
            'github_url.url' => 'GitHub URL must be a valid URL',
        ];
    }
}

