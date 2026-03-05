<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Team;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $team = $this->route('team');
        $programmer = auth()->user()->programmer;

        return $team && $programmer &&
               ($team->isLeader($programmer->id) || auth()->user()->role === 'admin');
    }

    public function rules(): array
    {
        $team = $this->route('team');
        $currentMembersCount = $team ? $team->activeMembers()->count() : 0;

        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:active,completed,disbanded',
            'is_public' => 'sometimes|boolean',
            'max_members' => 'sometimes|integer|min:' . max(3, $currentMembersCount) . '|max:20',
            'experience_level' => 'sometimes|in:beginner,intermediate,advanced,expert',
            'required_skills' => 'nullable|array',
            'required_skills.*' => 'string',
            'preferred_skills' => 'nullable|array',
            'preferred_skills.*' => 'string',
            'avatar_url' => 'nullable|url',
            'join_code' => 'nullable|string|size:8|unique:teams,join_code,' . ($team ? $team->id : 'NULL'),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required',
'name.max' => 'Team name must not exceed 255 characters',

'status.in' => 'Status must be: active, completed, or dissolved',

'max_members.min' => 'Maximum members must be at least the current number of members (' . ($this->route('team')->activeMembers()->count() ?? 0) . ')',
'max_members.max' => 'Maximum members must not exceed 20',

'experience_level.in' => 'Experience level must be: beginner, intermediate, advanced, or expert',

'required_skills.array' => 'Required skills must be an array',
'preferred_skills.array' => 'Preferred skills must be an array',

'avatar_url.url' => 'Avatar URL must be a valid URL',

'join_code.size' => 'Join code must be exactly 8 characters',
'join_code.unique' => 'This join code is already in use',

        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('required_skills') && is_string($this->required_skills)) {
            $this->merge([
                'required_skills' => json_decode($this->required_skills, true)
            ]);
        }

        if ($this->has('preferred_skills') && is_string($this->preferred_skills)) {
            $this->merge([
                'preferred_skills' => json_decode($this->preferred_skills, true)
            ]);
        }

        if ($this->has('is_public') && $this->is_public === false && empty($this->join_code)) {
            $this->merge([
                'join_code' => strtoupper(substr(md5(uniqid()), 0, 8))
            ]);
        }

        if ($this->has('is_public') && $this->is_public === true) {
            $this->merge(['join_code' => null]);
        }
    }

    protected function passedValidation(): void
    {
        if ($this->has('status') && $this->status === 'disbanded') {
            $this->merge(['disbanded_at' => now()]);
        }

        if ($this->has('status') && $this->status !== 'disbanded' && $this->route('team')->status === 'disbanded') {
            $this->merge(['disbanded_at' => null]);
        }
    }
}
