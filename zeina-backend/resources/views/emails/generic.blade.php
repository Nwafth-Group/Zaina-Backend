<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>{{ $mailSubject }}</title>
</head>
<body>
    <h2>تطبيق زينة - Zeina App</h2>
    <hr>
    
    <p>{{ $mailData['body'] ?? '' }}</p>
    
    @if(isset($mailData['details']))
        <ul>
            @foreach($mailData['details'] as $key => $value)
                <li><strong>{{ $key }}:</strong> {{ $value }}</li>
            @endforeach
        </ul>
    @endif

    <br>
    <p> Thank you for use Zainah App.</p>
</body>
</html>