<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث بشأن طلب توثيق الحساب</title>
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
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
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
        .warning-badge {
            display: inline-block;
            background-color: #FEE2E2;
            color: #991B1B;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 16px;
            border: 1px solid #FCA5A5;
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
        .rejection-box {
            background-color: #FEF2F2;
            border: 1.5px solid #FCA5A5;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 28px;
        }
        .rejection-title {
            font-size: 14px;
            font-weight: 800;
            color: #991B1B;
            margin-bottom: 10px;
        }
        .rejection-reason {
            font-size: 14px;
            color: #7F1D1D;
            font-weight: 600;
            background-color: #FFFFFF;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid #FECACA;
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
            <div class="warning-badge">
                ⚠️ لم تتم الموافقة على التوثيق
            </div>

            <div class="greeting">
                أهلاً بك، {{ $customer->first_name }} {{ $customer->last_name }} 👋
            </div>

            <p class="intro-text">
                نود إفادتك بأنه تعذر قبول طلب توثيق حسابك حالياً في منصة <b>{{ $companyInfo?->name_ar ?? 'زينة' }}</b>.
            </p>

            <div class="rejection-box">
                <div class="rejection-title">📋 سبب عدم القبول:</div>
                <div class="rejection-reason">
                    {{ $reason }}
                </div>
            </div>

            <p style="font-size: 13px; color: #64748B; line-height: 1.6;">
                💡 <b>ما الذي يجب عليك فعله الآن؟</b><br>
                يرجى فتح التطبيق والانتقال إلى صفحة توثيق الحساب لتعديل المستندات المطلوبة وإعادة إرسال الطلب ليتم مراجعته مرة أخرى.
            </p>
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
