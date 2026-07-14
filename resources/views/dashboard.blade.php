<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoring PH Air</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --line:#dbe4f0; --ink:#0f172a; --muted:#64748b; --green:#0f766e; --blue:#2563eb; --red:#dc2626; }
        body { margin:0; font-family: Arial, sans-serif; background: var(--bg); color: var(--ink); }
        .wrap { max-width: 1400px; margin: 0 auto; padding: 24px; }
        .top { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom: 18px; }
        .brand h1 { margin:0; font-size: 28px; }
        .brand p { margin:6px 0 0; color: var(--muted); }
        .logout button { border:0; background:#ef4444; color:#fff; padding:10px 16px; border-radius:10px; cursor:pointer; }
        .hero { background: linear-gradient(135deg, #0f172a, #1d4ed8); color: #fff; border-radius: 22px; padding: 22px; margin-bottom: 18px; }
        .hero h2 { margin:0 0 8px; font-size: 24px; }
        .hero p { margin:0; opacity:.92; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap:16px; }
        .card { background: var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow: 0 10px 25px rgba(15,23,42,.05); }
        .metric-title { color: var(--muted); font-size: 14px; }
        .metric-value { font-size: 42px; font-weight: 800; margin-top: 8px; color: var(--green); }
        .metric-meta { color: var(--muted); margin-top: 6px; min-height: 36px; }
        .actions { margin-top: 14px; display:flex; gap:10px; flex-wrap:wrap; }
        .btn-link { display:inline-block; text-decoration:none; background:#0f172a; color:#fff; padding:10px 14px; border-radius:10px; font-size: 14px; }
        .btn-link.secondary { background:#2563eb; }
        .section { margin-top: 22px; }
        .section-title { margin: 0 0 12px; font-size: 20px; }
        .charts { display:grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
        .chart-card { background: var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow: 0 10px 25px rgba(15,23,42,.05); }
        .chart-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px; }
        .chart-head h3 { margin:0; font-size: 18px; }
        .jump { color: var(--blue); text-decoration:none; font-size: 14px; }
        canvas { width:100% !important; height:320px !important; }
        .detail-list { display:grid; gap:16px; }
        .detail-box { background: var(--card); border:1px solid var(--line); border-radius:18px; padding:18px; box-shadow: 0 10px 25px rgba(15,23,42,.05); }
        .detail-box h3 { margin:0 0 12px; }
        table { width:100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; border-bottom:1px solid var(--line); text-align:left; }
        th { background:#0f172a; color:#fff; }
        .pill { display:inline-block; padding: 6px 10px; border-radius:999px; background:#e2e8f0; color:#0f172a; font-size:12px; }
        @media (max-width: 768px){ .top { flex-direction:column; align-items:flex-start; } .hero h2 { font-size: 20px; } }
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

    <div class="hero">
        <h2>Dashboard Internet of Things</h2>
        <p>Grafik pH air, suhu air, dan kekeruhan air tampil realtime. Klik kartu atau tombol detail untuk lompat ke bagian detail sensor.</p>
    </div>

    <div class="grid" id="metricGrid">
        @foreach (['phair' => ['label' => 'PH Air', 'color' => 'var(--blue)'], 'suhu' => ['label' => 'Suhu Air', 'color' => 'var(--red)'], 'kekeruhan' => ['label' => 'Kekeruhan Air', 'color' => 'var(--green)']] as $key => $cfg)
            @php($item = $payload['latest'][$key] ?? ['nilai' => '-', 'waktu' => '-', 'satuan' => ''])
            <div class="card">
                <div class="metric-title">{{ $cfg['label'] }}</div>
                <div class="metric-value" style="color: {{ $cfg['color'] }}" id="metric-{{ $key }}">{{ $item['nilai'] }}</div>
                <div class="metric-meta" id="meta-{{ $key }}">{{ $item['waktu'] }} {{ $item['satuan'] ? '· '.$item['satuan'] : '' }}</div>
                <div class="actions">
                    <a href="#detail-{{ $key }}" class="btn-link secondary">Lihat detail</a>
                    <a href="#chart-{{ $key }}" class="btn-link">Ke grafik</a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="section">
        <h2 class="section-title">Grafik Live</h2>
        <div class="charts">
            @foreach (['phair' => ['label' => 'PH Air'], 'suhu' => ['label' => 'Suhu Air'], 'kekeruhan' => ['label' => 'Kekeruhan Air']] as $key => $cfg)
                <div class="chart-card" id="chart-{{ $key }}">
                    <div class="chart-head">
                        <h3>{{ $cfg['label'] }}</h3>
                        <a href="#detail-{{ $key }}" class="jump">Buka detail</a>
                    </div>
                    <canvas id="canvas-{{ $key }}"></canvas>
                </div>
            @endforeach
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Detail Sensor Realtime</h2>
        <div class="detail-list">
            @foreach (['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'] as $key => $sensorLabel)
                <div class="detail-box" id="detail-{{ $key }}">
                    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
                        <h3 style="margin:0;">{{ $sensorLabel }}</h3>
                        <span class="pill" id="pill-{{ $key }}">{{ ($payload['latest'][$key]['nilai'] ?? '-') }} {{ $payload['latest'][$key]['satuan'] ?? '' }}</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Nilai</th>
                                </tr>
                            </thead>
                            <tbody id="table-{{ $key }}">
                                @php($series = $payload['series'][$key] ?? ['labels' => [], 'values' => []])
                                @foreach (array_slice($series['labels'], -10, 10, true) as $idx => $labelTime)
                                    <tr>
                                        <td>{{ $labelTime }}</td>
                                        <td>{{ $series['values'][$idx] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
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
    const ctx = document.getElementById(`canvas-${key}`);
    const series = initialPayload.series?.[key] || { labels: [], values: [] };
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
            plugins: { legend: { display: true } },
            scales: { y: { beginAtZero: false } }
        }
    });
}

function renderRows(key, series) {
    const body = document.getElementById(`table-${key}`);
    if (!body) return;
    const labels = series.labels || [];
    const values = series.values || [];
    const start = Math.max(labels.length - 10, 0);
    const rows = labels.slice(start).map((label, index) => {
        const realIndex = start + index;
        return `<tr><td>${label}</td><td>${values[realIndex] ?? '-'}</td></tr>`;
    }).join('');
    body.innerHTML = rows || '<tr><td colspan="2">Belum ada data</td></tr>';
}

function updateMetric(key, item) {
    document.getElementById(`metric-${key}`).textContent = item?.nilai ?? '-';
    const waktu = item?.waktu ?? '-';
    const satuan = item?.satuan ? ` · ${item.satuan}` : '';
    document.getElementById(`meta-${key}`).textContent = `${waktu}${satuan}`;
    const pill = document.getElementById(`pill-${key}`);
    if (pill) pill.textContent = `${item?.nilai ?? '-'}${item?.satuan ? ' ' + item.satuan : ''}`;
}

function updateChart(key, series) {
    if (!charts[key]) return;
    charts[key].data.labels = series.labels || [];
    charts[key].data.datasets[0].data = series.values || [];
    charts[key].update('none');
    renderRows(key, series);
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
