<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Company;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Admin - استخدام create بدون أحداث أو استخدام query builder
        User::withoutEvents(function () {
            User::updateOrCreate(
                ['email' => 'admin@example.com'],
                [
                    'full_name' => 'Admin User',
                    'password' => Hash::make('password'),
                    'role' => 'admin',
                    'email_verified_at' => now(),
                ]
            );
        });

        // 2. Companies
        $companiesData = [
            [
                'email' => 'company1@example.com',
                'full_name' => 'Tech Solutions Inc.',
                'company_name' => 'Tech Solutions Inc.',
                'industry' => 'Information Technology',
                'size' => '50-200',
                'website' => 'https://techsolutions.com',
            ],
            [
                'email' => 'company2@example.com',
                'full_name' => 'Creative Agency LLC',
                'company_name' => 'Creative Agency LLC',
                'industry' => 'Design & Marketing',
                'size' => '10-50',
                'website' => 'https://creativeagency.com',
            ],
        ];

        foreach ($companiesData as $data) {
            // إنشاء المستخدم مع تعطيل الأحداث لمنع الإنشاء التلقائي للـ company
            $user = User::withoutEvents(function () use ($data) {
                return User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'full_name' => $data['full_name'],
                        'password' => Hash::make('password'),
                        'role' => 'company',
                        'email_verified_at' => now(),
                    ]
                );
            });

            // الآن نقوم بإنشاء الـ company يدوياً مع البيانات الكاملة
            Company::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => $data['company_name'],
                    'industry' => $data['industry'],
                    'size' => $data['size'],
                    'website' => $data['website'],
                    'subscription_end_date' => Carbon::now()->addMonths(6),
                ]
            );
        }

        // 3. Programmers - أيضاً مع تعطيل الأحداث لمنع إنشاء Programmer تلقائياً (سننشئهم لاحقاً)
        for ($i = 1; $i <= 10; $i++) {
            User::withoutEvents(function () use ($i) {
                User::updateOrCreate(
                    ['email' => "programmer{$i}@example.com"],
                    [
                        'full_name' => "Programmer {$i}",
                        'password' => Hash::make('password'),
                        'role' => 'programmer',
                        'email_verified_at' => now(),
                    ]
                );
            });
        }
    }
}
