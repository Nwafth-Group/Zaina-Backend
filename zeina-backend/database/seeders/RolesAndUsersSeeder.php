<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\Fluent\Concerns\Has;
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
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'bookings.view', 'bookings.edit', 'bookings.delete', 'location.edit',
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

        $superAdminRole->syncPermissions(Permission::all());
        $operationRole->syncPermissions(['users.view', 'bookings.view', 'bookings.edit']);

        // ------------------------------------------------------------------
        // 4. توليد المستخدمين (Seeding Users)
        // ------------------------------------------------------------------

        // أ. مستخدم Super Admin واحد فقط (ببيانات ثابتة عشان تقدر تعمل Login)
        $superAdmin = User::firstOrCreate(
            ['phone_number' => '0599000001'],
            [
                'first_name' => 'Ahmad',
                'last_name' => 'Admin',
                'email' => 'admin@zeina.com',
                'password' => Hash::make('password'),
                'type' => 'super_admin',
                'status' => 'active',
            ]
        );
        $superAdmin->assignRole($superAdminRole);

        // ب. توليد 50 موظف عمليات (Operations)
        // بنستخدم الـ Factory وبنجبر الـ type يكون operation
        $operations = User::factory()->count(20)->create([
            'type' => 'operation',
            'job_title' => 'Operations Officer',
        ]);
        
        // إسناد الدور لكل موظف عمليات
        foreach ($operations as $op) {
            $op->assignRole($operationRole);
        }

        // ج. توليد 500 عميل (Customers)
        // العملاء ما عندهم باسورد (بيدخلوا بـ OTP) ولا Job Title
        $customers = User::factory()->count(50)->create([
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
            ['phone_number' => '0599000003'],
            [
                'first_name' => 'Sara',
                'last_name' => 'Customer',
                'email' => 'sara@example.com',
                'password' => Hash::make('password'), 
                'type' => 'customer',
                'status' => 'active',
            ]
        );
        $testCustomer->assignRole($customerRole);
    }
}