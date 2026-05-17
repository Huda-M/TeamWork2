<?php

namespace App\Http\Requests\Company\JopOffer;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SendJopOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true ;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string',
            'description' => 'required|string',
            'salary_range' => 'required|string',
            'job_type' => 'required|in:full-time,part-time,freelance',
            'work_type' => 'required|in:on-site,remote,hybrid',
            'programmer_id' => 'required|exists:programmers,id',
        ];
    }
}
