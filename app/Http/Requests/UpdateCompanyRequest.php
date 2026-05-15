<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        // فقط الشركة نفسها أو الأدمن يمكنه التحديث
        return $user && ($user->role === 'company' || $user->role === 'admin');
    }

    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'about' => ['nullable', 'string', 'min:20'],
            'country' => ['sometimes', 'string', 'max:100'],
            'location' => ['sometimes', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'social_links' => ['nullable', 'array'],
            'social_links.*' => ['url', 'max:255'],
            'industry' => ['sometimes', 'string', 'max:100'],
            // ملاحظة: cr_number غير موجود هنا لأنه لا يمكن تحديثه
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.max' => 'Company name must not exceed 255 characters',
            'phone.max' => 'Phone number must not exceed 20 characters',
            'about.min' => 'About section must be at least 20 characters',
            'country.max' => 'Country name must not exceed 100 characters',
            'location.max' => 'Location must not exceed 255 characters',
            'logo.image' => 'The logo must be an image',
            'logo.mimes' => 'Logo must be a file of type: jpg, jpeg, png',
            'logo.max' => 'Logo size must not exceed 5MB',
            'social_links.array' => 'Social links must be an array',
            'social_links.*.url' => 'Each social link must be a valid URL',
            'industry.max' => 'Industry must not exceed 100 characters',
        ];
    }
}

