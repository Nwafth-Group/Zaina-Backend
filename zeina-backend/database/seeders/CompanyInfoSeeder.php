<?php

namespace Database\Seeders;

use App\Models\CompanyInfo;
use Illuminate\Database\Seeder;

class CompanyInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        CompanyInfo::firstOrCreate(
            ['id' => 1],
            [
                'name_ar' => 'زينا',
                'name_en' => 'Zeina',
                'email' => 'info@zeina.com',
                'phone' => '+201000000000',
                'website' => 'https://zeina.com',
                'facebook_link' => 'https://facebook.com/zeina',
                'instagram_link' => 'https://instagram.com/zeina',
                'twitter_link' => 'https://twitter.com/zeina',
                'snapchat_link' => 'https://snapchat.com/add/zeina',
                'tiktok_link' => 'https://tiktok.com/@zeina',
                'linkedin_link' => 'https://linkedin.com/company/zeina',
                'vat_percentage' => 14.00,
            ]
        );
    }
}
