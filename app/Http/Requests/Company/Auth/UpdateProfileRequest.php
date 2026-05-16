<?php

namespace App\Http\Requests\Company\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            "social_links.*" => "sometimes|url",
            "phone"=> "sometimes|string|max:255",
            "company_name" => "sometimes|string|max:255",
            "cr_number" => "sometimes|string|max:255",
            "about" => "sometimes|string",
            "country" => "sometimes|string|max:255",
            "location" => "sometimes|string|max:255",
            "industry" => "sometimes|string|max:255",
        ];
    }
}
