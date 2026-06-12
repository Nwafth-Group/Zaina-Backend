<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LegalDocumentsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. بيانات الشروط والأحكام (Terms)
        $terms = [
            [
                'type'     => 'terms',
                'title_ar' => 'إدارة بياناتك ونشاطاتك',
                'title_en' => 'Manage your data and activity',
                'content_ar' => 'نحن ملتزمون بحماية خصوصيتك وإدارة بياناتك بأمان تام وفقاً لأعلى معايير الحماية البرمجية.',
                'content_en' => 'An enthusiastic learner with a keen interest in subscribing to various courses. Passionate about acquiring new skills and expanding knowledge across diverse fields.',
                'sort_order'=> 1
            ],
            [
                'type'     => 'terms',
                'title_ar' => 'الحصول على نسخة من البيانات',
                'title_en' => 'Get a copy of your data',
                'content_ar' => 'يمكنك في أي وقت تقديم طلب عبر التطبيق للحصول على نسخة شاملة ومصدرة من كافة بيانات حركاتك الحالية.',
                'content_en' => 'You can request a full backup or copy of your historical activities and data via your profile settings at any time.',
                'sort_order'=> 2
            ]
        ];

        // 2. بيانات سياسة الخصوصية (Privacy)
        $privacy = [
            [
                'type'     => 'privacy',
                'title_ar' => 'سجل البحث والتعريف الجغرافي',
                'title_en' => 'Search history and Location tracking',
                'content_ar' => 'نستخدم حيز الموقع الجغرافي فقط لتقديم أفضل تجربة تسعير وتغطية دقيقة لعنوانك السكني.',
                'content_en' => 'We collect and process your geographical location strictly to provide accurate service availability and optimized pricing calculations.',
                'sort_order'=> 1
            ]
        ];

        // إدخال البيانات بأمان ومقاومة التكرار بناءً على العنوان المفتاحي
        foreach (array_merge($terms, $privacy) as $doc) {
            DB::table('legal_documents')->updateOrInsert(
                ['type' => $doc['type'], 'title_en' => $doc['title_en']],
                array_merge($doc, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}