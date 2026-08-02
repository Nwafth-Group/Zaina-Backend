<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مرحباً بك في لوحة التحكم الإدارية</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #F8FAFC;
            margin: 0;
            padding: 20px 0;
            direction: rtl;
            text-align: right;
            color: #1E293B;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #FFFFFF;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            border: 1px solid #F1F5F9;
        }
        .email-header {
            background: linear-gradient(135deg, #E91E63 0%, #F45B8C 50%, #FF8FA3 100%);
            padding: 36px 24px;
            text-align: center;
            color: #FFFFFF;
        }
        .brand-logo {
            max-height: 70px;
            margin-bottom: 12px;
            object-contain: fit;
        }
        .header-title {
            font-size: 22px;
            font-weight: 900;
            margin: 0;
            color: #FFFFFF;
            letter-spacing: -0.5px;
        }
        .header-subtitle {
            font-size: 13px;
            color: #FFE4E6;
            margin-top: 6px;
            font-weight: 500;
        }
        .email-body {
            padding: 32px 28px;
        }
        .welcome-badge {
            display: inline-block;
            background-color: #FDF0F3;
            color: #E91E63;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 16px;
            border: 1px solid #FBCFE8;
        }
        .greeting {
            font-size: 20px;
            font-weight: 800;
            color: #0F172A;
            margin-bottom: 12px;
        }
        .intro-text {
            font-size: 14px;
            line-height: 1.7;
            color: #475569;
            margin-bottom: 24px;
        }
        .credentials-card {
            background-color: #F8FAFC;
            border: 1.5px dashed #E2E8F0;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 28px;
        }
        .credentials-title {
            font-size: 14px;
            font-weight: 800;
            color: #E91E63;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .credential-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F1F5F9;
            font-size: 13px;
        }
        .credential-row:last-child {
            border-bottom: none;
        }
        .credential-label {
            color: #64748B;
            font-weight: 600;
        }
        .credential-value {
            font-weight: 700;
            color: #0F172A;
            font-family: 'Courier New', Courier, monospace;
            direction: ltr;
        }
        .highlight-value {
            background-color: #FDF0F3;
            color: #E91E63;
            padding: 4px 10px;
            border-radius: 8px;
            border: 1px solid #FBCFE8;
        }
        .action-container {
            text-align: center;
            margin: 32px 0 24px;
        }
        .btn-login {
            display: inline-block;
            background: linear-gradient(135deg, #E91E63 0%, #F45B8C 100%);
            color: #FFFFFF !important;
            text-decoration: none;
            font-size: 15px;
            font-weight: 800;
            padding: 14px 36px;
            border-radius: 14px;
            box-shadow: 0 8px 20px rgba(233, 30, 99, 0.25);
            transition: all 0.2s ease;
        }
        .security-notice {
            background-color: #FEF3C7;
            border: 1px solid #FDE68A;
            color: #92400E;
            font-size: 12px;
            line-height: 1.6;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .email-footer {
            background-color: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
        }
        .footer-company-name {
            font-weight: 800;
            color: #475569;
            font-size: 13px;
            margin-bottom: 6px;
        }
        .footer-contacts {
            margin-bottom: 12px;
            color: #64748B;
        }
        .footer-contacts span {
            margin: 0 8px;
        }
        .copyright {
            color: #94A3B8;
            font-size: 11px;
        }
    </style>
</head>
<body>

    <div class="email-container">
        
        <!-- Header -->
        <div class="email-header">
            @if(!empty($companyInfo?->logo_url))
                <img src="{{ $companyInfo->logo_url }}" alt="Company Logo" class="brand-logo">
            @else
                <div style="font-size: 32px; font-weight: 900; margin-bottom: 8px;">👑</div>
            @endif
            <h1 class="header-title">{{ $companyInfo?->name_ar ?? 'زينة للخدمات المنزلية' }}</h1>
            <div class="header-subtitle">{{ $companyInfo?->name_en ?? 'Zeina Home Services Management' }}</div>
        </div>

        <!-- Body -->
        <div class="email-body">
            
            <div class="welcome-badge">
                @if($isUpdate)
                    🔄 تم تحديث بيانات حسابك الإداري
                @else
                    🎉 مرحباً بك في الفريق!
                @endif
            </div>

            <div class="greeting">
                أهلاً بك، {{ $employee->first_name }} {{ $employee->last_name }} 👋
            </div>

            <p class="intro-text">
                @if($isUpdate)
                    تم تحديث بيانات حسابك الإداري بنجاح في منصة شركة <b>{{ $companyInfo?->name_ar ?? 'زينة' }}</b>. يمكنك الاطلاع على بياناتك المحدثة ورابط الدخول أدناه:
                @else
                    تمت إضافة حسابك الموظف بنجاح في المنصة الإدارية لشركة <b>{{ $companyInfo?->name_ar ?? 'زينة' }}</b>. يمكنك الآن الوصول إلى لوحة التحكم ومباشرة مهامك الإدارية باستخدام بيانات الاعتماد أدناه.
                @endif
            </p>

            <!-- Account Details Card -->
            <div class="credentials-card">
                <div class="credentials-title">
                    🔑 بيانات تسجيل الدخول الخاصة بحسابك:
                </div>

                <div class="credential-row">
                    <span class="credential-label">رابط لوحة التحكم:</span>
                    <span class="credential-value" style="color: #2563EB;">{{ $adminPortalUrl }}</span>
                </div>

                <div class="credential-row">
                    <span class="credential-label">البريد الإلكتروني:</span>
                    <span class="credential-value highlight-value">{{ $employee->email }}</span>
                </div>

                <div class="credential-row">
                    <span class="credential-label">كلمة المرور:</span>
                    <span class="credential-value highlight-value">
                        @if(!empty($plainPassword))
                            {{ $plainPassword }}
                        @else
                            <span style="color: #64748B; font-weight: normal; font-family: inherit;">(لم تتغير / Unchanged)</span>
                        @endif
                    </span>
                </div>

                @if(!empty($employee->phone_number))
                <div class="credential-row">
                    <span class="credential-label">رقم الهاتف:</span>
                    <span class="credential-value">{{ $employee->phone_number }}</span>
                </div>
                @endif

                <div class="credential-row">
                    <span class="credential-label">الصلاحية / الدور الوظيفي:</span>
                    <span class="credential-label" style="color: #0F172A; font-weight: 700;">
                        {{ $employee->roles->pluck('name')->join('، ') ?: ($employee->type === 'super_admin' ? 'Super Admin' : 'موظف عمليات') }}
                    </span>
                </div>
            </div>

            <!-- Login Action Button -->
            <div class="action-container">
                <a href="{{ $adminPortalUrl }}" target="_blank" class="btn-login">
                    🚀 تسجيل الدخول إلى لوحة التحكم
                </a>
            </div>

            <!-- Security Notice -->
            <div class="security-notice">
                🔒 <b>نصيحة أمان:</b> يُرجى تغيير كلمة المرور الخاّصة بك بعد تسجيل الدخول لأول مرة من إعدادات الملف الشخصي للحفاظ على أمان حسابك.
            </div>

        </div>

        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-company-name">
                {{ $companyInfo?->name_ar ?? 'زينة للخدمات المنزلية' }} — {{ $companyInfo?->name_en ?? 'Zeina Home Services' }}
            </div>
            
            <div class="footer-contacts">
                @if(!empty($companyInfo?->email))
                    <span>📧 {{ $companyInfo->email }}</span>
                @endif
                @if(!empty($companyInfo?->phone))
                    <span>📞 {{ $companyInfo->phone }}</span>
                @endif
                @if(!empty($companyInfo?->tax_number))
                    <span>📋 الرقم الضريبي: {{ $companyInfo->tax_number }}</span>
                @endif
            </div>

            <div class="copyright">
                جميع الحقوق محفوظة &copy; {{ date('Y') }} {{ $companyInfo?->name_ar ?? 'زينة للخدمات المنزلية' }}.
            </div>
        </div>

    </div>

</body>
</html>
