// app/Http/Requests/UpdateProgrammerProfileRequest.php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateProgrammerProfileRequest extends FormRequest
{
    public function authorize()
    {
        return Auth::check();
    }
    
    public function rules()
    {
        return [
            'user_name' => 'sometimes|string|unique:programmers,user_name,'.Auth::user()->programmer->id,
            'phone' => 'nullable|string',
            'bio' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
            'track' => 'nullable|string',
            'experience_level' => 'nullable|in:beginner,junior,senior,expert',
        ];
    }
}
