<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring PH Air</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --line:#dbe4f0; --ink:#0f172a; --muted:#64748b; --green:#0f766e; }
        body { margin:0; font-family: Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .wrap { max-width: 1320px; margin: 0 auto; padding: 24px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom: 20px; }
        .brand h1 { margin:0; font-size: 28px; }
        .brand p { margin:6px 0 0; color: var(--muted); }
        .logout button { border:0; background:#ef4444; color:#fff; padding:10px 16px; border-radius:10px; cursor:pointer; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:16px; }
        .card { background: var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow: 0 10px 25px rgba(15,23,42,.05); }
        .chart-card { background: var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow: 0 10px 25px rgba(15,23,42,.05); margin-top: 18px; }
        .metric-title { color: var(--muted); font-size: 14px; }
        .metric-value { font-size: 42px; font-weight: 800; margin-top: 8px; color: var(--green); }
        .metric-meta { color: var(--muted); margin-top: 6px; }
        .charts { display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
        canvas { width:100% !important; height:320px !important; }
        .section-title { margin: 6px 0 14px; font-size: 18px; }
        .note { color: var(--muted); font-size: 13px; }
        @media (max-width: 768px){ .top { flex-direction: column; align-items:flex-start; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div class="brand">
            <h1>Monitoring PH Air</h1>
            <p>Login sebagai <strong>{{ $username }}</strong> · data refresh tiap 1 detik</p>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="logout">@csrf<button type="submit">Keluar</button></form>
    </div>

    <div class="grid" id="metricGrid">
        @foreach (['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'] as $key => $label)
            @php($item = $payload['latest'][$key] ?? ['nilai' => '-', 'waktu' => '-', 'satuan' => ''])
            <div class="card">
                <div class="metric-title">{{ $label }}</div>
                <div class="metric-value" id="metric-{{ $key }}">{{ $item['nilai'] }}</div>
                <div class="metric-meta" id="meta-{{ $key }}">{{ $item['waktu'] }} {{ $item['satuan'] ? '· '.$item['satuan'] : '' }}</div>
            </div>
        @endforeach
    </div>

    <div class="chart-card">
        <div class="section-title">Grafik Live</div>
        <div class="note">Grafik otomatis mengambil data terbaru dari MySQL setiap 1 detik.</div>
        <div class="charts">
            <div><canvas id="chart-phair"></canvas></div>
            <div><canvas id="chart-suhu"></canvas></div>
            <div><canvas id="chart-kekeruhan"></canvas></div>
        </div>
    </div>
</div>

<script>
const initialPayload = @json($payload);
const chartConfigs = {
    phair: { label: 'PH Air', color: 'rgba(37, 99, 235, 1)' },
    suhu: { label: 'Suhu Air', color: 'rgba(220, 38, 38, 1)' },
    kekeruhan: { label: 'Kekeruhan Air', color: 'rgba(16, 185, 129, 1)' },
};
const charts = {};

function buildChart(key) {
    const ctx = document.getElementById(`chart-${key}`);
    const series = initialPayload.series[key] || { labels: [], values: [] };
    charts[key] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: series.labels || [],
            datasets: [{
                label: chartConfigs[key].label,
                data: series.values || [],
                borderColor: chartConfigs[key].color,
                backgroundColor: chartConfigs[key].color.replace('1)', '0.15)'),
                tension: 0.25,
                fill: true,
                pointRadius: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            scales: { y: { beginAtZero: false } }
        }
    });
}

function updateChart(key, series) {
    if (!charts[key]) return;
    charts[key].data.labels = series.labels || [];
    charts[key].data.datasets[0].data = series.values || [];
    charts[key].update('none');
}

function updateMetric(key, item) {
    document.getElementById(`metric-${key}`).textContent = item?.nilai ?? '-';
    const waktu = item?.waktu ?? '-';
    const satuan = item?.satuan ? ` · ${item.satuan}` : '';
    document.getElementById(`meta-${key}`).textContent = `${waktu}${satuan}`;
}

async function refreshData() {
    try {
        const response = await fetch('{{ route('monitoring.api') }}', { headers: { 'Accept': 'application/json' } });
        if (!response.ok) return;
        const payload = await response.json();
        ['phair', 'suhu', 'kekeruhan'].forEach((key) => {
            updateMetric(key, payload.latest?.[key]);
            updateChart(key, payload.series?.[key] || { labels: [], values: [] });
        });
    } catch (error) {
        console.error(error);
    }
}

['phair', 'suhu', 'kekeruhan'].forEach(buildChart);
refreshData();
setInterval(refreshData, 1000);
</script>
</body>
</html>
