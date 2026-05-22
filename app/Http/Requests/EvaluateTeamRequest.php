<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EvaluateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role === 'programmer';
    }

    public function rules(): array
    {
        return [
            'evaluations' => 'required|array|min:1',
            'evaluations.*.evaluated_id' => 'required|exists:programmers,id',
            'evaluations.*.rating' => 'required|integer|min:1|max:5',
            'evaluations.*.feedback' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'evaluations.*.evaluated_id.required' => 'Each evaluation must have an evaluated_id',
            'evaluations.*.rating.required' => 'Each evaluation must have a rating (1-5)',
            'evaluations.*.rating.min' => 'Rating must be at least 1',
            'evaluations.*.rating.max' => 'Rating cannot exceed 5',
        ];
    }
}
