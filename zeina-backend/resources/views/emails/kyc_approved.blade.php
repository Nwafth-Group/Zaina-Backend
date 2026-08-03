<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تم توثيق حسابك بنجاح</title>
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
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
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
        .email-body {
            padding: 32px 28px;
        }
        .welcome-badge {
            display: inline-block;
            background-color: #D1FAE5;
            color: #047857;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 16px;
            border: 1px solid #6EE7B7;
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
        .success-box {
            background-color: #F0FDF4;
            border: 1.5px solid #BBF7D0;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 28px;
            text-align: center;
        }
        .email-footer {
            background-color: #F8FAFC;
            border-top: 1px solid #F1F5F9;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            @if(!empty($companyInfo?->logo_url))
                <img src="{{ $companyInfo->logo_url }}" alt="Company Logo" class="brand-logo">
            @else
                <div style="font-size: 32px; font-weight: 900; margin-bottom: 8px;">👑</div>
            @endif
            <h1 class="header-title">{{ $companyInfo?->name_ar ?? 'زينة للخدمات المنزلية' }}</h1>
        </div>

        <div class="email-body">
            <div class="welcome-badge">
                🎉 تم التوثيق بنجاح
            </div>

            <div class="greeting">
                أهلاً بك، {{ $customer->first_name }} {{ $customer->last_name }} 👋
            </div>

            <p class="intro-text">
                يسعدنا إبلاغك بأنه تمت الموافقة على طلب توثيق حسابك رسمياً في منصة <b>{{ $companyInfo?->name_ar ?? 'زينة' }}</b>.
            </p>

            <div class="success-box">
                <div style="font-size: 40px; margin-bottom: 10px;">✅</div>
                <h3 style="color: #065F46; margin: 0 0 8px;">حسابك الآن موثّق بالكامل!</h3>
                <p style="color: #047857; font-size: 13px; margin: 0;">يمكنك الآن حجز كافة الخدمات المنزلية بسهولة والاستفادة من جميع العروض والمكافآت المتاحة في التطبيق.</p>
            </div>
        </div>

        <div class="email-footer">
            <div style="font-weight: 800; color: #475569; margin-bottom: 6px;">
                {{ $companyInfo?->name_ar ?? 'زينة للخدمات المنزلية' }}
            </div>
            <div>جميع الحقوق محفوظة &copy; {{ date('Y') }}</div>
        </div>
    </div>
</body>
</html>
