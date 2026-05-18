<?php

namespace App\Http\Controllers\Company\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Auth\CompleteProfileRequest;
use App\Http\Requests\Company\Auth\UpdateProfileRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function me()
    {
        return response()->json([
            'message' => 'Profile Fetched Successfully',
            'status' => 200,
            'company' => auth()->user()->load('company'),
        ]);
    }

    public function completeProfile(CompleteProfileRequest $request)
    {
        $company = null;

        DB::transaction(function () use ($request, &$company) {
            $data = $request->validated();
            $data['profile_completed'] = true;
            $data['user_id'] = auth()->user()->id;

            if ($request->hasFile('logo')) {
                $data['logo'] = $request->file('logo')->store('logos', 'public');
            }

            $company = Company::create($data);
        });

        return response()->json([
            'message' => 'Profile completed successfully',
            'status' => 200,
            'company' => $company,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $company = Company::where('user_id', auth()->user()->id)->first();

        DB::transaction(function () use ($request, &$company) {
            $data = $request->validated();

            if ($request->hasFile('logo')) {
                if ($company->logo) {
                    Storage::delete($company->logo);
                }
                $data['logo'] = $request->file('logo')->store('logos', 'public');
            }

            $company->update($data);
        });

        return response()->json([
            'message' => 'Profile updated successfully',
            'status' => 200,
            'company' => $company,
        ]);
    }

    public function deleteProfile()
    {
        DB::transaction(function () {
            $user = User::where('email', auth()->user()->email)->first();
            if ($user->company) {
                $user->company->update([
                    'cr_number' => $user->company->cr_number.'::deleted_'.time(),
                ]);
                $user->company->delete();
            }
            $user->tokens()->delete();

            $user->update([
                'email' => $user->email.'::deleted_'.time(),
            ]);

            $user->delete();
        });

        return response()->json([
            'message' => 'Profile deleted successfully',
            'status' => 200,
        ]);
    }
}
