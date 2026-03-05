<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->programmer;
    }

    public function rules(): array
    {
        return [
            'programmer_id' => 'required|exists:programmers,id',
            'role' => 'required|in:leader,member',
            'message' => 'nullable|string|max:500',
            'expires_at' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'programmer_id.required' => 'Programmer ID is required',
'programmer_id.exists' => 'The selected programmer does not exist',

'role.required' => 'Role is required',
'role.in' => 'Role must be either leader or member',

'message.max' => 'Message must not exceed 500 characters',

'expires_at.after' => 'Expiration date must be after the current time',

        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'role' => $this->role ?? 'member',
        ]);
    }
}
