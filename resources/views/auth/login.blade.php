<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log in · Draftroom</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    @endif
</head>
<body class="auth-page">
<main class="auth-card">
    <div class="auth-heading"><h1>Log in to your workspace</h1><p>Continue working on your shared documents.</p></div>
    @if ($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('login.store') }}" class="auth-form">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required autofocus></label>
        <label>Password<input type="password" name="password" placeholder="Your password" required></label>
        <label class="remember"><input type="checkbox" name="remember"> Remember me</label>
        <button class="primary-button auth-submit" type="submit">Log in</button>
    </form>
    <p class="auth-footer">No account yet? <a href="{{ route('register') }}">Create one</a></p>
    <p class="auth-hint">Demo account: ivan@gmail.com · password123</p>
</main>
</body>
</html>
