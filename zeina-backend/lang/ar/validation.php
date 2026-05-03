<?php 

return [

 'login' => [
      //   'mobile_number_required' => 'رقم الهاتف مطلوب.',
      //   'mobile_number_numeric' => 'رقم الهاتف يجب أن يكون رقمي.',
      //   'invalid_mobile' => 'رقم الهاتف غير مسجل.',
      //   'otp_required' => 'الرمز السري المؤقت (OTP) مطلوب.',
      //   'otp_numeric' => 'الرمز السري المؤقت (OTP) يجب أن يكون رقمي.',
      'phone_required' => 'رقم الجوال مطلوب لتسجيل الدخول.',
      'phone_string'   => 'صيغة رقم الجوال غير صحيحة.',
      'phone_min'      => 'رقم الجوال يجب أن يتكون من 9 أرقام على الأقل.',
      'phone_max'      => 'رقم الجوال يجب أن لا يتجاوز 15 رقماً.',
      'account_inactive' => 'عفواً، حسابك غير مفعل. يرجى التواصل مع الدعم الفني.',
      'logged_in_successfully' => 'تم تسجيل الدخول بنجاح.',
      'registered_successfully' => 'تم إنشاء الحساب وتسجيل الدخول بنجاح.',
    ],

     'sendotp' => [
      'mobile_number_required' => 'رقم الهاتف مطلوب.',
      'otp_required' => 'رقم التحقق مطلوب.',
    ],

    'profile'=>[
        'email_required' => 'البريد الإلكتروني مطلوب.',
        'email_email' => 'يجب أن يكون البريد الإلكتروني بصيغة صحيحة.',
        'email_max' => 'البريد الإلكتروني لا يجب أن يتجاوز 100 حرف.',
        'email_unique' => 'البريد الإلكتروني مستخدم بالفعل.',
        'image_image' => 'الملف يجب أن يكون صورة.',
        'id_number_required' => 'رقم الهوية مطلوب.',
        'id_number_string' => 'رقم الهوية يجب أن يكون نصي.',
        'id_number_max' => 'رقم الهوية لا يجب أن يتجاوز 50 حرف.',
        'id_number_unique' => 'رقم الهوية مستخدم بالفعل.',
        'image_mimes' => 'الصورة يجب أن تكون من نوع jpeg, png, jpg.',
        'image_max' => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت.',
        'gender_required' => 'الجنس مطلوب.',
        'gender_in' => 'الجنس يجب أن يكون ذكر أو أنثى.',
    ],
   'address' => [
    'area_required'         => 'المنطقة مطلوبة.',
    'area_invalid'          => 'المنطقة المحددة غير صالحة.',
    'location_required'     => 'يرجى تحديد الموقع على الخريطة.',
    'street_required'       => 'اسم الشارع مطلوب.',
    'contact_name_required' => 'اسم المستلم مطلوب.',
    'phone_required'        => 'رقم الهاتف مطلوب.',
],

    'delete_account' => [
        'otp_required' => 'الرمز السري المؤقت (OTP) مطلوب.',
        'otp_numeric' => 'الرمز السري المؤقت (OTP) يجب أن يكون رقمي.',
        'otp_digits' => 'الرمز السري المؤقت (OTP) يجب أن يتكون من 6 أرقام.',
        'reason_string' => 'سبب الحذف يجب أن يكون نصي.',
        'reason_in' => 'سبب الحذف غير صالح.',
        'other_reason_required_if' => 'تفاصيل السبب الأخرى مطلوبة عندما يكون السبب "أخرى".',
        'other_reason_string' => 'تفاصيل السبب الأخرى يجب أن تكون نصية.',
        'other_reason_max' => 'تفاصيل السبب الأخرى لا يجب أن تتجاوز 250 حرف.',
        'confirm_required' => 'تأكيد الحذف مطلوب.',
        'confirm_accepted' => 'يجب قبول تأكيد الحذف.',
    ],

    'role' =>[
        'name_required' => 'اسم الدور مطلوب.',
        'name_string' => 'اسم الدور يجب أن يكون نصي.',
        'name_max' => 'اسم الدور لا يجب أن يتجاوز 100 حرف.',
        'name_unique' => 'اسم الدور مستخدم بالفعل.',
        'code_required' => 'كود الدور مطلوب.',
        'code_string' => 'كود الدور يجب أن يكون نصي.',
        'code_max' => 'كود الدور لا يجب أن يتجاوز 100 حرف.',
        'code_unique' => 'كود الدور مستخدم بالفعل.',
        'description_string' => 'وصف الدور يجب أن يكون نصي.',
        'description_max' => 'وصف الدور لا يجب أن يتجاوز 200 حرف.',
        'status_required' => 'حالة الدور مطلوبة.',
        'status_in' => 'حالة الدور يجب أن تكون إما "active" أو "inactive".',
        'permissions_required' => 'الصلاحيات مطلوبة.',
        'permissions_array' => 'الصلاحيات يجب أن تكون مصفوفة.',
        'permissions_min' => 'يجب تحديد صلاحية واحدة على الأقل.',
        'permissions_exists' => 'إحدى الصلاحيات المحددة غير موجودة في النظام.',
    ],

    'update_user' => [
        'status_in' => 'الحالة يجب أن تكون إما "active" أو "inactive".',
        'roles_array' => 'الأدوار يجب أن تكون مصفوفة.',
        'roles_exists' => 'إحدى الأدوار المحددة غير موجودة في النظام.',
     ],

     'reset_password' => [
        'password_required' => 'كلمة المرور الجديدة مطلوبة.',
        'password_string' => 'كلمة المرور الجديدة يجب أن تكون نصية.',
        'password_min' => 'كلمة المرور الجديدة يجب أن تكون على الأقل 8 أحرف.',
        'password_confirmed' => 'تأكيد كلمة المرور لا يتطابق مع كلمة المرور الجديدة.',
     ],

     'store_citry' => [
        'country_id_required' => 'معرف الدولة مطلوب.',
        'country_id_exists' => 'الدولة المحددة غير موجودة.',
        'name_ar_required' => 'الاسم العربي مطلوب.',
        'name_ar_string' => 'الاسم العربي يجب أن يكون نصي.',
        'name_ar_max' => 'الاسم العربي لا يجب أن يتجاوز 100 حرف.',
        'name_en_required' => 'الاسم الإنجليزي مطلوب.',
        'name_en_string' => 'الاسم الإنجليزي يجب أن يكون نصي.',
        'name_en_max' => 'الاسم الإنجليزي لا يجب أن يتجاوز 100 حرف.',
        'code_required' => 'الكود مطلوب.',
        'code_string' => 'الكود يجب أن يكون نصي.',
        'code_max' => 'الكود لا يجب أن يتجاوز 20 حرف.',
        'code_unique' => 'الكود مستخدم بالفعل في نفس الدولة.',
        'status_required' => 'الحالة مطلوبة.',
        'status_in' => 'الحالة يجب أن تكون إما "active" أو "inactive".',
     ],

    'store_country' => [
        'name_ar_required'       => 'الاسم باللغة العربية مطلوب.',
        'name_ar_string'         => 'الاسم باللغة العربية يجب أن يكون نصاً.',
        'name_ar_max'            => 'الاسم باللغة العربية يجب ألا يتجاوز 100 حرف.',
        
        'name_en_required'       => 'الاسم باللغة الإنجليزية مطلوب.',
        'name_en_string'         => 'الاسم باللغة الإنجليزية يجب أن يكون نصاً.',
        'name_en_max'            => 'الاسم باللغة الإنجليزية يجب ألا يتجاوز 100 حرف.',
        
        'code_required'          => 'كود الدولة مطلوب.',
        'code_string'            => 'كود الدولة يجب أن يكون نصاً.',
        'code_max'               => 'كود الدولة يجب ألا يتجاوز 20 حرفاً.',
        'code_unique'            => 'كود الدولة مسجل مسبقاً، يجب أن يكون جديداً كلياً.',
        
        'status_in'              => 'الحالة يجب أن تكون إما نشط (active) أو غير نشط (inactive).',
        
        'currency_code_required' => 'كود العملة مطلوب.',
        'currency_code_string'   => 'كود العملة يجب أن يكون نصاً.',
        'currency_code_max'      => 'كود العملة يجب ألا يتجاوز 10 أحرف.',
        
        'phone_code_string'      => 'كود الهاتف يجب أن يكون نصاً.',
        'phone_code_max'         => 'كود الهاتف يجب ألا يتجاوز 10 أحرف.',
        
        'flag_image'             => 'يجب أن يكون العلم صورة.',
        'flag_mimes'             => 'يجب أن يكون العلم بملف من نوع: jpeg, png, jpg, svg, webp.',
        'flag_max'               => 'حجم صورة العلم يجب ألا يتجاوز 1024 كيلوبايت (1 ميجابايت).',
    ],

     'store_area' => [
        'city_id_required' => 'معرف المدينة مطلوب.',
        'city_id_exists' => 'المدينة المحددة غير موجودة.',
        'name_ar_required' => 'الاسم العربي مطلوب.',
        'name_ar_string' => 'الاسم العربي يجب أن يكون نصي.',
        'name_ar_max' => 'الاسم العربي لا يجب أن يتجاوز 100 حرف.',
        'name_en_required' => 'الاسم الإنجليزي مطلوب.',
        'name_en_string' => 'الاسم الإنجليزي يجب أن يكون نصي.',
        'name_en_max' => 'الاسم الإنجليزي لا يجب أن يتجاوز 100 حرف.',
        'code_required' => 'الكود مطلوب.',
        'code_string' => 'الكود يجب أن يكون نصي.',
        'code_max' => 'الكود لا يجب أن يتجاوز 20 حرف.',
        'code_unique' => 'الكود مستخدم بالفعل.',
        // 'status_required' => 'الحالة مطلوبة.',
        'status_in' => 'الحالة يجب أن تكون إما "active" أو "inactive".',
     ], 


      'create_category' => [
        'parent_id_exists' => 'القسم الرئيسي المحدد غير موجود.',
        'parent_id_not_in' => 'خطأ: لا يمكن أن يكون القسم فرعياً من نفسه.',
        
        'name_ar_required' => 'اسم القسم باللغة العربية مطلوب.',
        'name_ar_string'   => 'اسم القسم باللغة العربية يجب أن يكون نصاً.',
        'name_ar_max'      => 'اسم القسم باللغة العربية يجب ألا يتجاوز 100 حرف.',
        'name_ar_unique'   => 'اسم القسم باللغة العربية مسجل مسبقاً.',
        
        'name_en_required' => 'اسم القسم باللغة الإنجليزية مطلوب.',
        'name_en_string'   => 'اسم القسم باللغة الإنجليزية يجب أن يكون نصاً.',
        'name_en_max'      => 'اسم القسم باللغة الإنجليزية يجب ألا يتجاوز 100 حرف.',
        'name_en_unique'   => 'اسم القسم باللغة الإنجليزية مسجل مسبقاً.',
        
        'image_image'      => 'الملف المرفق يجب أن يكون صورة.',
        'image_mimes'      => 'يجب أن تكون الصورة بأحد الصيغ التالية: jpeg, png, jpg, svg, webp.',
        'image_max'        => 'يجب ألا يتجاوز حجم الصورة 2 ميجابايت.',
        
        'sort_order_integer'=> 'ترتيب العرض يجب أن يكون رقماً صحيحاً.',
        'sort_order_min'   => 'ترتيب العرض لا يمكن أن يكون أقل من 0.',
        
        'status_in'        => 'الحالة المحددة غير صالحة.',
    ],

    'update_category' => [
        'parent_id_exists' => 'القسم المختار غير موجود أو أنه قسم فرعي (يجب اختيار قسم رئيسي فقط).',
        'parent_id_not_in' => 'خطأ: لا يمكن أن يكون القسم فرعياً من نفسه.',
        
        'name_ar_required' => 'اسم القسم باللغة العربية مطلوب.',
        'name_ar_string'   => 'اسم القسم باللغة العربية يجب أن يكون نصاً.',
        'name_ar_max'      => 'اسم القسم باللغة العربية يجب ألا يتجاوز 100 حرف.',
        'name_ar_unique'   => 'اسم القسم باللغة العربية مسجل مسبقاً لقسم آخر.',
        
        'name_en_required' => 'اسم القسم باللغة الإنجليزية مطلوب.',
        'name_en_string'   => 'اسم القسم باللغة الإنجليزية يجب أن يكون نصاً.',
        'name_en_max'      => 'اسم القسم باللغة الإنجليزية يجب ألا يتجاوز 100 حرف.',
        'name_en_unique'   => 'اسم القسم باللغة الإنجليزية مسجل مسبقاً لقسم آخر.',
        
        'image_image'      => 'الملف المرفق يجب أن يكون صورة.',
        'image_mimes'      => 'يجب أن تكون الصورة بأحد الصيغ التالية: jpeg, png, jpg, svg, webp.',
        'image_max'        => 'يجب ألا يتجاوز حجم الصورة 2 ميجابايت.',
        
        'sort_order_integer'=> 'ترتيب العرض يجب أن يكون رقماً صحيحاً.',
        'sort_order_min'   => 'ترتيب العرض لا يمكن أن يكون أقل من 0.',
        
        'status_in'        => 'الحالة المحددة غير صالحة.',
    ],

   'create_service' => [
    // 1. البيانات الأساسية (Basic Data)
    'category_id_required' => 'تصنيف الخدمة مطلوب.',
    'category_id_exists'   => 'تصنيف الخدمة المحدد غير موجود.',
    
    'type_required' => 'نوع الخدمة مطلوب.',
    'type_in'       => 'نوع الخدمة يجب أن يكون: بالساعة، مقيمة، أو كلاهما.',
    
    'name_ar_required' => 'اسم الخدمة بالعربية مطلوب.',
    'name_ar_string'   => 'اسم الخدمة بالعربية يجب أن يكون نصاً.',
    'name_ar_max'      => 'اسم الخدمة بالعربية يجب ألا يتجاوز 100 حرف.',
    
    'name_en_required' => 'اسم الخدمة بالإنجليزية مطلوب.',
    'name_en_string'   => 'اسم الخدمة بالإنجليزية يجب أن يكون نصاً.',
    'name_en_max'      => 'اسم الخدمة بالإنجليزية يجب ألا يتجاوز 100 حرف.',

    'workers_required'         => 'مطلوب وجود عامل واحد على الأقل.',
    'workers_array'            => 'يجب أن يكون العمال مصفوفة.',
    'workers.min'              => 'مطلوب وجود عامل واحد على الأقل.',
    'workers.*.integer'       => 'يجب أن يكون كل معرف لعامل رقماً صحيحاً.',
    'workers.*.exists'        => 'العامل المحدد غير موجود.',

    'description_ar_string' => 'الوصف بالعربية يجب أن يكون نصاً.',
    'description_en_string' => 'الوصف بالإنجليزية يجب أن يكون نصاً.',
    
    'status_in' => 'حالة الخدمة المحددة غير صحيحة.',

    // 2. المدد (Durations)
    'duration_required' => 'بيانات مدة الخدمة مطلوبة.',
    'duration_array'    => 'بيانات مدة الخدمة يجب أن تكون مصفوفة.',
    
    'allowed_durations_required_if' => 'المدد المسموحة مطلوبة للخدمات بالساعة.',
    'allowed_durations_array'       => 'المدد المسموحة يجب أن تكون مصفوفة.',
    'allowed_durations_numeric'     => 'يجب أن تكون كل مدة مسموحة عبارة عن رقم.',
    
    'min_duration_hours_required_if' => 'الحد الأدنى لعدد الساعات مطلوب للخدمات بالساعة.',
    'min_duration_hours_numeric'     => 'الحد الأدنى لعدد الساعات يجب أن يكون رقماً.',
    'min_duration_hours_lte'         => 'الحد الأدنى لعدد الساعات يجب أن يكون أقل من أو يساوي أصغر مدة مسموحة.',
    
    'allowed_contract_durations_required_if' => 'مدد العقد المسموحة مطلوبة لخدمات مقيمة.',
    'allowed_contract_durations_array'       => 'مدد العقد المسموحة يجب أن تكون مصفوفة.',
    'allowed_contract_durations_numeric'     => 'يجب أن تكون كل مدة عقد عبارة عن رقم.',
    
    'min_contract_months_required_if' => 'الحد الأدنى لأشهر العقد مطلوب لخدمات مقيمة.',
    'min_contract_months_numeric'     => 'الحد الأدنى لأشهر العقد يجب أن يكون رقماً.',
    'min_contract_months_lte'         => 'الحد الأدنى لأشهر العقد يجب أن يكون أقل من أو يساوي أصغر مدة عقد مسموحة.',

    // 3. القواعد (Rules)
    'rules_required' => 'قواعد وشروط الخدمة مطلوبة.',
    'rules_array'    => 'قواعد وشروط الخدمة يجب أن تكون مصفوفة.',
    
    'free_cancel_cutoff_hours_required' => 'المهلة المسموحة للإلغاء المجاني مطلوبة.',
    'free_cancel_cutoff_hours_integer'  => 'المهلة المسموحة للإلغاء المجاني يجب أن تكون رقماً صحيحاً.',
    'free_cancel_cutoff_hours_min'      => 'المهلة المسموحة للإلغاء المجاني لا يمكن أن تكون أقل من 0.',
    
    'reschedule_cutoff_hours_required' => 'المهلة المسموحة لإعادة الجدولة مطلوبة.',
    'reschedule_cutoff_hours_integer'  => 'المهلة المسموحة لإعادة الجدولة يجب أن تكون رقماً صحيحاً.',
    'reschedule_cutoff_hours_min'      => 'المهلة المسموحة لإعادة الجدولة لا يمكن أن تكون أقل من 0.',
    
    'lead_time_min_hours_required' => 'الحد الأدنى لوقت التحضير مطلوب.',
    'lead_time_min_hours_integer'  => 'الحد الأدنى لوقت التحضير يجب أن يكون رقماً صحيحاً.',
    'lead_time_min_hours_min'      => 'الحد الأدنى لوقت التحضير لا يمكن أن يكون أقل من 0.',
    
    'lead_time_max_days_required' => 'الحد الأقصى لأيام الحجز المسبق مطلوب.',
    'lead_time_max_days_integer'  => 'الحد الأقصى لأيام الحجز المسبق يجب أن يكون رقماً صحيحاً.',
    'lead_time_max_days_min'      => 'الحد الأقصى لأيام الحجز المسبق يجب أن يكون يوماً واحداً على الأقل.',
    
    'late_cancel_action_required' => 'إجراء الإلغاء المتأخر مطلوب.',
    'late_cancel_action_in'       => 'إجراء الإلغاء المتأخر المحدد غير صحيح.',
    
    'late_cancel_fee_type_required_if' => 'نوع رسوم الإلغاء المتأخر مطلوب عند تحديد الإجراء كرسوم.',
    'late_cancel_fee_type_in'          => 'نوع رسوم الإلغاء المتأخر المحدد غير صحيح.',
    
    'late_cancel_fee_value_required_if' => 'قيمة رسوم الإلغاء المتأخر مطلوبة.',
    'late_cancel_fee_value_numeric'     => 'قيمة رسوم الإلغاء المتأخر يجب أن تكون رقماً.',
    'late_cancel_fee_value_min'         => 'قيمة رسوم الإلغاء المتأخر لا يمكن أن تكون أقل من 0.',
    
    'no_show_policy_required' => 'سياسة عدم الحضور مطلوبة.',
    'no_show_policy_in'       => 'سياسة عدم الحضور المحددة غير صحيحة.',
    
    'max_changes_per_booking_required' => 'الحد الأقصى للتعديلات لكل حجز مطلوب.',
    'max_changes_per_booking_integer'  => 'الحد الأقصى للتعديلات يجب أن يكون رقماً صحيحاً.',
    'max_changes_per_booking_min'      => 'الحد الأقصى للتعديلات لا يمكن أن يكون أقل من 0.',

    // 4. التغطية الجغرافية (Coverage)
    'coverages_required' => 'بيانات التغطية الجغرافية مطلوبة.',
    'coverages_array'    => 'بيانات التغطية الجغرافية يجب أن تكون مصفوفة.',
    'coverages_min'      => 'يجب إضافة منطقة تغطية واحدة على الأقل.',
    
    'coverages_city_id_required' => 'المدينة مطلوبة في التغطية الجغرافية.',
    'coverages_city_id_exists'   => 'المدينة المحددة في التغطية الجغرافية غير موجودة.',
    
    'coverages_area_id_exists' => 'المنطقة المحددة غير موجودة.',
    
    'coverages_status_required' => 'حالة التغطية الجغرافية مطلوبة.',
    'coverages_status_in'       => 'حالة التغطية الجغرافية المحددة غير صحيحة.',
],

     'store_service_price' => [
        'service_id_required' => 'معرف الخدمة مطلوب.',
        'service_id_exists' => 'الخدمة المحددة غير موجودة.',
        'pricing_scope_required' => 'نطاق التسعير مطلوب.',
        'pricing_scope_in' => 'نطاق التسعير يجب أن يكون إما "city" أو "area".',
        'city_id_required_if' => 'معرف المدينة مطلوب عندما يكون نطاق التسعير "city".',
        'city_id_exists' => 'المدينة المحددة غير موجودة.',
        'area_id_required_if' => 'معرف المنطقة مطلوب عندما يكون نطاق التسعير "area".',
        'area_id_exists' => 'المنطقة المحددة غير موجودة.',
        'base_price_required' => 'السعر الأساسي مطلوب.',
        'base_price_numeric' => 'السعر الأساسي يجب أن يكون رقمي.',
        'base_price_min' => 'السعر الأساسي لا يجب أن يكون أقل من 0.',
        'price_per_hour_required' => 'سعر الساعة مطلوب.',
        'price_per_hour_numeric' => 'سعر الساعة يجب أن يكون رقمي.',
        'price_per_hour_min' => 'سعر الساعة يجب أن يكون أكبر من 0.',
        'currency_required' => 'العملة مطلوبة.',
        'currency_string' => 'العملة يجب أن تكون نصية.',
        'currency_size' => 'العملة يجب أن تتكون من 3 أحرف (مثل EGP).',
        'effective_start_date' => 'تاريخ بدء السريان يجب أن يكون تاريخًا صالحًا.',
        'effective_start_after_or_equal' => 'تاريخ بدء السريان لا يمكن أن يكون في الماضي.',
        'effective_end_date' => 'تاريخ نهاية السريان يجب أن يكون تاريخًا صالحًا.',
        'effective_end_after' => 'تاريخ نهاية السريان يجب أن يكون بعد تاريخ بدء السريان.',
     ],

     'adminLogin' =>[

    'email_required' => 'البريد الإلكتروني مطلوب.',
    'email_invalid' => 'صيغة البريد الإلكتروني غير صحيحة.',
    'email_not_found' => 'لا يوجد حساب مرتبط بهذا البريد الإلكتروني.',
    'password_required' => 'كلمة المرور مطلوبة.',
     ],

'storeEmployee' => [
    'first_name_required'   => 'الاسم الأول مطلوب.',
    'first_name_string'     => 'يجب أن يكون الاسم الأول نصاً صحيحاً.',
    'first_name_max'        => 'يجب ألا يتجاوز الاسم الأول 100 حرف.',
    'last_name_required'    => 'الاسم الأخير مطلوب.',
    'last_name_string'      => 'يجب أن يكون الاسم الأخير نصاً صحيحاً.',
    'last_name_max'         => 'يجب ألا يتجاوز الاسم الأخير 100 حرف.',
    'phone_number_required' => 'رقم الهاتف مطلوب.',
    'phone_already_exists'  => 'رقم الهاتف مسجل مسبقاً في النظام.',
    'email_required'        => 'البريد الإلكتروني مطلوب.',
    'email_invalid'         => 'يرجى إدخال عنوان بريد إلكتروني صحيح.',
    'email_already_exists'  => 'البريد الإلكتروني مسجل مسبقاً في النظام.',
    'password_required'     => 'كلمة المرور مطلوبة.',
    'password_min'          => 'يجب ألا تقل كلمة المرور عن 6 أحرف.',
    'type_required'         => 'نوع الموظف مطلوب.',
    'type_invalid'          => 'نوع الموظف المحدد غير صالح.',
    'job_title_max'         => 'يجب ألا يتجاوز المسمى الوظيفي 100 حرف.',
    'gender_invalid'        => 'الجنس المحدد غير صالح.',
    'status_required'       => 'حالة الموظف مطلوبة.',
    'status_invalid'        => 'الحالة المحددة غير صالحة.',
    'roles_required'        => 'يجب تحديد صلاحية (دور) واحدة على الأقل للموظف.',
    'roles_array'           => 'صيغة الصلاحيات المرسلة غير صالحة.',
    'roles_min'             => 'يجب إعطاء الموظف دوراً واحداً على الأقل.',
    'roles_exists'          => 'إحدى الصلاحيات المحددة غير موجودة في النظام.',
],

'storeWorker' => [
    'name_required' => 'اسم العامل مطلوب.',
    'name_string' => 'اسم العامل يجب أن يكون نصاً.',
    'name_max' => 'اسم العامل لا يجب أن يتجاوز 255 حرفاً.',
    'nationality_required' => 'جنسية العامل مطلوبة.',
    'nationality_string' => 'جنسية العامل يجب أن تكون نصاً.',
    'nationality_max' => 'جنسية العامل لا يجب أن تتجاوز 255 حرفاً.',
    'status_in' => 'حالة العامل غير صحيحة. يجب أن تكون إما "active" أو "inactive".',  
    'services_array' => 'الخدمات يجب أن تكون مصفوفة.',
    'services.*.exists' => 'إحدى الخدمات المحددة غير موجودة في النظام.',
    'image_image' => 'الملف يجب أن يكون صورة.',
    'image_mimes' => 'الصورة يجب أن تكون من نوع: jpeg, png,jpg, gif, svg.',
    'image_max' => 'حجم الصورة لا يجب أن يتجاوز 2 ميجابايت.',
],

 'updateWorker' => [
    'name_required' => 'اسم العامل مطلوب.',
    'name_string' => 'اسم العامل يجب أن يكون نصاً.',
    'name_max' => 'اسم العامل لا يجب أن يتجاوز 255 حرفاً.',
    'nationality_required' => 'جنسية العامل مطلوبة.',
    'nationality_string' => 'جنسية العامل يجب أن تكون نصاً.',
    'nationality_max' => 'جنسية العامل لا يجب أن تتجاوز 255 حرفاً.',
    'status_in' => 'حالة العامل غير صحيحة. يجب أن تكون إما "active" أو "inactive".',
    'services_array' => 'الخدمات يجب أن تكون مصفوفة.',  

 ],
 'getAvaliable' => [
    'date_required' => 'تاريخ الخدمة مطلوب.',
    'date_format' => 'تاريخ الخدمة يجب أن يكون بصيغة YYYY-MM-DD.',
    'start_time_required' => 'وقت بدء الخدمة مطلوب.',
    'start_time_format' => 'وقت بدء الخدمة يجب أن يكون بصيغة HH:MM.',
    'duration_required' => 'مدة الخدمة مطلوبة.',
    'duration_integer' => 'مدة الخدمة يجب أن تكون رقماً صحيحاً يمثل عدد الساعات.',
    'duration_min' => 'مدة الخدمة لا يمكن أن تكون أقل من ساعة واحدة.',
 ],

 'getShiftsForDate' => [
    'date_required' => 'تاريخ الخدمة مطلوب.',
    'date_format' => 'تاريخ الخدمة يجب أن يكون بصيغة YYYY-MM-DD.',
 ],

 'create_booking' => [
    'service_id_required' => 'معرف الخدمة مطلوب.',
    'service_id_exists' => 'الخدمة المحددة غير موجودة.',
    'worker_id_required' => 'معرف العامل مطلوب.',
    'worker_id_exists' => 'العامل المحدد غير موجود.',
    'address_id_required' => 'معرف العنوان مطلوب.',
    'address_id_exists' => 'العنوان المحدد غير موجود.',
    'date_required' => 'تاريخ الحجز مطلوب.',
    'date_date_format' => 'تاريخ الحجز يجب أن يكون بصيغة YYYY-MM-DD.',
    'start_time_required' => 'وقت بدء الحجز مطلوب.',
    'start_time_date_format' => 'وقت بدء الحجز يجب أن يكون بصيغة HH:MM.',
    // 'duration_minutes_required' => 'مدة الحجز بالدقائق مطلوبة.',
    // 'duration_minutes_integer' => 'مدة الحجز بالدقائق يجب أن تكون رقماً صحيحاً.',
    // 'duration_minutes_min' => 'مدة الحجز لا يمكن أن تكون أقل من 60 دقيقة (ساعة واحدة).',
    'end_time_required' => 'وقت انتهاء الحجز مطلوب.',
    'end_time_date_format' => 'وقت انتهاء الحجز يجب أن يكون بصيغة HH:MM.',
    'end_time_after' => 'وقت انتهاء الحجز يجب أن يكون بعد وقت بدء الحجز.',
 ],

'report' => [
        'report_reason_required' => 'سبب الإبلاغ مطلوب.',
        'report_reason_string' => 'يجب أن يكون سبب الإبلاغ نصاً.',
        'report_reason_invalid' => 'سبب الإبلاغ المحدد غير صالح.',
        'report_description_string' => 'يجب أن يكون وصف الإبلاغ نصاً.',
        'report_description_required_if_other' => 'وصف الإبلاغ مطلوب عندما يكون السبب "أخرى".',
    ],

 'review' => [
        'service_rating_required' => 'تقييم الخدمة مطلوب.',
        'service_rating_integer'  => 'يجب أن يكون تقييم الخدمة رقماً صحيحاً.',
        'service_rating_min'      => 'يجب ألا يقل تقييم الخدمة عن :min.',
        'service_rating_max'      => 'يجب ألا يتجاوز تقييم الخدمة :max.',

        'service_comment_string'  => 'يجب أن يكون تعليق الخدمة نصاً.',
        'service_comment_max'     => 'يجب ألا يتجاوز تعليق الخدمة :max حرفاً.',

        'workers_required'        => 'قائمة العمال مطلوبة.',
        'workers_array'           => 'يجب أن تكون قائمة العمال مصفوفة.',

        'worker_id_required'      => 'رقم العامل مطلوب.',
        'worker_id_exists'        => 'العامل المحدد غير صالح.',

        'worker_rating_required'  => 'تقييم العامل مطلوب.',
        'worker_rating_integer'   => 'يجب أن يكون تقييم العامل رقماً صحيحاً.',
        'worker_rating_min'       => 'يجب ألا يقل تقييم العامل عن :min.',
        'worker_rating_max'       => 'يجب ألا يتجاوز تقييم العامل :max.',

        'worker_comment_string'   => 'يجب أن يكون تعليق العامل نصاً.',
        'worker_comment_max'      => 'يجب ألا يتجاوز تعليق العامل :max حرفاً.',
    ],
    
    'deposit' => [
        'amount_required' => 'مبلغ الإيداع مطلوب.',
        'amount_numeric' => 'مبلغ الإيداع يجب أن يكون رقمي.',
     ],

     'card' => [
        'card_token_required' => 'رمز البطاقة مطلوب.',
        'card_token_string' => 'رمز البطاقة يجب أن يكون نصاً.',
        'last_four_required' => 'الأرقام الأربعة الأخيرة من البطاقة مطلوبة.',
        'last_four_string' => 'الأرقام الأربعة الأخيرة يجب أن تكون نصاً.',
        'last_four_size' => 'الأرقام الأربعة الأخيرة يجب أن تكون بالضبط 4 أحرف.',
        'card_brand_required' => 'علامة البطاقة مطلوبة.',
        'card_brand_string' => 'علامة البطاقة يجب أن تكون نصاً.',
        'card_holder_name_string' => 'اسم حامل البطاقة يجب أن يكون نصاً.',
     ],

    'complete_profile' =>
     [
    'first_name_required' => 'الاسم الأول مطلوب.',
    'last_name_required'  => 'الاسم الأخير مطلوب.',
    'phone_required'      => 'رقم الهاتف مطلوب.',
    'phone_unique'        => 'رقم الهاتف مستخدم مسبقاً.',
    'id_number_required' => 'رقم الهوية مطلوب.',
    'id_number_unique' => 'رقم الهوية مستخدم مسبقاً.',
    'id_number_string' => 'رقم الهوية يجب أن يكون نصاً.',
    'id_number_max' => 'رقم الهوية لا يجب أن يتجاوز 50 حرفاً.',
    'email_required'      => 'البريد الإلكتروني مطلوب.',
    'email_invalid'       => 'صيغة البريد الإلكتروني غير صحيحة.',
    'email_unique'        => 'البريد الإلكتروني مستخدم مسبقاً لحساب آخر.',
    'gender_required'     => 'يرجى تحديد الجنس.',
    'gender_invalid'      => 'قيمة الجنس المحددة غير صالحة.',
    'image_invalid'       => 'الملف المرفق يجب أن يكون صورة.',
    'image_mimes'         => 'يجب أن تكون الصورة بصيغة: jpeg, png, jpg, webp.',
    'image_max'           => 'حجم الصورة يجب ألا يتجاوز 4 ميجابايت.',

    ],

];
    
