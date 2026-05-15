<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Http\Requests\UpdateCompanyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    /**
     * عرض بيانات الشركة (للمستخدم المسجل)
     */
    public function showProfile(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'Only companies can access',
                'errors' => null,
                'data' => null
            ], 403);
        }

        $company = $user->company;
        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found',
                'errors' => null,
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Company profile retrieved',
            'errors' => null,
            'data' => [
                'id' => $company->id,
                'company_name' => $company->company_name,
                'phone' => $company->phone,
                'cr_number' => $company->cr_number,
                'about' => $company->about,
                'country' => $company->country,
                'location' => $company->location,
                'logo_url' => $company->logo_url,
                'social_links' => $company->social_links,
                'industry' => $company->industry,
                'subscription_end_date' => $company->subscription_end_date,
                'profile_completed' => $company->profile_completed,
            ]
        ]);
    }

    /**
     * تحديث بيانات الشركة (بدون تحديث cr_number)
     */
    public function updateProfile(UpdateCompanyRequest $request)
    {
        $user = $request->user();
        // الصلاحية تم التحقق منها في UpdateCompanyRequest

        $company = $user->company;
        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found',
                'errors' => null,
                'data' => null
            ], 404);
        }

        $data = $request->validated();

        // رفع الشعار الجديد إن وجد
        if ($request->hasFile('logo')) {
            // حذف الشعار القديم
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $path = $request->file('logo')->store('company_logos', 'public');
            $data['logo'] = $path;
        }

        // تحديث البيانات (لا يشمل cr_number)
        $company->update($data);

        // تحديث حالة إكمال الملف الشخصي
        if (!$company->profile_completed &&
            isset($data['company_name'], $data['phone'], $company->cr_number, $data['country'], $data['location'])) {
            $company->profile_completed = true;
            $company->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Company profile updated successfully',
            'errors' => null,
            'data' => [
                'id' => $company->id,
                'company_name' => $company->company_name,
                'phone' => $company->phone,
                'cr_number' => $company->cr_number,
                'about' => $company->about,
                'country' => $company->country,
                'location' => $company->location,
                'logo_url' => $company->logo_url,
                'social_links' => $company->social_links,
                'industry' => $company->industry,
                'subscription_end_date' => $company->subscription_end_date,
                'profile_completed' => $company->profile_completed,
            ]
        ]);
    }

    // باقي الدوال (softDelete, restore) كما هي دون تغيير


    /**
     * Soft Delete للشركة (حذف الحساب مع إمكانية الاستعادة)
     */
    public function softDelete(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'Only companies can delete their account',
                'errors' => null,
                'data' => null
            ], 403);
        }

        $company = $user->company;
        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found',
                'errors' => null,
                'data' => null
            ], 404);
        }

        // إبطال التوكنات الحالية
        $user->tokens()->delete();

        // Soft delete للشركة وللمستخدم
        $company->delete();
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company account soft deleted successfully',
            'errors' => null,
            'data' => null
        ]);
    }

    /**
     * استعادة الحساب المحذوف (للأدمن فقط)
     */
    public function restore($id)
    {
        $user = User::withTrashed()->findOrFail($id);
        if ($user->role !== 'company') {
            return response()->json([
                'success' => false,
                'message' => 'User is not a company',
                'errors' => null,
                'data' => null
            ], 400);
        }

        $company = Company::withTrashed()->where('user_id', $user->id)->first();
        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company profile not found',
                'errors' => null,
                'data' => null
            ], 404);
        }

        $company->restore();
        $user->restore();

        return response()->json([
            'success' => true,
            'message' => 'Company account restored successfully',
            'errors' => null,
            'data' => null
        ]);
    }
}

