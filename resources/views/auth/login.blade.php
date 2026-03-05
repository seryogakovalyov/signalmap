@extends('layouts.app')

@section('content')
    <style>
        :root {
            --bg: #f8fafc;
            --panel: rgba(255, 255, 255, 0.96);
            --border: #dbe3ea;
            --text: #0f172a;
            --muted: #475569;
            --primary: #155e75;
            --primary-dark: #164e63;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Instrument Sans", "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(21, 94, 117, 0.15), transparent 34%),
                radial-gradient(circle at bottom right, rgba(2, 132, 199, 0.14), transparent 32%),
                var(--bg);
            display: grid;
            place-items: center;
            padding: 1rem;
        }

        .card {
            width: min(100%, 440px);
            padding: 2rem;
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            background: var(--panel);
            backdrop-filter: blur(12px);
            box-shadow: 0 22px 42px rgba(15, 23, 42, 0.12);
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: clamp(2rem, 6vw, 2.7rem);
            line-height: 1;
        }

        p {
            margin: 0 0 1.25rem;
            color: var(--muted);
            line-height: 1.6;
        }

        form {
            display: grid;
            gap: 1rem;
        }

        label {
            display: grid;
            gap: 0.4rem;
            font-weight: 600;
            font-size: 0.95rem;
        }

        input,
        button {
            font: inherit;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 0.85rem 0.95rem;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
        }

        .remember {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-weight: 500;
            color: var(--muted);
        }

        .remember input {
            width: 1rem;
            height: 1rem;
        }

        .errors {
            padding: 0.9rem 1rem;
            border-radius: 14px;
            background: rgba(185, 28, 28, 0.1);
            color: #991b1b;
            font-size: 0.94rem;
        }

        button {
            border: 0;
            border-radius: 999px;
            padding: 0.9rem 1rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 14px 26px rgba(8, 47, 73, 0.2);
        }

        .meta {
            margin-top: 1rem;
            font-size: 0.88rem;
            color: var(--muted);
        }
    </style>

    <main class="card">
        <h1>Sign in</h1>
        <p>Authorized staff can access the moderation panel after authentication.</p>

        @if ($errors->any())
            <div class="errors">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}">
            @csrf

            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus>
            </label>

            <label>
                Password
                <input type="password" name="password" required>
            </label>

            <label class="remember">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                <span>Remember me</span>
            </label>

            <button type="submit">Log in</button>
        </form>

    </main>
@endsection
