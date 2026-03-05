<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Team;

class UpdateTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $team = $this->route('team');
        $programmer = auth()->user()->programmer;

        return $team && $programmer && $team->isLeader($programmer->id);
    }

    public function rules(): array
    {
        $team = $this->route('team');
        $memberId = $this->route('programmer') ? $this->route('programmer')->id : null;

        return [
            'role' => 'required|in:leader,member',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => 'Role is required',
'role.in' => 'Role must be either leader or member',

'reason.max' => 'Reason must not exceed 500 characters',

        ];
    }

    protected function prepareForValidation(): void
    {
        $team = $this->route('team');
        $memberId = $this->route('programmer') ? $this->route('programmer')->id : null;

        if ($team && $memberId) {
            $member = $team->activeMembers()->where('programmer_id', $memberId)->first();

            if ($member && $member->role === 'leader') {
                $otherMembersCount = $team->activeMembers()
                    ->where('programmer_id', '!=', $memberId)
                    ->count();

                if ($otherMembersCount === 0) {
                    $this->merge(['role' => 'leader']);
                }
            }
        }
    }

    protected function passedValidation(): void
    {
        $team = $this->route('team');
        $memberId = $this->route('programmer') ? $this->route('programmer')->id : null;

        if ($this->role === 'leader' && $team && $memberId) {
            $currentLeader = $team->activeMembers()
                ->where('role', 'leader')
                ->where('programmer_id', '!=', $memberId)
                ->first();

            if ($currentLeader) {
                $currentLeader->update(['role' => 'member']);
            }
        }
    }
}
