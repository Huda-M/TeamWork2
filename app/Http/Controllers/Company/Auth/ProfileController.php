<?php

namespace App\Http\Controllers\Company\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\Auth\CompleteProfileRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function me(){
        return response()->json([
            "message" => "Profile Fetched Successfully",
            "status" => 200,
            "company" => auth()->user()->company
        ]);
    }

    public function completeProfile(CompleteProfileRequest $request){
        $data = $request->validated();
        $data['profile_completed'] = true;
        $data['user_id'] = auth()->user()->id;

        if($request->hasFile('logo')){
            $data['logo'] = $request->file('logo')->store('logos', 'public');
        }
        
        $company = Company::create($data);

        return response()->json([
            "message" => "Profile completed successfully",
            "status" => 200,
            "company" => $company
        ]);
    }

    public function updateProfile(){

    }

     public function deleteProfile(){
        $user = User::where("email", auth()->user()->email)->first();
        $user->delete();
        auth()->logout();

        return response()->json([
            "message" => "Profile deleted successfully",
            "status" => 200
        ]);
    }
}
