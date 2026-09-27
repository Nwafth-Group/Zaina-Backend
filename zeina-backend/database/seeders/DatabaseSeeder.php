<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            RolesAndUsersSeeder::class,
            ServiceZoneSeeder::class,
            CategoryAndServiceSeeder::class,
            ProviderSeeder::class,
            AdSeeder::class,
            WorkerSeeder::class,
            // CustomerAddressSeeder::class,
            // BookingSeeder::class,
            // ReviewSeeder::class,
            // WalletSeeder::class,
            PointSystemSeeder::class,
            // CouponSeeder::class,
            LegalDocumentsSeeder::class,
            // ReviewServcieAndWorkerSeeder::class,
            AnnouncementSeeder::class,
            // MobileNotificationSeeder::class,
            CompanyInfoSeeder::class,
            DriverSeeder::class,
            DeparturePointSeeder::class,
            TeamSeeder::class,
            KhaledTestUserSeeder::class,
        ]);
    }
}
