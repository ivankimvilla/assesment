<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · Draftroom</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    @endif
</head>
<body class="auth-page">
<main class="auth-card">
    <a class="brand auth-brand" href="{{ route('login') }}"><span class="brand-mark">d</span><span>draftroom</span></a>
    <div class="auth-heading"><span class="eyebrow">Get started</span><h1>Create your account</h1><p>Set up your workspace and start writing together.</p></div>
    @if ($errors->any())<div class="auth-error">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('register.store') }}" class="auth-form">
        @csrf
        <label>Name<input type="text" name="name" value="{{ old('name') }}" placeholder="Your name" required autofocus></label>
        <label>Email<input type="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" required></label>
        <label>Password<input type="password" name="password" placeholder="At least 8 characters" required></label>
        <label>Confirm password<input type="password" name="password_confirmation" placeholder="Repeat your password" required></label>
        <button class="primary-button auth-submit" type="submit">Create account</button>
    </form>
    <p class="auth-footer">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
</main>
</body>
</html>
