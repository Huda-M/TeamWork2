<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgrammerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "user_id" => "sometimes|exists:users,id",
            "specialty" => "sometimes|string",
            "total_score" => "sometimes|numeric|nullable",
            "github_username" => "sometimes|string|nullable",
            "behance_url" => "sometimes|string|nullable",
        ];
    }
}
