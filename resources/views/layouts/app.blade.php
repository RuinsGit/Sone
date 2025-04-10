<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SoneAI')</title>
    @yield('styles')
</head>
<body>
    @yield('content')
    @yield('scripts')
</body>
</html> 