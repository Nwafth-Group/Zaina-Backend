<?php

namespace Database\Seeders;

use App\Models\CompanyInfo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class CompanyInfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = CompanyInfo::firstOrCreate(
            ['id' => 1],
            [
                'name_ar' => 'زينه',
                'name_en' => 'Ziena',
                'email' => 'info@zeina.com',
                'phone' => '+201000000000',
                'website' => 'https://zeina.com',
                'facebook_link' => 'https://facebook.com/zeina',
                'instagram_link' => 'https://instagram.com/zeina',
                'twitter_link' => 'https://twitter.com/zeina',
                'snapchat_link' => 'https://snapchat.com/add/zeina',
                'tiktok_link' => 'https://tiktok.com/@zeina',
                'linkedin_link' => 'https://linkedin.com/company/zeina',
                'vat_percentage' => 00.00,
                'tax_number' => '123456789000003',
            ]
        );

        // Upload logo from D:\work\Nwafth\Zeina\Zaina-Backend\zeina-backend\public\assist\logo\logo.webp
        $sourcePath = public_path('assist/logo/logo.webp');
        if (file_exists($sourcePath)) {
            // Delete old media collection to avoid duplicates
            $company->media()->where('collection', 'company_info/logo')->delete();

            $targetDir = 'company_info/logo';
            $fileName = 'logo_'.time().'.webp';
            $targetPath = $targetDir.'/'.$fileName;

            // Store file using Storage
            Storage::disk('public')->put($targetPath, file_get_contents($sourcePath));

            $company->media()->create([
                'collection' => 'company_info/logo',
                'file_name' => $fileName,
                'mime_type' => 'image/webp',
                'size' => filesize($sourcePath),
                'disk' => 'public',
                'path' => $targetPath,
                'title' => 'logo.webp',
                'date' => now()->toDateString(),
            ]);
        }
    }
}
