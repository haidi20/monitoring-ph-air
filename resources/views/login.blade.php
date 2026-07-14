<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring PH Air - Login</title>
    <style>
        body { font-family: Arial, sans-serif; background: #0f172a; color: #e2e8f0; margin: 0; min-height: 100vh; display: grid; place-items: center; }
        .card { width: min(100%, 420px); background: #111827; border: 1px solid #334155; border-radius: 18px; padding: 28px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
        h1 { margin: 0 0 8px; font-size: 28px; }
        p { margin: 0 0 20px; color: #94a3b8; }
        label { display: block; margin: 14px 0 6px; font-size: 14px; color: #cbd5e1; }
        input { width: 100%; box-sizing: border-box; padding: 12px 14px; border-radius: 12px; border: 1px solid #475569; background: #0b1220; color: #e2e8f0; }
        button { width: 100%; margin-top: 20px; padding: 12px 14px; border: 0; border-radius: 12px; background: #22c55e; color: #052e16; font-weight: 700; cursor: pointer; }
        .hint { margin-top: 14px; font-size: 13px; color: #94a3b8; }
        .error { background: #7f1d1d; color: #fecaca; padding: 10px 12px; border-radius: 10px; margin-bottom: 14px; }
        code { color: #86efac; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Monitoring PH Air</h1>
        <p>Silakan login untuk masuk ke dashboard.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}">
            @csrf
            <label for="username">Username</label>
            <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" required>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" autocomplete="current-password" required>

            <button type="submit">Masuk</button>
        </form>

        <div class="hint">Akun demo: <code>user</code> / <code>user</code></div>
    </div>
</body>
</html>
