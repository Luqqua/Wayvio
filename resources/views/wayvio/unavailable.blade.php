<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WAYVIO</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", "Helvetica Neue", Arial, sans-serif;
            background: radial-gradient(circle at 20% 20%, #f4f5f8, #e5e7eb 55%, #d1d5db);
            color: #111827;
        }
        .card {
            width: min(620px, 92vw);
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid #d1d5db;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 12px 40px rgba(17, 24, 39, 0.08);
        }
        h1 {
            margin: 0 0 10px;
            font-size: 1.45rem;
            line-height: 1.3;
        }
        p {
            margin: 0;
            font-size: 1rem;
            line-height: 1.6;
            color: #374151;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>{{ __('This page is temporarily unavailable.') }}</h1>
    <p>{{ __('No content has been deleted. Access may return once the account is reactivated.') }}</p>
</div>
</body>
</html>
