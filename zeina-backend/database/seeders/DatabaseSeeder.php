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
        $this->call([
            RolesAndUsersSeeder::class,
            ServiceZoneSeeder::class,
            DeparturePointSeeder::class,
            DriverSeeder::class,
            CategoryAndServiceSeeder::class,
            AdSeeder::class,
            WorkerSeeder::class,
            TeamSeeder::class,
            BookingSeeder::class,
            KhaledTestUserSeeder::class,
            PointSystemSeeder::class,
            LegalDocumentsSeeder::class,
            AnnouncementSeeder::class,
            CompanyInfoSeeder::class,
        ]);
    }
}
