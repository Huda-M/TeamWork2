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
        // 1. Admin - استخدام create بدون أحداث
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

        // 2. Companies - إضافة الحقول الجديدة
        $companiesData = [
            [
                'email' => 'company1@example.com',
                'full_name' => 'Tech Solutions Inc.',
                'company_name' => 'Tech Solutions Inc.',
                'phone' => '+1234567890',
                'cr_number' => 'CR-TECH-12345',
                'about' => 'Leading software development company specializing in AI and cloud solutions.',
                'country' => 'Egypt',
                'location' => 'Cairo, Smart Village, Building B3',
                'social_links' => json_encode(['https://linkedin.com/company/techsolutions', 'https://twitter.com/techsolutions']),
                'industry' => 'Information Technology',
                'size' => '50-200',
                'website' => 'https://techsolutions.com',
            ],
            [
                'email' => 'company2@example.com',
                'full_name' => 'Creative Agency LLC',
                'company_name' => 'Creative Agency LLC',
                'phone' => '+1987654321',
                'cr_number' => 'CR-CREATE-67890',
                'about' => 'Award-winning creative agency offering branding, design, and marketing services.',
                'country' => 'UAE',
                'location' => 'Dubai, Internet City, Building 12',
                'social_links' => json_encode(['https://instagram.com/creativeagency', 'https://facebook.com/creativeagency']),
                'industry' => 'Design & Marketing',
                'size' => '10-50',
                'website' => 'https://creativeagency.com',
            ],
        ];

        foreach ($companiesData as $data) {
            // إنشاء المستخدم مع تعطيل الأحداث
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

            // إنشاء أو تحديث الـ company بالحقول الكاملة
            Company::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'company_name' => $data['company_name'],
                    'phone' => $data['phone'],
                    'cr_number' => $data['cr_number'],
                    'about' => $data['about'],
                    'country' => $data['country'],
                    'location' => $data['location'],
                    'logo' => null,
                    'social_links' => $data['social_links'],
                    'profile_completed' => true,
                    'industry' => $data['industry'],
                    'size' => $data['size'],
                    'website' => $data['website'],
                    'subscription_end_date' => Carbon::now()->addMonths(6),
                ]
            );
        }

        // 3. Programmers - بدون تغيير
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
