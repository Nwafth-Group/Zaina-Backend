<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        // 1. مسح الكاش
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. إنشاء الصلاحيات
        $permissions = [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'users.verify', 'users.reset-password',
            'employees.view', 'employees.create', 'employees.edit', 'employees.delete',
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'bookings.view', 'bookings.edit', 'bookings.delete',
            'service_zones.view', 'service_zones.create', 'service_zones.edit', 'service_zones.delete',
            'drivers.view', 'drivers.create', 'drivers.edit', 'drivers.delete',
            'departure_points.view', 'departure_points.create', 'departure_points.edit', 'departure_points.delete',
            'transport_dispatch.view', 'transport_dispatch.edit',
            
            // Services
            'services.view', 'services.create', 'services.edit', 'services.delete',
            
            // Categories
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            
            // Workers
            'workers.view', 'workers.create', 'workers.edit', 'workers.delete',
            
            // Work Teams
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete',
            
            // Banners / Ads
            'banners.view', 'banners.create', 'banners.edit', 'banners.delete',
            
            // Announcements
            'announcements.view', 'announcements.create', 'announcements.edit', 'announcements.delete',
            
            // Mobile Push Notifications
            'notifications.view', 'notifications.create', 'notifications.edit', 'notifications.delete',
            
            // Coupons
            'coupons.view', 'coupons.create', 'coupons.edit', 'coupons.delete',
            
            // Loyalty Points
            'points.view', 'points.edit',
            
            // Legal Documents
            'legal_documents.view', 'legal_documents.create', 'legal_documents.edit', 'legal_documents.delete',
            
            // Reviews
            'reviews.view', 'reviews.edit',
            
            // Reports / Tickets
            'reports.view', 'reports.edit',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // 3. إنشاء الأدوار
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super Admin'],
            ['code' => 'SUPER_ADMIN', 'description' => 'مدير النظام', 'status' => 'active', 'guard_name' => 'sanctum']
        );

        $operationRole = Role::firstOrCreate(
            ['name' => 'Operations Manager'],
            ['code' => 'OPS_MGR', 'description' => 'مدير العمليات', 'status' => 'active', 'guard_name' => 'sanctum']
        );

        $customerRole = Role::firstOrCreate(
            ['name' => 'Customer'],
            ['code' => 'CUSTOMER', 'description' => 'عميل', 'status' => 'active', 'guard_name' => 'sanctum']
        );

        $driverRole = Role::firstOrCreate(
            ['name' => 'Driver'],
            ['code' => 'Driver', 'description' => 'سائق', 'status' => 'active', 'guard_name' => 'sanctum']
        );

        $superAdminRole->syncPermissions(Permission::all());
        $operationRole->syncPermissions(['users.view', 'users.edit', 'bookings.view', 'bookings.edit']);

        // ------------------------------------------------------------------
        // 4. توليد المستخدمين (Seeding Users)
        // ------------------------------------------------------------------

        // أ. مستخدم Super Admin واحد فقط (ببيانات ثابتة عشان تقدر تعمل Login)
        $superAdmin = User::firstOrCreate(
            ['phone_number' => '0599000001'],
            [
                'first_name' => 'Admin',
                'last_name' => 'Ziena System',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'verification_status' => 'approved',
                'gender' => 'Male',
                'type' => 'super_admin',
                'status' => 'active',
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        // ب. توليد موظفي عمليات (Operations)
        $operations = [
            [
                'first_name' => 'خالد',
                'last_name' => 'عادل إبراهيم',
                'phone_number' => '01099000001',
                'email' => 'ops1@zeina.com',
                'password' => Hash::make('password'),
                'verification_status' => 'approved',
                'gender' => 'Male',
                'type' => 'operation',
                'job_title' => 'Operations Officer',
                'status' => 'active',
            ],
            [
                'first_name' => 'محمد',
                'last_name' => 'سمير عبد الوهاب',
                'phone_number' => '01099000002',
                'email' => 'ops2@zeina.com',
                'password' => Hash::make('password'),
                'verification_status' => 'approved',
                'gender' => 'Male',
                'type' => 'operation',
                'job_title' => 'Operations Officer',
                'status' => 'active',
            ],
            [
                'first_name' => 'أحمد',
                'last_name' => 'عصام التوني',
                'phone_number' => '01099000003',
                'email' => 'ops3@zeina.com',
                'password' => Hash::make('password'),
                'verification_status' => 'approved',
                'gender' => 'Male',
                'type' => 'operation',
                'job_title' => 'Operations Officer',
                'status' => 'active',
            ],
        ];

        foreach ($operations as $opData) {
            $op = User::updateOrCreate(['phone_number' => $opData['phone_number']], $opData);
            $op->assignRole($operationRole);
        }

        // ج. توليد العملاء (Customers) - (مغلق بناءً على الطلب)
        /*
        $customers = User::factory()->count(8)->create([
            'type' => 'customer',
            'password' => Hash::make('password'),
            'job_title' => null,
        ]);

        // إسناد الدور لكل عميل
        foreach ($customers as $customer) {
            $customer->assignRole($customerRole);
        }

        // إضافة عميل واحد ببيانات معروفة للفحص السريع
        $testCustomer = User::firstOrCreate(
            ['phone_number' => '+200599000003'],
            [
                'first_name' => 'Sara',
                'last_name' => 'Customer',
                'email' => 'sara@example.com',
                'password' => Hash::make('password'),
                'type' => 'customer',
                'verification_status' => 'approved',
                'status' => 'active',
            ]
        );
        $testCustomer->assignRole($customerRole);
        */
    }
}
