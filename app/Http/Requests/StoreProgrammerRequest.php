<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgrammerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            "user_id" => "required|exists:users,id",
            "specialty" => "required|string",
            "total_score" => "required|numeric",
            "github_username" => "required|string",
            "behance_url" => "sometimes|string|nullable",
        ];
    }
}
