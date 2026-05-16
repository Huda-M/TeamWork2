<?php

namespace App\Http\Requests\Company\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CompleteProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            "logo" => "nullable|image|mimes:jpeg,png,jpg,gif|max:2048",
            "social_links" => "nullable|array",
            "social_links.*" => "url",
            "user_id" => "required|exists:users,id",
            "phone"=> "required|string|max:255",
            "company_name" => "required|string|max:255",
            "cr_number" => "required|string|max:255",
            "about" => "required|string",
            "country" => "required|string|max:255",
            "location" => "required|string|max:255",
            "industry" => "required|string|max:255",
        ];
    }
}
