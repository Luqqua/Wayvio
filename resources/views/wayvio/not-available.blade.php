<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f5f5;
            color: #222;
            font-family: "Segoe UI", Arial, sans-serif;
        }
        .message {
            padding: 0 1.25rem;
            font-size: 1rem;
            text-align: center;
            letter-spacing: 0.01em;
        }
    </style>
</head>
<body>
    <p class="message">
        {{ __('messages.hub.publish.not_available') }}
    </p>
</body>
</html>
