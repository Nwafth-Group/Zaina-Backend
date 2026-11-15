<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class WorkerSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Fetch all services
        $services = Service::all()->keyBy('service_code');

        if ($services->isEmpty()) {
            $this->command->error('Services table is empty. Please run CategoryAndServiceSeeder first.');
            return;
        }

        // Image resources
        $image1 = public_path('assist/workers/1.webp');
        $image2 = public_path('assist/workers/2.webp');
        $hasImg1 = file_exists($image1);
        $hasImg2 = file_exists($image2);

        // Defined Real Workers Pool
        $workersData = [
            // =========================================================
            // 1. Domestic Helpers (عاملات منزليات بالساعة - Service 1)
            // =========================================================
            [
                'name' => 'مريم إبراهيم حسن',
                'gender' => 'female',
                'nationality' => 'الفلبين',
                'phone_number' => '01020220811',
                'id_number' => '29508140102541',
                'address' => 'شارع مصطفى النحاس، الحي الثامن، مدينة نصر، القاهرة',
                'avg_rating' => 4.9,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'فاطمة أحمد عبد الله',
                'gender' => 'female',
                'nationality' => 'إندونيسيا',
                'phone_number' => '01020220812',
                'id_number' => '29609120103412',
                'address' => 'شارع عباس العقاد، المنطقة الأولى، مدينة نصر، القاهرة',
                'avg_rating' => 4.8,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'خديجة محمد السيد',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01020220813',
                'id_number' => '29403150104123',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس، القاهرة الجديدة',
                'avg_rating' => 5.0,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'سارة علي خليل',
                'gender' => 'female',
                'nationality' => 'الفلبين',
                'phone_number' => '01020220814',
                'id_number' => '29711200105234',
                'address' => 'شارع التسعين الجنوبي، بجوار مجمع البنوك، التجمع الخامس',
                'avg_rating' => 4.9,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'زينب مصطفى كمال',
                'gender' => 'female',
                'nationality' => 'أوغندا',
                'phone_number' => '01020220815',
                'id_number' => '29801050106345',
                'address' => 'مدينة الرحاب، مجموعة 124، عمارة 8، شقة 3',
                'avg_rating' => 4.7,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'أسماء عبد الرحمن محمود',
                'gender' => 'female',
                'nationality' => 'كينيا',
                'phone_number' => '01020220816',
                'id_number' => '29904180107456',
                'address' => 'مدينتي، منطقة B1، مجموعة 12، عمارة 4',
                'avg_rating' => 4.8,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'ياسمين هاني صالح',
                'gender' => 'female',
                'nationality' => 'إندونيسيا',
                'phone_number' => '01020220817',
                'id_number' => '29512250108567',
                'address' => 'شارع 9، المعادي، القاهرة',
                'avg_rating' => 4.9,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'سيتي نورواني سارتونو',
                'gender' => 'female',
                'nationality' => 'إندونيسيا',
                'phone_number' => '01020220818',
                'id_number' => '29607140109678',
                'address' => 'شارع اللاسلكي، المعادي الجديدة، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'ديوي سارتيكا وجايا',
                'gender' => 'female',
                'nationality' => 'إندونيسيا',
                'phone_number' => '01020220819',
                'id_number' => '29705220101789',
                'address' => 'شارع جامعة الدول العربية، المهندسين، الجيزة',
                'avg_rating' => 4.8,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],
            [
                'name' => 'ألميرا باوتيستا رييس',
                'gender' => 'female',
                'nationality' => 'الفلبين',
                'phone_number' => '01020220820',
                'id_number' => '29808300102890',
                'address' => 'شارع مصدق، الدقي، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['DOMESTIC_HELPER_HOURLY'],
            ],

            // =========================================================
            // 2. Hospitality Workers (عاملات وعمال ضيافة - Service 2)
            // =========================================================
            [
                'name' => 'نور الهدى طارق شريف',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01145678901',
                'id_number' => '29502100103901',
                'address' => 'ميدان الحجاز، مصر الجديدة، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'هبة فاروق حسنين',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01145678902',
                'id_number' => '29606180104012',
                'address' => 'شارع المرغني، كلية البنات، مصر الجديدة',
                'avg_rating' => 4.9,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'آية سامي يوسف',
                'gender' => 'female',
                'nationality' => 'المغرب',
                'phone_number' => '01145678903',
                'id_number' => '29710250105123',
                'address' => 'شارع النزهة، تريومف، مصر الجديدة',
                'avg_rating' => 4.8,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'ريم خالد عثمان',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01145678904',
                'id_number' => '29803120106234',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس',
                'avg_rating' => 4.9,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'ماري جريس سانتوس',
                'gender' => 'female',
                'nationality' => 'الفلبين',
                'phone_number' => '01145678905',
                'id_number' => '29909050107345',
                'address' => 'الحي الدبلوماسي، التجمع الخامس',
                'avg_rating' => 5.0,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'أنجيلا ديل كروز',
                'gender' => 'female',
                'nationality' => 'الفلبين',
                'phone_number' => '01145678906',
                'id_number' => '29511110108456',
                'address' => 'الحي المتميز، الشيخ زايد، الجيزة',
                'avg_rating' => 4.8,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'حسام الدين فؤاد مرسي',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01145678907',
                'id_number' => '29408220109567',
                'address' => 'شارع البطل أحمد عبد العزيز، المهندسين',
                'avg_rating' => 4.9,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'هيثم رجب عبد المنعم',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01145678908',
                'id_number' => '29604150101678',
                'address' => 'شارع التحرير، الدقي، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'كريم صلاح الدين عثمان',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01145678909',
                'id_number' => '29701190102789',
                'address' => 'شارع النصر، المعادي، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],
            [
                'name' => 'طارق يوسف عبد العزيز',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01145678910',
                'id_number' => '29812080103890',
                'address' => 'شارع مكرم عبيد، مدينة نصر، القاهرة',
                'avg_rating' => 4.8,
                'services' => ['HOSPITALITY_WORKER_HOURLY'],
            ],

            // =========================================================
            // 3. Babysitters (جليسات أطفال بالساعة - Service 3)
            // =========================================================
            [
                'name' => 'سلمى يوسف عبد العزيز',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01287654301',
                'id_number' => '29605150104901',
                'address' => 'شارع الطيران، مدينة نصر، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'مروة عادل إمام',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01287654302',
                'id_number' => '29708200105012',
                'address' => 'شارع أنور المفتي، مدينة نصر، القاهرة',
                'avg_rating' => 4.9,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'شيماء إبراهيم متولي',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01287654303',
                'id_number' => '29510100106123',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس',
                'avg_rating' => 4.8,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'ندى سعيد عاشور',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01287654304',
                'id_number' => '29802280107234',
                'address' => 'ميدان روكسي، مصر الجديدة، القاهرة',
                'avg_rating' => 4.9,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'هنا طارق بدوي',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01287654305',
                'id_number' => '29907140108345',
                'address' => 'شارع دمشق، مصر الجديدة، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'ليلى سامي فؤاد',
                'gender' => 'female',
                'nationality' => 'المغرب',
                'phone_number' => '01287654306',
                'id_number' => '29611220109456',
                'address' => 'شارع اللاسلكي، المعادي الجديدة',
                'avg_rating' => 4.9,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'فاطمة الزهراء الإدريسي',
                'gender' => 'female',
                'nationality' => 'المغرب',
                'phone_number' => '01287654307',
                'id_number' => '29704050101567',
                'address' => 'شارع النخيل، الدقي، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['BABYSITTER_HOURLY'],
            ],
            [
                'name' => 'جويس نامبوزي موكاسا',
                'gender' => 'female',
                'nationality' => 'أوغندا',
                'phone_number' => '01287654308',
                'id_number' => '29809180102678',
                'address' => 'الحي السابع، مدينة 6 أكتوبر',
                'avg_rating' => 4.8,
                'services' => ['BABYSITTER_HOURLY'],
            ],

            // =========================================================
            // 4. Pedicure & Manicure (خدمة العناية بالأقدام واليدين - Service 4)
            // =========================================================
            [
                'name' => 'منى فاروق عبد الغني',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01551234501',
                'id_number' => '29503120103789',
                'address' => 'شارع بيروت، مصر الجديدة، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['PEDICURE_MANICURE'],
            ],
            [
                'name' => 'أمينة حسني مرسي',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01551234502',
                'id_number' => '29608240104890',
                'address' => 'شارع الكوربة، مصر الجديدة، القاهرة',
                'avg_rating' => 4.9,
                'services' => ['PEDICURE_MANICURE'],
            ],
            [
                'name' => 'رحمة ماهر كمال',
                'gender' => 'female',
                'nationality' => 'المغرب',
                'phone_number' => '01551234503',
                'id_number' => '29712010105901',
                'address' => 'شارع التسعين الجنوبي، التجمع الخامس',
                'avg_rating' => 5.0,
                'services' => ['PEDICURE_MANICURE'],
            ],
            [
                'name' => 'رانيا شريف التوني',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01551234504',
                'id_number' => '29806150106012',
                'address' => 'شارع النصر، المعادي، القاهرة',
                'avg_rating' => 4.8,
                'services' => ['PEDICURE_MANICURE'],
            ],
            [
                'name' => 'دنيا مجدي زكي',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01551234505',
                'id_number' => '29901300107123',
                'address' => 'شارع سوريا، المهندسين، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['PEDICURE_MANICURE'],
            ],
            [
                'name' => 'خديجة بنجلون الفاسي',
                'gender' => 'female',
                'nationality' => 'المغرب',
                'phone_number' => '01551234506',
                'id_number' => '29604180108234',
                'address' => 'الحي المتميز، الشيخ زايد، الجيزة',
                'avg_rating' => 5.0,
                'services' => ['PEDICURE_MANICURE'],
            ],

            // =========================================================
            // 5. Deep Cleaning (خدمة النظافة العميقة - Service 5)
            // =========================================================
            [
                'name' => 'أحمد محمود حسن',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01060350801',
                'id_number' => '29402100109345',
                'address' => 'المحور المركزي، الحي السادس، 6 أكتوبر',
                'avg_rating' => 4.9,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'عبد الله محمد علي',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01060350802',
                'id_number' => '29505180101456',
                'address' => 'شارع فيصل الرئيسي، الجيزة',
                'avg_rating' => 4.8,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'خالد إبراهيم سالم',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01060350803',
                'id_number' => '29609250102567',
                'address' => 'شارع الهرم، محطة العريش، الجيزة',
                'avg_rating' => 5.0,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'عمر فاروق مصطفى',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01060350804',
                'id_number' => '29701140103678',
                'address' => 'البوابة الثانية، حدائق الأهرام، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'ماجد عبد الرحمن هاني',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01060350805',
                'id_number' => '29807200104789',
                'address' => 'شارع مصطفى النحاس، مدينة نصر',
                'avg_rating' => 4.8,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'يوسف سامي كمال',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01060350806',
                'id_number' => '29903150105890',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس',
                'avg_rating' => 4.9,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'محمد نور الرحمن',
                'gender' => 'male',
                'nationality' => 'بنغلاديش',
                'phone_number' => '01060350807',
                'id_number' => '29511080106901',
                'address' => 'شارع السودان، المهندسين، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['DEEP_CLEANING'],
            ],
            [
                'name' => 'رافي كمار سينغ',
                'gender' => 'male',
                'nationality' => 'الهند',
                'phone_number' => '01060350808',
                'id_number' => '29608120107012',
                'address' => 'شارع الدقي، الجيزة',
                'avg_rating' => 4.8,
                'services' => ['DEEP_CLEANING'],
            ],

            // =========================================================
            // 6. Specialized Services (6 to 11) - Teams & Technicians
            // =========================================================
            [
                'name' => 'حازم نبيل بدوي',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881101',
                'id_number' => '29506200108123',
                'address' => 'شارع عباس العقاد، مدينة نصر، القاهرة',
                'avg_rating' => 4.9,
                'services' => ['SOFA_CLEANING', 'CARPET_CLEANING'],
            ],
            [
                'name' => 'إسلام سعيد عاشور',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881102',
                'id_number' => '29610150109234',
                'address' => 'شارع مكرم عبيد، مدينة نصر، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['SOFA_CLEANING', 'CARPET_CLEANING'],
            ],
            [
                'name' => 'مصطفى كامل عبد الغني',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881103',
                'id_number' => '29704280101345',
                'address' => 'شارع الطيران، رابعة العدوية، مدينة نصر',
                'avg_rating' => 4.8,
                'services' => ['WATER_TANK_CLEANING'],
            ],
            [
                'name' => 'شريف كمال التوني',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881104',
                'id_number' => '29801120102456',
                'address' => 'شارع التسعين الجنوبي، التجمع الخامس',
                'avg_rating' => 4.9,
                'services' => ['WATER_TANK_CLEANING'],
            ],
            [
                'name' => 'سامح عبد السلام متولي',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881105',
                'id_number' => '29509050103567',
                'address' => 'شارع اللاسلكي، المعادي الجديدة',
                'avg_rating' => 4.9,
                'services' => ['STAIRS_SURFACES_CLEANING'],
            ],
            [
                'name' => 'وحيد فاروق حسنين',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881106',
                'id_number' => '29603180104678',
                'address' => 'شارع النصر، المعادي، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['STAIRS_SURFACES_CLEANING'],
            ],
            [
                'name' => 'تامر حسني فتحي',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881107',
                'id_number' => '29707240105789',
                'address' => 'شارع جامعة الدول العربية، المهندسين',
                'avg_rating' => 4.8,
                'services' => ['STAIRS_SURFACES_CLEANING'],
            ],
            [
                'name' => 'مدحت عبد اللطيف إبراهيم',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881108',
                'id_number' => '29811300106890',
                'address' => 'شارع البطل أحمد عبد العزيز، المهندسين',
                'avg_rating' => 4.9,
                'services' => ['AC_CLEANING'],
            ],
            [
                'name' => 'أشرف زكي سليمان',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881109',
                'id_number' => '29508190107901',
                'address' => 'شارع مصدق، الدقي، الجيزة',
                'avg_rating' => 5.0,
                'services' => ['AC_CLEANING'],
            ],
            [
                'name' => 'محمد شهيد عالم',
                'gender' => 'male',
                'nationality' => 'بنغلاديش',
                'phone_number' => '01099881110',
                'id_number' => '29612050108012',
                'address' => 'شارع التحرير، الدقي، الجيزة',
                'avg_rating' => 4.7,
                'services' => ['AC_CLEANING'],
            ],
            [
                'name' => 'كرم خان مسعود',
                'gender' => 'male',
                'nationality' => 'باكستان',
                'phone_number' => '01099881111',
                'id_number' => '29705140109123',
                'address' => 'الحي السابع، مدينة 6 أكتوبر',
                'avg_rating' => 4.8,
                'services' => ['CARPET_CLEANING'],
            ],
            [
                'name' => 'راميش باتيل شارما',
                'gender' => 'male',
                'nationality' => 'الهند',
                'phone_number' => '01099881112',
                'id_number' => '29809220101234',
                'address' => 'المحور المركزي، 6 أكتوبر',
                'avg_rating' => 4.8,
                'services' => ['CARPET_CLEANING'],
            ],
            [
                'name' => 'سيد عبد الرازق قاسم',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881113',
                'id_number' => '29504100102345',
                'address' => 'الحي المتميز، الشيخ زايد، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['CAR_WASH'],
            ],
            [
                'name' => 'علاء الدين حسني جاد',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881114',
                'id_number' => '29608150103456',
                'address' => 'شارع الشباب، الشيخ زايد، الجيزة',
                'avg_rating' => 5.0,
                'services' => ['CAR_WASH'],
            ],
            [
                'name' => 'وائل محمود السيد',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881115',
                'id_number' => '29702200104567',
                'address' => 'البوابة الأولى، حدائق الأهرام',
                'avg_rating' => 4.9,
                'services' => ['CAR_WASH'],
            ],
            [
                'name' => 'نبيل فكري عثمان',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881116',
                'id_number' => '29806120105678',
                'address' => 'شارع الهرم، الجيزة',
                'avg_rating' => 4.8,
                'services' => ['SOFA_CLEANING', 'STAIRS_SURFACES_CLEANING'],
            ],
            [
                'name' => 'صبري عبد الغفار خليل',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881117',
                'id_number' => '29510180106789',
                'address' => 'شارع فيصل، محطة المساحة، الجيزة',
                'avg_rating' => 4.9,
                'services' => ['WATER_TANK_CLEANING'],
            ],
            [
                'name' => 'عادل فتحي مصطفى',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01099881118',
                'id_number' => '29601250107890',
                'address' => 'شارع العروبة، مصر الجديدة، القاهرة',
                'avg_rating' => 5.0,
                'services' => ['AC_CLEANING', 'CAR_WASH'],
            ],

            // =========================================================
            // 7. Unassigned Active Workers (متاحين غير مسندين لأي خدمة)
            // =========================================================
            [
                'name' => 'هاجر نبيل رضوان',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01033445501',
                'id_number' => '29708150108901',
                'address' => 'شارع مصطفى النحاس، مدينة نصر، القاهرة',
                'avg_rating' => 4.9,
                'services' => [], // Unassigned
            ],
            [
                'name' => 'إسراء أحمد فتحي',
                'gender' => 'female',
                'nationality' => 'مصر',
                'phone_number' => '01033445502',
                'id_number' => '29803200109012',
                'address' => 'شارع التسعين الشمالي، التجمع الخامس',
                'avg_rating' => 4.8,
                'services' => [], // Unassigned
            ],
            [
                'name' => 'جريس كيبكويتش تشيبتوم',
                'gender' => 'female',
                'nationality' => 'كينيا',
                'phone_number' => '01033445503',
                'id_number' => '29909100101123',
                'address' => 'شارع 9، المعادي، القاهرة',
                'avg_rating' => 4.7,
                'services' => [], // Unassigned
            ],
            [
                'name' => 'عاطف إبراهيم درويش',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01033445504',
                'id_number' => '29406180102234',
                'address' => 'شارع جامعة الدول العربية، المهندسين',
                'avg_rating' => 4.9,
                'services' => [], // Unassigned
            ],
            [
                'name' => 'محمود كمال الشريف',
                'gender' => 'male',
                'nationality' => 'مصر',
                'phone_number' => '01033445505',
                'id_number' => '29511250103345',
                'address' => 'المحور المركزي، الحي الرابع، 6 أكتوبر',
                'avg_rating' => 4.8,
                'services' => [], // Unassigned
            ],
            [
                'name' => 'كفايت الله عبد الإسلام',
                'gender' => 'male',
                'nationality' => 'بنغلاديش',
                'phone_number' => '01033445506',
                'id_number' => '29607140104456',
                'address' => 'شارع مصدق، الدقي، الجيزة',
                'avg_rating' => 4.7,
                'services' => [], // Unassigned
            ],
        ];

        $imgIndex = 0;

        foreach ($workersData as $i => $data) {
            $serviceCodes = $data['services'];
            unset($data['services']);

            $worker = Worker::updateOrCreate(
                ['phone_number' => $data['phone_number']],
                array_merge($data, [
                    'status' => 'active',
                ])
            );

            // Sync assigned services
            $serviceIds = [];
            foreach ($serviceCodes as $code) {
                if (isset($services[$code])) {
                    $serviceIds[] = $services[$code]->id;
                }
            }
            $worker->services()->sync($serviceIds);

            // Attach worker image if exists and not already attached
            if (($hasImg1 || $hasImg2) && $worker->media()->count() === 0) {
                $sourceImage = ($imgIndex % 2 === 0) ? ($hasImg1 ? $image1 : $image2) : ($hasImg2 ? $image2 : $image1);
                $originalName = ($imgIndex % 2 === 0) ? '1.webp' : '2.webp';
                $imgIndex++;

                if (file_exists($sourceImage)) {
                    $targetDir = 'worker/image';
                    $fileName = 'worker_' . $worker->id . '_' . time() . '_' . $i . '.webp';
                    $targetPath = $targetDir . '/' . $fileName;

                    Storage::disk('public')->put($targetPath, file_get_contents($sourceImage));

                    $worker->media()->create([
                        'collection' => 'worker/image',
                        'file_name' => $fileName,
                        'mime_type' => 'image/webp',
                        'size' => filesize($sourceImage),
                        'disk' => 'public',
                        'path' => $targetPath,
                        'title' => $originalName,
                        'date' => now()->toDateString(),
                    ]);
                }
            }
        }

        $this->command->info('تم إنشاء وتحديث ' . count($workersData) . ' عاملاً وعاملة ببيانات حقيقية وأسماء عربية وتوزيعهم على الخدمات بنجاح.');
    }
}
