<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // نسمح فقط للمستخدمين غير المسجلين أو الأدمن؟ حسب سياسة التسجيل
        // نفترض أن التسجيل مفتوح أو أن الأدمن هو من ينشئ الشركات
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => 'required|exists:users,id|unique:companies,user_id',
            'company_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'cr_number' => 'required|string|max:50|unique:companies,cr_number',
            'about' => 'nullable|string|min:20',
            'country' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'social_links' => 'nullable|array',
            'social_links.*' => 'url|max:255',
            'industry' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required',
            'user_id.exists' => 'User not found',
            'user_id.unique' => 'This user already has a company profile',
            'company_name.required' => 'Company name is required',
            'phone.required' => 'Phone number is required',
            'cr_number.required' => 'Commercial registration number is required',
            'cr_number.unique' => 'This commercial registration number is already used',
            'country.required' => 'Country is required',
            'location.required' => 'Location is required',
            'industry.required' => 'Industry is required',
            'logo.image' => 'Logo must be an image',
            'logo.max' => 'Logo size must not exceed 5MB',
            'social_links.*.url' => 'Each social link must be a valid URL',
        ];
    }
}
