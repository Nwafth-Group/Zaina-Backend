<?php

return [

    'login' => [
      //   'mobile_number_required' => 'Mobile number is required.',
      //   'mobile_number_numeric' => 'Mobile number must be numeric.',
      //   'invalid_mobile' => 'The mobile number is not registered.',
      //   'otp_required' => 'OTP is required.',
      //   'otp_numeric' => 'OTP must be numeric.',
      'phone_required' => 'Phone number is required to login.',
      'phone_string'   => 'Invalid phone number format.',
      'phone_min'      => 'Phone number must be at least 9 digits.',
      'phone_max'      => 'Phone number must not exceed 15 digits.',
      'account_inactive' => 'Sorry, your account is inactive. Please contact support.',
      'logged_in_successfully' => 'Logged in successfully.',
      'registered_successfully' => 'Account created and logged in successfully.',
    ],

    'sendotp' => [
      'mobile_number_required' => 'Mobile number is reqiured.',
      'otp_required' => 'OTP is reqiured.',
    ],

    'profile'=>[
        'email_required' => 'Email is required.',
        'email_email' => 'Email must be a valid email address.',
        'email_max' => 'Email must not exceed 100 characters.',
        'email_unique' => 'Email is already in use.',
        'id_number_required' => 'ID number is required.',
        'id_number_string' => 'ID number must be a string.',
        'id_number_max' => 'ID number must not exceed 50 characters.',
        'id_number_unique' => 'ID number is already in use.',
        'image_image' => 'The file must be an image.',
        'image_mimes' => 'The image must be a file of type: jpeg, png, jpg.',
        'image_max' => 'The image must not be greater than 2MB.',
        'gender_required' => 'Gender is required.', 
        'gender_in' => 'Gender must be either Male or Female.',
    ],

    'address' => [
    'area_required'         => 'The area field is required.',
    'area_invalid'          => 'The selected area is invalid.',
    'location_required'     => 'Please select the location on the map.',
    'street_required'       => 'The street name is required.',
    'contact_name_required' => 'The recipient name is required.',
    'phone_required'        => 'The phone number is required.',
],

    'delete_account' => [
        'otp_required' => 'OTP is required.',
        'otp_numeric' => 'OTP must be numeric.',
        'otp_digits' => 'OTP must be exactly 6 digits.',
        'reason_string' => 'Reason must be a string.',
        'reason_in' => 'Reason must be one of the following: no_longer_need, data_concern, dissatisfied, complicated, prefer_not, other.',
        'other_reason_required_if' => 'Other reason is required when reason is "other".',
        'other_reason_string' => 'Other reason must be a string.',
        'other_reason_max' => 'Other reason must not exceed 250 characters.',
        'confirm_required' => 'You must confirm the account deletion by checking the box.',
        'confirm_accepted' => 'You must accept the account deletion by checking the box.',
    ],

    'role' => [
        'name_required' => 'Role name is required.',
        'name_string' => 'Role name must be a string.',
        'name_max' => 'Role name must not exceed 100 characters.',
        'name_unique' => 'Role name must be unique.',
        'code_required' => 'Role code is required.',
        'code_string' => 'Role code must be a string.',
        'code_max' => 'Role code must not exceed 100 characters.',
        'code_unique' => 'Role code must be unique.',
        'description_string' => 'Description must be a string.',
        'description_max' => 'Description must not exceed 200 characters.',
        'status_required' => 'Status is required.',
        'status_in' => 'Status must be either active or inactive.',
        'permissions_required' => 'At least one permission is required.',
        'permissions_array' => 'Permissions must be an array.',
        'permissions_min' => 'At least one permission is required.',
        'permissions_exists' => 'One or more selected permissions are invalid.',
        ],

     'reset_password' => [
        'password_required' => 'Password is required.',
        'password_string' => 'Password must be a string.',
        'password_min' => 'Password must be at least 8 characters.',
        'password_confirmed' => 'Password confirmation does not match.',
     ],

     'store_citry' => [
        'country_id_required' => 'Country ID is required.',
        'country_id_exists' => 'The selected country does not exist.',
        'name_ar_required' => 'Arabic name is required.',
        'name_ar_string' => 'Arabic name must be a string.',
        'name_ar_max' => 'Arabic name must not exceed 100 characters.',
        'name_en_required' => 'English name is required.',
        'name_en_string' => 'English name must be a string.',
        'name_en_max' => 'English name must not exceed 100 characters.',
        'code_required' => 'City code is required.',
        'code_string' => 'City code must be a string.',
        'code_max' => 'City code must not exceed 20 characters.',
        'code_unique' => 'City code must be unique within the same country.',
        'status_required' => 'Status is required.',
        'status_in' => 'Status must be either active or inactive.',
     ],

   'store_country' => [
        'name_ar_required'       => 'The Arabic name is required.',
        'name_ar_string'         => 'The Arabic name must be a string.',
        'name_ar_max'            => 'The Arabic name may not be greater than 100 characters.',
        
        'name_en_required'       => 'The English name is required.',
        'name_en_string'         => 'The English name must be a string.',
        'name_en_max'            => 'The English name may not be greater than 100 characters.',
        
        'code_required'          => 'The country code is required.',
        'code_string'            => 'The country code must be a string.',
        'code_max'               => 'The country code may not be greater than 20 characters.',
        'code_unique'            => 'This country code has already been taken.',
        
        'status_in'              => 'The status must be either active or inactive.',
        
        'currency_code_required' => 'The currency code is required.',
        'currency_code_string'   => 'The currency code must be a string.',
        'currency_code_max'      => 'The currency code may not be greater than 10 characters.',
        
        'phone_code_string'      => 'The phone code must be a string.',
        'phone_code_max'         => 'The phone code may not be greater than 10 characters.',
        
        'flag_image'             => 'The flag must be an image.',
        'flag_mimes'             => 'The flag must be a file of type: jpeg, png, jpg, svg, webp.',
        'flag_max'               => 'The flag image size may not be greater than 1024 kilobytes.',
    ],

     'store_area' => [
        'city_id_required' => 'City ID is required.',
        'city_id_exists' => 'The selected city does not exist.',
        'name_ar_required' => 'Arabic name is required.',
        'name_ar_string' => 'Arabic name must be a string.',
        'name_ar_max' => 'Arabic name must not exceed 100 characters.',
        'name_en_required' => 'English name is required.',
        'name_en_string' => 'English name must be a string.',
        'name_en_max' => 'English name must not exceed 100 characters.',
        'code_required' => 'Area code is required.',
        'code_string' => 'Area code must be a string.',
        'code_max' => 'Area code must not exceed 20 characters.',
        'code_unique' => 'Area code must be unique.',
        'status_required' => 'Status is required.',
        'status_in' => 'Status must be either active or inactive.',
     ],

     'create_category' => [
        'parent_id_exists' => 'The selected category is invalid, does not exist, or is already a subcategory.',
        'parent_id_not_in' => 'A category cannot be a subcategory of itself.',
        
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_string'   => 'The Arabic name must be a string.',
        'name_ar_max'      => 'The Arabic name may not be greater than 100 characters.',
        'name_ar_unique'   => 'The Arabic name has already been taken.',
        
        'name_en_required' => 'The English name is required.',
        'name_en_string'   => 'The English name must be a string.',
        'name_en_max'      => 'The English name may not be greater than 100 characters.',
        'name_en_unique'   => 'The English name has already been taken.',
        
        'image_image'      => 'The file must be an image.',
        'image_mimes'      => 'The image must be a file of type: jpeg, png, jpg, svg, webp.',
        'image_max'        => 'The image size may not be greater than 2MB.',
        
        'sort_order_integer'=> 'The sort order must be an integer.',
        'sort_order_min'   => 'The sort order must be at least 0.',
        
        'status_in'        => 'The selected status is invalid.',
    ],

    'update_category' => [
        'parent_id_exists' => 'The selected parent category is invalid or does not exist.',
        'parent_id_not_in' => 'Error: A category cannot be set as a subcategory of itself.',
        
        'name_ar_required' => 'The Arabic name is required.',
        'name_ar_string'   => 'The Arabic name must be a string.',
        'name_ar_max'      => 'The Arabic name may not be greater than 100 characters.',
        'name_ar_unique'   => 'This Arabic name is already taken by another category.',
        
        'name_en_required' => 'The English name is required.',
        'name_en_string'   => 'The English name must be a string.',
        'name_en_max'      => 'The English name may not be greater than 100 characters.',
        'name_en_unique'   => 'This English name is already taken by another category.',
        
        'image_image'      => 'The file must be an image.',
        'image_mimes'      => 'The image must be a file of type: jpeg, png, jpg, svg, webp.',
        'image_max'        => 'The image size may not be greater than 2MB.',
        
        'sort_order_integer'=> 'The sort order must be an integer.',
        'sort_order_min'   => 'The sort order must be at least 0.',
        
        'status_in'        => 'The selected status is invalid.',
    ],

     'update_user' => [
        'status_invalid' => 'Status must be either active or inactive.',
        'roles_array' => 'Roles must be an array.',
        'roles_exists' => 'One or more selected roles are invalid.',
     ],

   'create_service' => [
    // 1. Basic Data
    'category_id_required' => 'The category is required.',
    'category_id_exists'   => 'The selected category is invalid.',
    
    'type_required' => 'The service type is required.',
    'type_in'       => 'The service type must be: hourly, moqema, or both.',
    
    'name_ar_required' => 'The Arabic name is required.',
    'name_ar_string'   => 'The Arabic name must be a string.',
    'name_ar_max'      => 'The Arabic name must not exceed 100 characters.',
    
    'name_en_required' => 'The English name is required.',
    'name_en_string'   => 'The English name must be a string.',
    'name_en_max'      => 'The English name must not exceed 100 characters.',

    'workers_required'         => 'At least one worker is required.',
    'workers_array'            => 'Workers must be an array.',
    'workers.min'              => 'At least one worker is required.',
    'workers.*.integer'       => 'Each worker ID must be an integer.',
    'workers.*.exists'        => 'Selected worker does not exist.',

    'description_ar_string' => 'The Arabic description must be a string.',
    'description_en_string' => 'The English description must be a string.',
    
    'status_in' => 'The selected status is invalid.',

    // 2. Durations
    'duration_required' => 'Duration details are required.',
    'duration_array'    => 'Duration details must be an array.',
    
    'allowed_durations_required_if' => 'Allowed durations are required for hourly services.',
    'allowed_durations_array'       => 'Allowed durations must be an array.',
    'allowed_durations_numeric'     => 'Each allowed duration must be a number.',
    
    'min_duration_hours_required_if' => 'Minimum duration hours are required for hourly services.',
    'min_duration_hours_numeric'     => 'Minimum duration hours must be a number.',
    'min_duration_hours_lte'         => 'Minimum duration hours must be less than or equal to the smallest allowed duration.',
    
    'allowed_contract_durations_required_if' => 'Allowed contract durations are required for moqema services.',
    'allowed_contract_durations_array'       => 'Allowed contract durations must be an array.',
    'allowed_contract_durations_numeric'     => 'Each allowed contract duration must be a number.',
    
    'min_contract_months_required_if' => 'Minimum contract months are required for moqema services.',
    'min_contract_months_numeric'     => 'Minimum contract months must be a number.',
    'min_contract_months_lte'         => 'Minimum contract months must be less than or equal to the smallest allowed contract duration.',

    // 3. Rules
    'rules_required' => 'Service rules are required.',
    'rules_array'    => 'Service rules must be an array.',
    
    'free_cancel_cutoff_hours_required' => 'Free cancellation cutoff hours are required.',
    'free_cancel_cutoff_hours_integer'  => 'Free cancellation cutoff hours must be an integer.',
    'free_cancel_cutoff_hours_min'      => 'Free cancellation cutoff hours cannot be less than 0.',
    
    'reschedule_cutoff_hours_required' => 'Reschedule cutoff hours are required.',
    'reschedule_cutoff_hours_integer'  => 'Reschedule cutoff hours must be an integer.',
    'reschedule_cutoff_hours_min'      => 'Reschedule cutoff hours cannot be less than 0.',
    
    'lead_time_min_hours_required' => 'Minimum lead time hours are required.',
    'lead_time_min_hours_integer'  => 'Minimum lead time hours must be an integer.',
    'lead_time_min_hours_min'      => 'Minimum lead time hours cannot be less than 0.',
    
    'lead_time_max_days_required' => 'Maximum lead time days are required.',
    'lead_time_max_days_integer'  => 'Maximum lead time days must be an integer.',
    'lead_time_max_days_min'      => 'Maximum lead time days must be at least 1.',
    
    'late_cancel_action_required' => 'Late cancellation action is required.',
    'late_cancel_action_in'       => 'The selected late cancellation action is invalid.',
    
    'late_cancel_fee_type_required_if' => 'Late cancellation fee type is required when a fee is applied.',
    'late_cancel_fee_type_in'          => 'The selected late cancellation fee type is invalid.',
    
    'late_cancel_fee_value_required_if' => 'Late cancellation fee value is required.',
    'late_cancel_fee_value_numeric'     => 'Late cancellation fee value must be a number.',
    'late_cancel_fee_value_min'         => 'Late cancellation fee value cannot be less than 0.',
    
    'no_show_policy_required' => 'No show policy is required.',
    'no_show_policy_in'       => 'The selected no show policy is invalid.',
    
    'max_changes_per_booking_required' => 'Maximum changes per booking is required.',
    'max_changes_per_booking_integer'  => 'Maximum changes per booking must be an integer.',
    'max_changes_per_booking_min'      => 'Maximum changes per booking cannot be less than 0.',

    // 4. Coverage
    'coverages_required' => 'Geographical coverage details are required.',
    'coverages_array'    => 'Geographical coverage details must be an array.',
    'coverages_min'      => 'At least one coverage area must be provided.',
    
    'coverages_city_id_required' => 'The city is required for coverage.',
    'coverages_city_id_exists'   => 'The selected city is invalid.',
    
    'coverages_area_id_exists' => 'The selected area is invalid.',
    
    'coverages_status_required' => 'The coverage status is required.',
    'coverages_status_in'       => 'The selected coverage status is invalid.',
],

     'store_service_price' => [
        'service_id_required' => 'Service ID is required.',
        'service_id_exists' => 'The selected service does not exist.',
        'pricing_scope_required' => 'Pricing scope is required.',
        'pricing_scope_in' => 'Pricing scope must be either city or area.',
        'city_id_required_if' => 'City ID is required when pricing scope is city.',
        'city_id_exists' => 'The selected city does not exist.',
        'area_id_required_if' => 'Area ID is required when pricing scope is area.',
        'area_id_exists' => 'The selected area does not exist.',
        'base_price_required' => 'Base price is required.',
        'base_price_numeric' => 'Base price must be a numeric value.',
        'base_price_min' => 'Base price must be at least 0.',
        'price_per_hour_required' => 'Price per hour is required.',
        'price_per_hour_numeric' => 'Price per hour must be a numeric value.',
        'price_per_hour_min' => 'Price per hour must be at least 1.',
        'currency_required' => 'Currency code is required.',
        'currency_string' => 'Currency code must be a string.',
        'currency_size' => 'Currency code must be exactly 3 characters (e.g., EGP).',
        'effective_start_date' => 'Effective start date must be a valid date.',
        'effective_start_after_or_equal' => 'Effective start date cannot be in the past.',
        'effective_end_date' => 'Effective end date must be a valid date.',
        'effective_end_after' => 'Effective end date must be after the effective start date.',
     ],

     'adminLogin' =>[
      'email_required' => 'Email address is required.',
      'email_invalid' => 'Invalid email format.',
      'email_not_found' => 'No account found with this email.',
      'password_required' => 'Password is required.',
     ],

    'storeEmployee' => [
    'first_name_required'   => 'The first name is required.',
    'first_name_string'     => 'The first name must be a valid string.',
    'first_name_max'        => 'The first name may not be greater than 100 characters.',
    
    'last_name_required'    => 'The last name is required.',
    'last_name_string'      => 'The last name must be a valid string.',
    'last_name_max'         => 'The last name may not be greater than 100 characters.',
    
    'phone_number_required' => 'The phone number is required.',
    'phone_already_exists'  => 'This phone number is already registered.',
    
    'email_required'        => 'The email address is required.',
    'email_invalid'         => 'Please provide a valid email address.',
    'email_already_exists'  => 'This email is already registered.',
    
    'password_required'     => 'The password is required.',
    'password_min'          => 'The password must be at least 6 characters.',
    
    'type_required'         => 'The employee type is required.',
    'type_invalid'          => 'The selected employee type is invalid.',
    
    'job_title_max'         => 'The job title may not be greater than 100 characters.',
    
    'gender_invalid'        => 'The selected gender is invalid.',
    
    'status_required'       => 'The employee status is required.',
    'status_invalid'        => 'The selected status is invalid.',
    
    'roles_required'        => 'At least one role must be assigned to the employee.',
    'roles_array'           => 'The roles format is invalid.',
    'roles_min'             => 'You must assign at least one role.',
    'roles_exists'          => 'One or more selected roles do not exist in our system.',
],

'store_worker' => [
    'name_required' => 'The worker name is required.',
    'name_string' => 'The worker name must be a string.',
    'name_max' => 'The worker name must not exceed 255 characters.',
    'nationality_required' => 'The worker nationality is required.',
    'nationality_string' => 'The worker nationality must be a string.',
    'nationality_max' => 'The worker nationality must not exceed 255 characters.',
    'status_in' => 'The worker status is invalid.', 
    'services_array' => 'The services must be an array.',
    'services.*.exists' => 'One or more selected services do not exist.',
    'image_image' => 'The file must be an image.',
    'image_mimes' => 'The image must be a file of type: jpeg, png, jpg, gif, svg.',
    'image_max' => 'The image must not be greater than 2MB.',
],

'getAvaliable' => [
    'date_required' => 'The date is required.',
    'date_format' => 'The date must be in the format YYYY-MM-DD.',
    'start_time_required' => 'The start time is required.',
    'start_time_format' => 'The start time must be in the format HH:MM.',
    'duration_required' => 'The duration is required.',
    'duration_integer' => 'The duration must be an integer representing hours.',
    'duration_min' => 'The duration must be at least 1 hour.',
],

'getShiftsForDate' => [
    'date_required' => 'The date is required.',
    'date_format' => 'The date must be in the format YYYY-MM-DD.',

],

'create_booking' => [
    'service_id_required' => 'Service ID is required.',
    'service_id_exists' => 'The selected service does not exist.',
    'worker_id_required' => 'Worker ID is required.',
    'worker_id_exists' => 'The selected worker does not exist.',
    'address_id_required' => 'Address ID is required.',
    'address_id_exists' => 'The selected address does not exist.',
    'date_required' => 'Booking date is required.',
    'date_date_format' => 'Booking date must be in the format YYYY-MM-DD.',
    'start_time_required' => 'Start time is required.',
    'start_time_date_format' => 'Start time must be in the format HH:MM.',
    // 'duration_minutes_required' => 'Duration in minutes is required.',
    // 'duration_minutes_integer' => 'Duration must be an integer representing minutes.',
    // 'duration_minutes_min' => 'Duration must be at least 60 minutes (1 hour).', 
    'end_time_required' => 'End time is required.',
    'end_time_date_format' => 'End time must be in the format HH:MM.',
    'end_time_after' => 'End time must be after the start time.',
], 

'report' => [
        'report_reason_required' => 'The report reason is required.',
        'report_reason_string' => 'The report reason must be a string.',
        'report_reason_invalid' => 'The selected report reason is invalid.',
        'report_description_string' => 'The report description must be a string.',
        'report_description_required_if_other' => 'The report description is required when the reason is "other".',
    ],

  'review' => [
        'service_rating_required' => 'The service rating is required.',
        'service_rating_integer'  => 'The service rating must be an integer.',
        'service_rating_min'      => 'The service rating must be at least :min.',
        'service_rating_max'      => 'The service rating may not be greater than :max.',

        'service_comment_string'  => 'The service comment must be a string.',
        'service_comment_max'     => 'The service comment may not be greater than :max characters.',

        'workers_required'        => 'The workers list is required.',
        'workers_array'           => 'The workers must be an array.',

        'worker_id_required'      => 'The worker ID is required.',
        'worker_id_exists'        => 'The selected worker is invalid.',

        'worker_rating_required'  => 'The worker rating is required.',
        'worker_rating_integer'   => 'The worker rating must be an integer.',
        'worker_rating_min'       => 'The worker rating must be at least :min.',
        'worker_rating_max'       => 'The worker rating may not be greater than :max.',

        'worker_comment_string'   => 'The worker comment must be a string.',
        'worker_comment_max'      => 'The worker comment may not be greater than :max characters.',
    ],  

   'deposit' => [
        'amount_required' => 'The deposit amount is required.',
        'amount_numeric' => 'The deposit amount must be a numeric value.',
     ],

    'card' => [
        'card_token_required' => 'Card token is required.',
        'card_token_string' => 'Card token must be a string.',
        'last_four_required' => 'Last four digits of the card are required.',
        'last_four_string' => 'Last four digits must be a string.',
        'last_four_size' => 'Last four digits must be exactly 4 characters.',
        'card_brand_required' => 'Card brand is required.',
        'card_brand_string' => 'Card brand must be a string.',
        'card_holder_name_string' => 'Card holder name must be a string.',
     ],

    'complete_profile' => [
        'first_name_required'   => 'First name is required.',
        'first_name_string'     => 'First name must be a string.',
        'first_name_max'        => 'First name must not exceed 50 characters.',
        'last_name_required'    => 'Last name is required.',
        'last_name_string'      => 'Last name must be a string.',
        'last_name_max'         => 'Last name must not exceed 50 characters.',
        'phone_required'       => 'Phone number is required.',
        'phone_string'         => 'Phone number must be a string.',
        'phone_max'            => 'Phone number must not exceed 20 characters.',
        'phone_min'            => 'Phone number must be at least 9 characters.',
        'phone_unique'         => 'This phone number is already in use.',
        'id_number_required' => 'ID number is required.',
        'id_number_string' => 'ID number must be a string.',
        'id_number_max' => 'ID number must not exceed 50 characters.',
        'id_number_unique' => 'This ID number is already in use.',
        'email_required'       => 'Email address is required.',
        'email_email'          => 'Email address must be valid.',
        'email_max'           => 'Email address must not exceed 255 characters.',
        'email_unique'        => 'This email address is already in use.',
        'gender_required' => 'Gender is required.',
        'gender_in' => 'Gender must be either   Male or Female.',
        'image_image' => 'The file must be an image.',
        'image_mimes' => 'The image must be a file of type: jpeg, png, jpg, webp.',
        'image_max' => 'The image must not be greater than 4MB.',
     ],


    
  



];
