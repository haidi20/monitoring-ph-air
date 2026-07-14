<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring PH Air - Dashboard</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f8fafc; color: #0f172a; margin: 0; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 24px; }
        .brand h1 { margin: 0; font-size: 30px; }
        .brand p { margin: 6px 0 0; color: #475569; }
        .logout button { border: 0; background: #ef4444; color: white; padding: 10px 16px; border-radius: 10px; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 18px; box-shadow: 0 10px 25px rgba(15, 23, 42, .05); }
        .value { font-size: 34px; font-weight: 800; margin-top: 8px; color: #0f766e; }
        .small { color: #64748b; margin-top: 6px; }
        .section { margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 16px; overflow: hidden; }
        th, td { padding: 14px 16px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        th { background: #0f172a; color: white; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="top">
            <div class="brand">
                <h1>Monitoring PH Air</h1>
                <p>Login berhasil sebagai <strong>{{ $username }}</strong></p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="logout">
                @csrf
                <button type="submit">Keluar</button>
            </form>
        </div>

        <div class="grid">
            <div class="card"><div>PH Air</div><div class="value">7.2</div><div class="small">Status normal</div></div>
            <div class="card"><div>Suhu</div><div class="value">28°C</div><div class="small">Stabil</div></div>
            <div class="card"><div>Kekeruhan</div><div class="value">3 NTU</div><div class="small">Baik</div></div>
        </div>

        <div class="section">
            <table>
                <thead>
                    <tr><th>Parameter</th><th>Nilai</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <tr><td>PH</td><td>7.2</td><td>Normal</td></tr>
                    <tr><td>Suhu</td><td>28°C</td><td>Normal</td></tr>
                    <tr><td>Kekeruhan</td><td>3 NTU</td><td>Normal</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
