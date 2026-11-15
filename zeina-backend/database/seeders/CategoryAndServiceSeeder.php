<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Service;
use App\Models\ServiceZone;
use App\Models\User;
use App\Service\ServiceWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class CategoryAndServiceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first();

        // Fetch all Geo-Fenced Service Zones
        $allZoneIds = ServiceZone::pluck('id')->toArray();

        // قائمة الصور العشوائية للخدمات
        $dummyIconsList = ['icon_1.jpg', 'icon_2.jpg', 'icon_3.jpg', 'cover_1.jpg', 'cover_2.jpg', 'cover_3.jpeg'];

        // 1. تعريف الأقسام الرئيسية (Categories)
        $categoriesData = [
            'cleaning' => ['en' => 'Cleaning Services', 'ar' => 'خدمات التنظيف', 'icon' => 'cleanCategory.webp'],
            'babysitting' => ['en' => 'Nanny & Babysitting', 'ar' => 'رعاية الأطفال', 'icon' => 'Babysitting.webp'],
            'hospitality' => ['en' => 'Hospitality Services', 'ar' => 'خدمات الضيافة', 'icon' => 'Babysitting.webp'],
            'beauty' => ['en' => 'Beauty & Care', 'ar' => 'التجميل والعناية', 'icon' => 'Beauty&Spa.avif'],
            'maintenance' => ['en' => 'Home Maintenance', 'ar' => 'الصيانة المنزلية', 'icon' => 'Maintenance.avif'],
            'other' => ['en' => 'Other Services', 'ar' => 'خدمات أخرى', 'icon' => 'Beauty&Spa.avif'],
        ];

        $categories = [];
        foreach ($categoriesData as $key => $data) {
            $category = Category::firstOrCreate(
                ['name_en' => $data['en'], 'parent_id' => null],
                ['name_ar' => $data['ar'], 'status' => 'active', 'created_by' => $admin->id ?? null]
            );
            $categories[$key] = $category;

            // رفع الميديا للقسم
            $dummyIconPath = public_path('dummy/' . $data['icon']);
            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'category_' . $category->id . '_icon.' . $extension;
                $iconPath = 'categories/icons/' . $iconName;

                Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));

                $category->media()->updateOrCreate(
                    ['collection' => 'category/icon'],
                    [
                        'file_name' => $iconName,
                        'mime_type' => mime_content_type($dummyIconPath),
                        'disk' => 'public',
                        'path' => $iconPath,
                        'size' => Storage::disk('public')->size($iconPath),
                    ]
                );
            }
        }

        // 2. تعريف الخدمات المحددة (11 خدمة ببيانات واقعية متكاملة)
        $servicesData = [
            // 1. عاملة منزلية بالساعة
            [
                'cat' => 'cleaning',
                'type' => 'hourly',
                'en' => 'Domestic Helper (Hourly)',
                'ar' => 'عاملة منزلية بالساعة',
                'price' => 80,
                'period' => 2,
                'code' => 'DOMESTIC_HELPER_HOURLY',
                'img_num' => 1,
                'description_ar' => 'خدمة النظافة المنزلية بالساعة توفر لكِ عاملات مدربات على أعلى مستوى لإنجاز المهام المنزلية اليومية بكفاءة ودقة وسرعة، بما يمنحك منزلاً نظيفاً ومرتباً ويوفر وقتك وجهدك لراحتك وراحة أسرتك.',
                'description_en' => 'Hourly domestic helper service provides highly trained and experienced cleaners for your daily home chores, ensuring a sparkling clean and organized living space with complete peace of mind.',
                'features' => [
                    ['ar' => 'تنظيف وترتيب غرف النوم والمعيشة وتغيير المفارش', 'en' => 'Bedroom and living area cleaning, dusting, and bed tidying'],
                    ['ar' => 'غسيل وتعقيم الحمامات والمطابخ والأحواض', 'en' => 'Bathroom and kitchen sanitization, tile, and basin scrubbing'],
                    ['ar' => 'كنس ومسح الأرضيات وتطهير الأسطح والمرايا', 'en' => 'Floor vacuuming, wet mopping, and surface disinfection'],
                    ['ar' => 'كوي وترتيب الملابس وغسيل الأواني الخفيفة', 'en' => 'Clothes ironing, neat folding, and light dishwashing'],
                    ['ar' => 'تفريغ سلات المهملات وتغيير أكياس القمامة', 'en' => 'Emptying all waste bins and replacing trash liners'],
                ],
                'terms_ar' => "1. يلتزم العميل بتوفير مواد وأدوات النظافة المناسبة والآمنة للعمل داخل المنزل.\n2. مهام الخدمة تشمل التنظيف المنزلي الروتيني المعتاد ولا تشمل الأعمال الشاقة أو تنظيف الواجهات الخارجية المرتفعة.\n3. يمكن إلغاء أو إعادة جدولة الموعد مجاناً قبل 24 ساعة على الأقل من موعد الزيارة المحدد.\n4. نرجو حفظ المقتنيات الثمينة والأموال في أماكن مغلقة وآمنة قبل بدء الزيارة.",
                'terms_en' => "1. The client agrees to provide suitable and safe cleaning supplies and equipment.\n2. Tasks cover routine domestic cleaning and exclude heavy lifting or exterior high-rise window cleaning.\n3. Cancellations or rescheduling are free up to 24 hours prior to the scheduled visit.\n4. Please secure valuable items and cash in a safe place prior to the helper's arrival.",
            ],

            // 2. عاملة ضيافة بالساعة
            [
                'cat' => 'hospitality',
                'type' => 'hourly',
                'en' => 'Hospitality Worker (Hourly)',
                'ar' => 'عاملة ضيافة بالساعة',
                'price' => 90,
                'period' => 2,
                'code' => 'HOSPITALITY_WORKER_HOURLY',
                'img_num' => 2,
                'description_ar' => 'خدمة الضيافة الراقية للمناسبات والعزائم والاجتماعات العائلية والرسمية، مع كادر متخصص يتمتع بأعلى معايير اللباقة والأناقة وحسن الاستقبال وتقديم المشروبات والمأكولات للضيوف بحرفية تامة.',
                'description_en' => 'Premium hospitality service for private gatherings, events, and family receptions, featuring well-groomed and courteous staff skilled in welcoming guests and serving refreshments with elegance.',
                'features' => [
                    ['ar' => 'استقبال الضيوف والترحيب بهم بلباقة ومظهر مهني راقٍ', 'en' => 'Welcoming guests with courtesy and professional appearance'],
                    ['ar' => 'تحضير وتقديم القهوة والشاي والمشروبات الباردة والساخنة', 'en' => 'Preparing and serving tea, coffee, and fresh refreshments'],
                    ['ar' => 'تنسيق وترتيب صواني التقديم وبوفيه المأكولات والحلويات', 'en' => 'Arranging serving platters, sweet buffets, and finger food'],
                    ['ar' => 'متابعة احتياجات الضيوف طوال فترة المناسبة باحترافية', 'en' => 'Attending to guests needs promptly throughout the gathering'],
                    ['ar' => 'تنظيف وترتيب أدوات الضيافة بعد انتهاء التقديم', 'en' => 'Clearing, washing, and tidying silverware and dinnerware'],
                ],
                'terms_ar' => "1. يرجى توفير أدوات التقديم والصواني والمشروبات والمأكولات المطلوبة للضيافة.\n2. العاملة مسؤولة عن مهام الضيافة والتقديم والترتيب ولا تكلف بأعمال النظافة المنزلية الشاقة.\n3. يتم تحديد عدد عاملات الضيافة وفق عدد الضيوف المتوقع لضمان جودة وسرعة الخدمة.\n4. يشترط إشعار الإلغاء أو التعديل قبل الموعد بـ 24 ساعة على الأقل.",
                'terms_en' => "1. The host provides serving ware, platters, beverages, and food items.\n2. Staff members are dedicated solely to hospitality, serving, and tidying and must not be assigned heavy domestic chores.\n3. The recommended staff-to-guest ratio ensures optimal service quality.\n4. Free cancellation or rescheduling is available up to 24 hours before the reservation.",
            ],

            // 3. جليسة أطفال بالساعة
            [
                'cat' => 'babysitting',
                'type' => 'hourly',
                'en' => 'Babysitter (Hourly)',
                'ar' => 'جليسة اطفال بالساعة',
                'price' => 100,
                'period' => 3,
                'code' => 'BABYSITTER_HOURLY',
                'img_num' => 3,
                'description_ar' => 'رعاية موثوقة وآمنة لأطفالكِ داخل منزلك مع جليسات مؤهلات ومدربات على التعامل الإيجابي مع مختلف الفئات العمرية للأطفال، لتستمتعي براحة بالك وثقتكِ التامة بوجود أطفالك في أيدٍ أمينة.',
                'description_en' => 'Compassionate and dependable in-home child care by trained babysitters experienced in age-appropriate activities and safety, giving parents complete reassurance and peace of mind.',
                'features' => [
                    ['ar' => 'الإشراف التام على سلامة وأمان الطفل طوال مدة الزيارة', 'en' => 'Continuous safety monitoring and supervision of the child'],
                    ['ar' => 'مشاركة الطفل في الألعاب التفاعلية والأنشطة الترفيهية والقصص', 'en' => 'Engaging the child in interactive games, storytelling, and play'],
                    ['ar' => 'المساعدة في تناول الوجبات الخفيفة والالتزام بالجدول الغذائي', 'en' => 'Assisting with scheduled snacks and healthy meal times'],
                    ['ar' => 'المساعدة في روتين النوم والقيلولة والنظافة الشخصية', 'en' => 'Assisting with naptime routines and personal hygiene'],
                    ['ar' => 'إشعار الوالدين بأي ملاحظات فورية تخص صحة وراحة الطفل', 'en' => 'Providing prompt updates to parents regarding the child wellbeing'],
                ],
                'terms_ar' => "1. يجب توضيح أي احتياجات خاصة أو حالات حساسية أو أدوية للطفل مسبقاً قبل بدء الزيارة.\n2. لا تقوم الجليسة بإعطاء أدوية علاجية دون تفويض كتابي وإرشادات واضحة من ولي الأمر.\n3. الخدمة مخصصة لرعاية الأطفال ولا تشمل مهام التدبير المنزلي العامة.\n4. يجب تواجد شخص بالغ بالمنزل أو ترك بيانات اتصال مباشرة للطوارئ.",
                'terms_en' => "1. Parents must disclose any allergies, special needs, or medical conditions in advance.\n2. Medications will only be administered with explicit written parental instructions.\n3. The babysitter focuses exclusively on child supervision and not general housekeeping.\n4. Emergency contact information must be provided and accessible at all times.",
            ],

            // 4. خدمة العناية بالأقدام واليدين
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Pedicure & Manicure Service',
                'ar' => 'خدمة العناية بالأقدام واليدين',
                'price' => 150,
                'period' => 2,
                'code' => 'PEDICURE_MANICURE',
                'covered_persons_per_worker' => 1,
                'img_num' => 4,
                'description_ar' => 'تجربة سبا فاخرة للعناية باليدين والأقدام داخل راحة منزلك، باستخدام أدوات معقمة ذات استخدام فردي ومستحضرات تجميل وترطيب عالية الجودة تمنح أظافرك وبشرتك نعومة وإشراقة تدوم.',
                'description_en' => 'A luxurious salon-grade manicure and pedicure experience in the comfort of your home, utilizing single-use sterilized kits and nourishing botanical cosmetics for radiant, healthy nails.',
                'features' => [
                    ['ar' => 'أدوات معقمة ومغلفة ذات استخدام فردي لكل عميلة لضمان الأمان', 'en' => 'Individual sterilized tool kits opened right before each client'],
                    ['ar' => 'تقليم وتنسيق وبرد الأظافر بشكل متناسق ومتقن', 'en' => 'Professional nail trimming, shaping, and buffing'],
                    ['ar' => 'إزالة الزوائد الجلدية الميتة وتقشير لطيف لليدين والقدمين', 'en' => 'Cuticle care and gentle exfoliating scrub for hands and feet'],
                    ['ar' => 'مساج مرطب بالزيوت والمستحضرات الطبيعية لتنشيط الدورة الدموية', 'en' => 'Hydrating massage with essential oils to promote circulation'],
                    ['ar' => 'تنعيم كعب القدمين وإزالة التشققات والجلد الجاف', 'en' => 'Heel callus softening and smoothing treatment'],
                ],
                'terms_ar' => "1. الأخصائية مؤهلة للخدمات التجميلية والعناية ولا تقدم علاجات طبية أو جراحية لأمراض الأظافر المعدية.\n2. يرجى توفير مقعد مريح ومصدر ماء دافئ في مكان تقديم الخدمة.\n3. نضمن استخدام أدوات ومبارد معقمة ومغلفة مسبقاً لكل زيارة.\n4. يمكن الإلغاء أو التعديل قبل 24 ساعة من الموعد المحدد.",
                'terms_en' => "1. The specialist provides cosmetic grooming only and cannot treat clinical nail infections.\n2. Please provide a comfortable chair and access to warm water at the service location.\n3. Single-use sterilized tools are guaranteed for every single booking.\n4. Free cancellation or appointment changes are allowed up to 24 hours in advance.",
            ],

            // 5. خدمة النظافة العميقة
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Deep Cleaning Service',
                'ar' => 'خدمة النظافة العميقة',
                'price' => 500,
                'period' => 6,
                'code' => 'DEEP_CLEANING',
                'img_num' => 5,
                'description_ar' => 'نظافة شاملة ومتكاملة للمنازل والشقق والفلل بعد التشطيب أو قبل الانتقال أو دورياً، يقوم بها فريق مدرب مجهز بأحدث ماكينات ومواد النظافة لإعادة البريق والانتعاش لكافة أركان المنزل.',
                'description_en' => 'Thorough, top-to-bottom deep sanitization and detailing for apartments and villas, performed by an equipped specialized team tackling post-construction dust and deep grime.',
                'features' => [
                    ['ar' => 'تنظيف عميق وإزالة الدهون المستعصية بالمطبخ والشفاط والأجهزة', 'en' => 'Deep degreasing of kitchen surfaces, exhaust hoods, and appliances'],
                    ['ar' => 'فرك وتطهير بلاط وسيراميك وأطقم الحمامات بالبخار والمعقمات', 'en' => 'Scrubbing and descaling bathroom tiles, grout, and sanitary ware'],
                    ['ar' => 'تلميع الأرضيات السيراميك والبورسلين والرخام والباركيه', 'en' => 'Polishing ceramic, porcelain, marble, and wooden flooring'],
                    ['ar' => 'تنظيف النوافذ والشيش والأبواب ومجاري الألوميتال الداخلية', 'en' => 'Cleaning window glass, frames, flyscreens, and sliding tracks'],
                    ['ar' => 'إزالة الغبار والأتربة العميقة من خلف الأثاث وتحت الأسرّة', 'en' => 'Dusting hard-to-reach areas behind furniture and beneath beds'],
                    ['ar' => 'تعقيم المفاتيح والمقابض ومجاري التكييف والمناطق كثيفة اللمس', 'en' => 'Sterilizing door handles, light switches, and high-touch surfaces'],
                ],
                'terms_ar' => "1. يجب توفير مصدر ماء وكهرباء يعملان بصورة مستمرة في موقع العمل.\n2. الفريق مجهز بكافة المعدات والمواد الأساسية المطلوبة لإتمام التنظيف العميق.\n3. يتم فحص الموقع ومطابقة الحالة مع مواصفات الحجز قبل بدء العمل.\n4. في حالات الشقق حديثة التشطيب، يجب إخلاء الموقع من بقايا مواد البناء الصلبة والمهملات الإنشائية الكبيرة.",
                'terms_en' => "1. Continuous water and electrical supply must be available at the property.\n2. The specialized crew brings all necessary heavy-duty tools and eco-safe chemicals.\n3. Site inspection is conducted upon arrival to verify booking scope.\n4. Post-construction sites must be cleared of large rubble and construction debris before arrival.",
            ],

            // 6. تنظيف الكنب والمجالس
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Sofa Cleaning Service',
                'ar' => 'تنظيف الكنب',
                'price' => 0,
                'period' => 2,
                'code' => 'SOFA_CLEANING',
                'img_num' => 6,
                'description_ar' => 'خدمة غسيل وتعقيم الكنب والمجالس والمقاعد بأحدث تقنيات الحقن والشفط واستخلاص الرغوة، لإزالة البقع المستعصية والروائح الكريهة وحشرات الفراش مع تجفيف سريع خلال ساعات معدودة.',
                'description_en' => 'Professional deep upholstery and sofa extraction cleaning using specialized foam and steam technology to eliminate tough stains, allergens, and odors with rapid drying.',
                'features' => [
                    ['ar' => 'شفط الأتربة العميقة وعث الغبار من ألياف وأنسجة الكنب', 'en' => 'High-suction vacuuming to extract deep dust mites and allergens'],
                    ['ar' => 'معالجة البقع العنيدة (قهوة، شاي، أطعمة، حبر) بمذيبات مخصصة', 'en' => 'Spot-treating stubborn stains like coffee, oils, and grease'],
                    ['ar' => 'غسيل ميكانيكي بالرغوة الفعالة يحافظ على ألوان وجودة القماش', 'en' => 'Active foam wash preserving delicate fabric fibers and colors'],
                    ['ar' => 'شفط قوي للسوائل ومياه الغسيل لضمان تجفيف سريع خلال 2-4 ساعات', 'en' => 'Deep liquid extraction ensuring dry-to-use comfort within hours'],
                    ['ar' => 'تعقيم مضاد للبكتيريا ومسببات الحساسية وتعطير برائحة زكية', 'en' => 'Anti-bacterial sanitization and long-lasting fresh fragrance'],
                ],
                'terms_ar' => "1. يتم تحديد التكلفة بدقة بناءً على عدد المقاعد أو القطع المختارة في الحجز.\n2. يرجى تهوية الغرفة جيداً بعد انتهاء الغسيل للمساعدة على سرعة التجفيف الكامل.\n3. المواد المستخدمة آمنة تماماً على الأقمشة والأطفال والحيوانات الأليفة.\n4. البقع القديمة جداً أو التي سببت تلفاً في صبغة النسيج الأصلية قد لا تزول بالكامل وسيتم توضيح ذلك للعميل.",
                'terms_en' => "1. Pricing is strictly calculated according to the selected seating capacity/units.\n2. Ensure good room ventilation following the cleaning for prompt drying.\n3. Cleaning agents used are certified hypoallergenic and safe for children and pets.\n4. Pre-existing fiber discoloration or permanent dye damages may not be completely reversible.",
                'options' => [
                    ['name_ar' => 'كنب (مقعد شخص واحد)', 'name_en' => 'Single-Seat Armchair / Sofa', 'unit_price' => 120, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'كنب (مقعدين - شخصين)', 'name_en' => 'Two-Seater Loveseat', 'unit_price' => 220, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'كنب (3 مقاعد - 3 أشخاص)', 'name_en' => 'Three-Seater Standard Sofa', 'unit_price' => 320, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                    ['name_ar' => 'طقم صالون كامل (كنبة كبيرة + كنبة وسط + 2 فوتيه)', 'name_en' => 'Complete Salon Set (3-seat + 2-seat + 2 armchairs)', 'unit_price' => 750, 'min_qty' => 1, 'max_qty' => 5, 'status' => 'active', 'sort_order' => 4],
                ],
            ],

            // 7. تنظيف وتعقيم خزانات المياه
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Water Tank Cleaning Service',
                'ar' => 'تنظيف خزانات المياه',
                'price' => 0,
                'period' => 2,
                'code' => 'WATER_TANK_CLEANING',
                'img_num' => 7,
                'description_ar' => 'تنظيف وتطهير وتعقيم خزانات مياه الشرب العلوية والأرضية بواسطة فنيين مدربين باستخدام مواد تعقيم معتمدة صحياً تضمن نقاء وسلامة المياه وصحة عائلتك من الرواسب والبكتيريا.',
                'description_en' => 'Certified cleaning, sediment removal, and chlorine-based disinfection for residential ground and rooftop water storage tanks, guaranteeing potable water safety and hygiene.',
                'features' => [
                    ['ar' => 'سحب وتفريغ المياه وإزالة الطحالب والرواسب والرمال من قاع الخزان', 'en' => 'Draining and thoroughly removing mud, sediment, and silt from the base'],
                    ['ar' => 'فرك وتنظيف الجدران الداخلية والأسقف بمواد معقمة صديقة للبيئة', 'en' => 'Scrubbing inner walls and ceiling with approved sanitizing agents'],
                    ['ar' => 'تطهير شامل بنسب محددة من الكلور المعقم للقضاء على البكتيريا والجراثيم', 'en' => 'Full chlorine disinfection neutralizing waterborne microorganisms'],
                    ['ar' => 'فحص سلامة العوامة والغطاء وإحكام الإغلاق لمنع دخول الشوائب والحشرات', 'en' => 'Inspecting float valve, intake pipes, and airtight lid fitting'],
                    ['ar' => 'شطف الخزان عدة مرات بالماء النظيف للتأكد من خلوه من أي بقايا كيميائية', 'en' => 'Multiple fresh water rinses ensuring zero residual chemicals'],
                ],
                'terms_ar' => "1. يرجى غلق محبس تغذية الخزان الرئيسي قبل موعد الزيارة بساعتين لتقليل هدر المياه.\n2. يجب وجود منفذ دخول آمن وسهل إلى الخزان (فتحة فحص ملائمة).\n3. المواد المستخدمة في التعقيم مطابقة للمواصفات القياسية لمياه الشرب.\n4. ينصح بإعادة تعبئة الخزان بعد ساعة من انتهاء عملية التعقيم والشطف.",
                'terms_en' => "1. Please close the main intake valve 2 hours prior to the appointment to minimize water waste.\n2. Safe inspection access (hatch or ladder) to the tank must be available.\n3. Sanitizers used fully comply with municipal potable water health standards.\n4. It is recommended to refill the tank 1 hour after the sanitization and rinsing process.",
                'options' => [
                    ['name_ar' => 'خزان علوي صغير (حتى 1000 لتر)', 'name_en' => 'Small Overhead Tank (up to 1,000L)', 'unit_price' => 750, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'خزان علوي وسط (من 1000 إلى 2000 لتر)', 'name_en' => 'Medium Overhead Tank (1,000L - 2,000L)', 'unit_price' => 1000, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'خزان علوي كبير (أكثر من 2000 لتر)', 'name_en' => 'Large Overhead Tank (above 2,000L)', 'unit_price' => 1400, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                    ['name_ar' => 'خزان أرضي خرساني (حتى 5000 لتر)', 'name_en' => 'Ground Concrete Tank (up to 5,000L)', 'unit_price' => 1800, 'min_qty' => 1, 'max_qty' => 5, 'status' => 'active', 'sort_order' => 4],
                ],
            ],

            // 8. تنظيف السلالم والأسطح
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Cleaning the Stairs & Surfaces',
                'ar' => 'تنظيف السلالم والأسطح',
                'price' => 0,
                'period' => 2,
                'code' => 'STAIRS_SURFACES_CLEANING',
                'img_num' => 8,
                'description_ar' => 'خدمة متخصصة لتنظيف وتلميع سلالم ومداخل العمارات والأبراج السكنية والمصاعد والأسطح، للتخلص من الأتربة وبقايا البناء وآثار الأقدام ومنح مدخل عقارك مظهراً أنيقاً يشرف سكانه.',
                'description_en' => 'Comprehensive communal building cleaning for staircases, building lobbies, elevator interiors, and terrace surfaces, removing footprints, dust, and daily wear.',
                'features' => [
                    ['ar' => 'كنس وغسيل درجات السلالم والدرج من أعلى دور حتى المدخل الأرضي', 'en' => 'Sweeping, scrubbing, and mopping steps from the top floor down'],
                    ['ar' => 'تلميع درابزين السلالم المعدني والخشبي ومقابض النوافذ', 'en' => 'Polishing metal and wooden handrails, banisters, and balustrades'],
                    ['ar' => 'تنظيف وجلي أرضية المدخل الرئيسي والممرات ومسح واجهات الزجاج', 'en' => 'Washing and scrubbing entrance foyer floors and glass entrance doors'],
                    ['ar' => 'تنظيف وتطهير كابينة المصعد والمرايا وأزرار التحكم بالمعقمات', 'en' => 'Sanitizing elevator cabin floors, mirrors, and control panels'],
                    ['ar' => 'كنس وتنظيف الأسطح المشتركة وإزالة الأتربة والمهملات المتروكة', 'en' => 'Clearing rooftop surfaces of loose dirt, debris, and dust accumulation'],
                ],
                'terms_ar' => "1. يرجى تحديد عدد الأدوار بدقة في خيارات الحجز لضمان تخصيص الوقت والعمالة الكافية.\n2. يجب توفير مصدر مياه ومخرج تصريف مناسب في مبنى العقار.\n3. لا تشمل الخدمة نقل مخلفات البناء الإنشائية الثقيلة (الردش).\n4. يتم التنسيق مع حارس العقار أو ممثل السكان لفتح غرف الخدمات والأسطح.",
                'terms_en' => "1. Please specify the exact floor count in booking options for proper scheduling.\n2. Access to a water faucet and drainage is required on the premises.\n3. Heavy construction debris or concrete disposal is excluded.\n4. Coordination with the building concierge or tenants representative is required.",
                'options' => [
                    ['name_ar' => 'درج أرضي + 3 أدوار سكنية', 'name_en' => 'Ground + 3 Residential Floors Stairs', 'unit_price' => 350, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'درج أرضي + 4 أدوار سكنية', 'name_en' => 'Ground + 4 Residential Floors Stairs', 'unit_price' => 450, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'درج أرضي + 5 أدوار سكنية', 'name_en' => 'Ground + 5 Residential Floors Stairs', 'unit_price' => 550, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                    ['name_ar' => 'سطح عقار صغير (حتى 150 متر مربع)', 'name_en' => 'Small Rooftop Area (up to 150m²)', 'unit_price' => 500, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 4],
                    ['name_ar' => 'سطح عقار وسط (من 150 إلى 300 متر مربع)', 'name_en' => 'Medium Rooftop Area (150m² - 300m²)', 'unit_price' => 800, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 5],
                    ['name_ar' => 'سطح عقار كبير (أكثر من 300 متر مربع)', 'name_en' => 'Large Rooftop Area (above 300m²)', 'unit_price' => 1200, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 6],
                ],
            ],

            // 9. تنظيف المكيفات
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Air Conditioner Cleaning Service',
                'ar' => 'تنظيف المكيفات',
                'price' => 0,
                'period' => 2,
                'code' => 'AC_CLEANING',
                'img_num' => 9,
                'description_ar' => 'تنظيف وغسيل احترافي للمكيفات السبليت والشباك بمضخات الضغط العالي مع غطاء وقائي عازل لمنع اتساخ الجدران والأثاث، لرفع كفاءة التبريد وتوفير استهلاك الكهرباء وتنقية الهواء المنزلي.',
                'description_en' => 'Professional high-pressure coil wash and filter deep cleaning for split and window air conditioners using water catch-bags, maximizing cooling efficiency and indoor air quality.',
                'features' => [
                    ['ar' => 'فك وتنظيف فلاتر الهواء الداخلية وإزالة الأتربة والبكتيريا العالقة', 'en' => 'Dismantling and washing air mesh filters and decorative covers'],
                    ['ar' => 'غسيل ردياتير وملفات التبريد بمضخات المياه المخصصة ومنظفات كيميائية آمنة', 'en' => 'High-pressure water washing of cooling evaporator coils'],
                    ['ar' => 'تركيب غطاء واقٍ (كيس عازل) لجمع مياه الغسيل وحماية الأثاث والجدران', 'en' => 'Utilizing protective catch-bags to keep walls and floors completely dry'],
                    ['ar' => 'تسليك وتنظيف حوض ومجرى تصريف المياه لمنع الانسداد وتساقط الماء', 'en' => 'Clearing condensate drain lines and pans to prevent water leaks'],
                    ['ar' => 'فحص ضغط غاز الفريون وكفاءة التبريد وسرعة مروحة دفع الهواء', 'en' => 'Checking refrigerant pressure levels and fan blower rotation speeds'],
                ],
                'terms_ar' => "1. يرجى إيقاف تشغيل المكيف قبل موعد الفحص بنصف ساعة.\n2. يشمل السعر التنظيف والغسيل الوقائي؛ وفي حال وجود عطل كهربائي أو نقص فريون يتم التنسيق مع العميل قبل الإصلاح.\n3. نضمن نظافة وسلامة الجدران والأثاث المحيط بالمكيف باستخدام أغطية العزل.\n4. يوصى بعدم تشغيل المكيف إلا بعد مرور 30 دقيقة من انتهاء الغسيل والتجفيف.",
                'terms_en' => "1. Turn off the AC units 30 minutes before the arrival of the technician.\n2. Price covers preventive washing; refrigerant top-up or part replacement requires prior approval.\n3. Wall and furnishing safety is fully protected with sealed drain bags.\n4. Please wait 30 minutes after wash completion before switching on the AC unit.",
                'options' => [
                    ['name_ar' => 'مكيف شباك (تنظيف شامل)', 'name_en' => 'Window AC Unit (Deep Wash)', 'unit_price' => 250, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'مكيف سبليت (تنظيف كيميائي وبخار بالجراب العازل)', 'name_en' => 'Split AC Unit (Chemical & Catch-Bag Wash)', 'unit_price' => 350, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'عرض 3 مكيفات سبليت أو أكثر (خصم خاص)', 'name_en' => 'Package: 3 or more Split AC Units', 'unit_price' => 300, 'min_qty' => 3, 'max_qty' => 15, 'status' => 'active', 'sort_order' => 3],
                ],
            ],

            // 10. غسيل السجاد والموكيت
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Carpet & Rug Cleaning Service',
                'ar' => 'غسيل السجاد والموكيت',
                'price' => 0,
                'period' => 2,
                'code' => 'CARPET_CLEANING',
                'img_num' => 10,
                'description_ar' => 'غسيل عميق وتنظيف متطور للسجاد والموكيت بأحدث ماكينات الفرش الدوارة والشفط القوي، لإزالة أصعب البقع والروائح الكريهة وتطهير الأنسجة مع استعادة زهوة الألوان ونعومة الملمس.',
                'description_en' => 'Intensive on-site carpet and rug shampooing utilizing rotary scrubbing and dual-motor extraction, reviving color vibrancy and eliminating stubborn stains and embedded dust.',
                'features' => [
                    ['ar' => 'شفط جاف فائق القوة للرمال والأتربة المتغلغلة في عمق أنسجة السجاد', 'en' => 'Heavy-duty dry vacuuming to remove embedded deep-seated grit'],
                    ['ar' => 'فرك ميكانيكي بالفرش الدوارة وشامبو تنظيف عالي الفعالية', 'en' => 'Rotary brush scrubbing with specialized fabric-safe active shampoo'],
                    ['ar' => 'معالجة موضعية مكثفة للبقع الصعبة وآثار المشروبات والأطعمة', 'en' => 'Targeted stain removal for wine, food spills, oils, and pet marks'],
                    ['ar' => 'شفط فائق لمياه الغسيل والرغوة لتقليل زمن الجفاف بنسبة 80%', 'en' => 'High-powered moisture extraction enabling quick drying'],
                    ['ar' => 'تعقيم مضاد للبكتيريا وتعطير بزيوت عطرية منعشة تدوم طويلاً', 'en' => 'Hygienic anti-allergen treatment and luxurious deodorizing perfume'],
                ],
                'terms_ar' => "1. يتم حساب التكلفة بناءً على مقاسات السجاد والموكيت المختارة في الحجز.\n2. يفضل إخلاء الأغراض الصغيرة والقابلة للكسر من على السجاد قبل بدء العمل.\n3. يتم استخدام مواد غسيل صديقة للألياف تحافظ على صوف وحرير السجاد من البهتان.\n4. ينصح بالسير على السجاد بحذر أو تركه يجف تماماً لمدة 2 إلى 4 ساعات في بيئة جيدة التهوية.",
                'terms_en' => "1. Charges are based strictly on the rug/carpet dimensions chosen at booking.\n2. Please remove fragile decor items from the carpeted area prior to arrival.\n3. Detergents used protect delicate wool, synthetic, and silk weaves from color bleeding.\n4. Allow 2 to 4 hours in a well-ventilated area for full drying before heavy foot traffic.",
                'options' => [
                    ['name_ar' => 'سجادة صغيرة (حتى 2 × 2 متر)', 'name_en' => 'Small Rug (up to 2x2m)', 'unit_price' => 180, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'سجادة وسط (حتى 2 × 3 متر)', 'name_en' => 'Medium Carpet (up to 2x3m)', 'unit_price' => 260, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'سجادة كبيرة (حتى 3 × 4 متر)', 'name_en' => 'Large Carpet (up to 3x4m)', 'unit_price' => 380, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 3],
                    ['name_ar' => 'سجادة جامبو أو موكيت صالة (حتى 4 × 5 متر)', 'name_en' => 'Jumbo Rug / Living Room Carpet (up to 4x5m)', 'unit_price' => 550, 'min_qty' => 1, 'max_qty' => 10, 'status' => 'active', 'sort_order' => 4],
                ],
            ],

            // 11. غسيل وتلميع السيارات المتنقل
            [
                'cat' => 'other',
                'type' => 'other',
                'en' => 'Car Wash Service',
                'ar' => 'غسيل السيارات',
                'price' => 0,
                'period' => 2,
                'code' => 'CAR_WASH',
                'img_num' => 11,
                'description_ar' => 'خدمة غسيل وتلميع السيارات المتنقلة أمام منزلك أو مقر عملك، بمعدات متكاملة ورغوة نانو فعالة ومناشف مايكروفايبر تحمي طلاء سيارتك وتمنحها نظافة ولمعاناً لا مثيل لهما داخلياً وخارجياً.',
                'description_en' => 'Convenient doorstep mobile car wash and interior detailing at your home or office, using high-foaming nano shampoos, scratch-free microfibers, and tire care.',
                'features' => [
                    ['ar' => 'غسيل خارجي احترافي برغوة الشامبو النانو والماء المضغوط لحماية الطلاء', 'en' => 'Touchless nano-foam exterior pressure wash protecting clear-coat shine'],
                    ['ar' => 'شفط أتربة المقصورة الداخلية بالكامل والشنطة الخلفية وأسفل المقاعد', 'en' => 'Thorough vacuuming of cabin upholstery, boot/trunk, and floor mats'],
                    ['ar' => 'تلميع وتنظيف لوحة القيادة (الطبلون) والأبواب والكونسول بمواد عازلة للأتربة', 'en' => 'Dust-repellent cleaning and conditioning of dashboard and trims'],
                    ['ar' => 'تنظيف وتلميع الجنوط والإطارات وتطبيق ملمع وحامي الكاوتشوك الأسود', 'en' => 'Rim degreasing, brake-dust removal, and deep-black tire dressing'],
                    ['ar' => 'تلميع الزجاج الداخلي والخارجي بمانع الضباب وتعطير فاخر للمقصورة', 'en' => 'Streak-free window cleaning and interior fragrance spritz'],
                ],
                'terms_ar' => "1. يرجى توفير مساحة مناسبة لوقوف سيارة الخدمة بجوار سيارتك لتسهيل العمل.\n2. نرجو إفراغ السيارة من الأغراض الشخصية والمقتنيات الثمينة والأوراق المهمة.\n3. الخدمة تشمل الغسيل والتلميع العادي للمقصورة والطلاء الخارجي ولا تشمل غسيل الموتور بالماء.\n4. خيارات الحجم تشمل السيارات الصغيرة (سيدان)، والوسط (كروس أوفر)، والكبيرة (SUV/دفع رباعي).",
                'terms_en' => "1. Please provide safe and legal parking space for our mobile service unit.\n2. Please remove personal valuables, wallet, and crucial documents from the vehicle.\n3. Standard detailing covers exterior paint and interior cabin; engine bay pressure wash is excluded.\n4. Vehicle size categories: Small (Sedan/Hatchback), Medium (Crossover), Large (Full-size SUV/Truck).",
                'options' => [
                    ['name_ar' => 'سيارة صغيرة (سيدان / هاتشباك) - غسيل كامل داخلي وخارجي', 'name_en' => 'Small Car (Sedan / Hatchback) - Full In & Out Wash', 'unit_price' => 250, 'min_qty' => 1, 'max_qty' => 5, 'status' => 'active', 'sort_order' => 1],
                    ['name_ar' => 'سيارة وسط (كروس أوفر / SUV متوسطة) - غسيل كامل داخلي وخارجي', 'name_en' => 'Medium Car (Crossover / Mid-size SUV) - Full In & Out', 'unit_price' => 350, 'min_qty' => 1, 'max_qty' => 5, 'status' => 'active', 'sort_order' => 2],
                    ['name_ar' => 'سيارة كبيرة (دفع رباعي 7 راكب / بيك أب) - غسيل كامل داخلي وخارجي', 'name_en' => 'Large SUV / 7-Seater / Truck - Full In & Out Wash', 'unit_price' => 450, 'min_qty' => 1, 'max_qty' => 5, 'status' => 'active', 'sort_order' => 3],
                ],
            ],
        ];

        // 3. إدخال الخدمات وتركيب القواعد والأسعار والمناطق
        foreach ($servicesData as $index => $data) {
            $defaultFeatures = [
                ['en' => 'Dusting All Surfaces', 'ar' => 'مسح وتنظيف جميع الأسطح والمرايا'],
                ['en' => 'Floor Cleaning & Mopping', 'ar' => 'كنس ومسح وتطهير الأرضيات بعناية'],
                ['en' => 'Sanitization & Disinfection', 'ar' => 'تعقيم شامل بأفضل المنظفات والمطهرات'],
            ];

            $isFirstService = ($data['code'] === 'DOMESTIC_HELPER_HOURLY');
            $isSecondService = ($data['code'] === 'HOSPITALITY_WORKER_HOURLY');
            $isThirdService = ($data['code'] === 'BABYSITTER_HOURLY');
            $isPedicureManicure = ($data['code'] === 'PEDICURE_MANICURE');
            $isDeepCleaning = ($data['code'] === 'DEEP_CLEANING');
            $isOtherNewService = in_array($data['code'], ['SOFA_CLEANING', 'WATER_TANK_CLEANING', 'STAIRS_SURFACES_CLEANING', 'AC_CLEANING', 'CARPET_CLEANING', 'CAR_WASH']);

            // Construct payload matching StoreServiceRequest structure exactly
            $payload = [
                'category_id' => $categories[$data['cat']]->id,
                'service_code' => $data['code'],
                'type' => $data['type'],
                'name_ar' => $data['ar'],
                'name_en' => $data['en'],
                'description_ar' => $data['description_ar'] ?? ('وصف تفصيلي لخدمة ' . $data['ar']),
                'description_en' => $data['description_en'] ?? ('Detailed description for ' . $data['en']),
                'features' => $data['features'] ?? $defaultFeatures,
                'status' => 'active',
                'is_team_based' => $isOtherNewService,

                'available_visit_types' => ($isSecondService || $isThirdService || $isPedicureManicure || $isDeepCleaning || $isOtherNewService)
                    ? [['type' => 'single_visit', 'discount' => 0]]
                    : [
                        ['type' => 'single_visit', 'discount' => 0],
                        ['type' => '3_days_per_week', 'discount' => 15],
                    ],
                'base_price' => $data['price'],
                'start_time' => '08:00',
                'end_time' => '20:00',
                'default_duration' => ($isSecondService || $isThirdService) ? 4 : $data['period'],
                'has_duration_range' => ($isSecondService || $isThirdService),
                'min_duration' => ($isSecondService || $isThirdService) ? 4 : null,
                'max_duration' => ($isSecondService || $isThirdService) ? 6 : null,
                'min_workers' => $isDeepCleaning ? 6 : ($isOtherNewService ? 3 : 1),
                'max_workers' => $isDeepCleaning ? 6 : ($isOtherNewService ? 3 : 6),
                'min_children' => $isThirdService ? 1 : null,
                'max_children' => $isThirdService ? 6 : null,
                'covered_persons_per_worker' => $data['covered_persons_per_worker'] ?? null,

                'service_zone_ids' => $allZoneIds,

                'working_days' => ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'saturday'],
                'shifts' => ($isSecondService || $isThirdService || $isPedicureManicure) ? [] : [
                    ['start_time' => '08:00'],
                    ['start_time' => '14:00'],
                ],

                'terms_ar' => $data['terms_ar'] ?? "1. يجب توفير بيئة عمل آمنة ومناسبة للعمالة.\n2. لا يحق للعميل تكليف العمال بأي أعمال خارج نطاق الخدمة المتفق عليها.\n3. يجب إبلاغ الشركة بأي تغييرات في موعد الخدمة قبل 24 ساعة على الأقل.\n4. الشركة غير مسؤولة عن فقدان المقتنيات الثمينة غير المصرح عنها.",
                'terms_en' => $data['terms_en'] ?? "1. A safe and suitable working environment must be provided for the worker.\n2. The client is not entitled to assign any tasks to the worker outside the scope of the agreed service.\n3. The company must be notified of any changes to the service schedule at least 24 hours in advance.\n4. The company is not responsible for the loss of undeclared valuable items.",

                'rules' => [
                    'allow_cancel' => true,
                    'free_cancel_cutoff_hours' => 24,
                    'allow_reschedule' => true,
                    'reschedule_cutoff_hours' => 24,
                    'allow_same_day' => false,
                    'max_advance_booking_days' => 30,
                    'arrival_buffer_minutes' => 15,
                    'late_arrival_grace_minutes' => 15,
                ],
                'points_redemption_enabled' => true,
                'points_redemption_method' => 'price_based',
                'points_required_fixed' => 1000,
                'points_conversion_rate' => 10,
            ];

            if (isset($data['options'])) {
                $payload['options'] = $data['options'];
            }

            if ($isFirstService || $isThirdService) {
                $payload['worker_gender_options'] = ['Female'];
                $payload['pricing_type'] = 'per_hour';
            } elseif ($isSecondService) {
                $payload['worker_gender_options'] = ['Female', 'Male'];
                $payload['pricing_type'] = 'per_hour';
            } elseif ($isPedicureManicure) {
                $payload['worker_gender_options'] = ['Female'];
                $payload['pricing_type'] = 'per_service';
            } else {
                $payload['worker_gender_options'] = ['Male'];
                $payload['pricing_type'] = 'per_service';
            }

            if ($isThirdService) {
                $payload['child_age_groups'] = [
                    [
                        'name_ar' => 'حديثي الولادة إلى سنتين',
                        'name_en' => 'Newborn to 2 Years',
                        'age_from' => 0,
                        'age_to' => 24,
                        'calculation_type' => 'ratio_based',
                        'children_count' => 1,
                        'required_worker_count' => 1,
                        'status' => 'active',
                        'note' => '1 babysitter for each child (مربية واحدة لكل طفل)',
                    ],
                    [
                        'name_ar' => 'من 2 إلى 7 سنوات',
                        'name_en' => '2 to 7 Years',
                        'age_from' => 25,
                        'age_to' => 84,
                        'calculation_type' => 'ratio_based',
                        'children_count' => 2,
                        'required_worker_count' => 1,
                        'status' => 'active',
                        'note' => '1 babysitter for every 2 children (1 جليسة لكل 2 أطفال)',
                    ]
                ];
            }

            // Write service and sync its relations using the ServiceWriter class
            $service = Service::where('name_en', $data['en'])->first();
            if ($service) {
                $service = app(ServiceWriter::class)->update($service, $payload);
            } else {
                $service = app(ServiceWriter::class)->create($payload);
            }

            // Sync media icon
            $imgNum = $data['img_num'] ?? ($index + 1);
            $dummyIconPath = public_path("assist/services/{$imgNum}.png");
            if (!file_exists($dummyIconPath)) {
                $dummyIconPath = public_path("assist/services/{$imgNum}.webp");
            }
            if (!file_exists($dummyIconPath)) {
                $selectedIcon = Arr::random($dummyIconsList);
                $dummyIconPath = public_path('dummy/' . $selectedIcon);
            }

            if (file_exists($dummyIconPath)) {
                $extension = pathinfo($dummyIconPath, PATHINFO_EXTENSION);
                $iconName = 'service_' . $service->id . '_icon.' . $extension;
                $iconPath = 'services/icons/' . $iconName;

                Storage::disk('public')->put($iconPath, file_get_contents($dummyIconPath));

                $service->media()->updateOrCreate(
                    ['collection' => 'service/icon'],
                    [
                        'file_name' => $iconName,
                        'mime_type' => mime_content_type($dummyIconPath),
                        'disk' => 'public',
                        'path' => $iconPath,
                        'size' => Storage::disk('public')->size($iconPath),
                    ]
                );
            }
        }
        $this->command->info('Categories and Services seeded successfully with comprehensive fallback city prices and custom area scopes!');
    }
}
