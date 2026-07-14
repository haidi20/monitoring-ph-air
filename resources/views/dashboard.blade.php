<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Internet of Things</title>
    <link rel="stylesheet" href="/legacy-assets/ace/css/bootstrap.min.css">
    <link rel="stylesheet" href="/legacy-assets/ace/css/font-awesome.min.css">
    <link rel="stylesheet" href="/legacy-assets/ace/css/ace.min.css">
    <script src="/legacy-assets/ace/js/jquery-2.1.4.min.js"></script>
    <script src="/legacy-assets/ace/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.3.0/Chart.bundle.js"></script>
    <style>
        body { background:#fff; color:#393939; font-family: Arial, Helvetica, sans-serif; padding:10px 18px 20px; }
        .header { line-height:28px; margin-bottom:16px; margin-top:18px; padding-bottom:4px; border-bottom:1px solid #CCC; }
        .smaller { font-size:21px; }
        .lighter { font-weight:lighter; }
        .blue { color:#478fca!important; }
        .green { color:#69aa46!important; }
        .topbar { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .hero { background:linear-gradient(135deg, #0f172a 0%, #2348d4 100%); color:#fff; border-radius:22px; padding:20px 24px; margin: 10px 0 20px; }
        .hero h2 { margin:0 0 6px; font-size:24px; font-weight:700; }
        .hero p { margin:0; font-size:16px; opacity:.95; }
        .metric-card { background:#fff; border:1px solid #d7dff0; border-radius:18px; padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.05); margin-bottom:18px; min-height:210px; }
        .metric-title { font-size:16px; color:#4b6cb7; margin-bottom:6px; }
        .metric-value { font-size:48px; line-height:1; font-weight:800; margin: 8px 0; }
        .metric-meta { font-size:16px; color:#51617d; min-height:24px; }
        .metric-actions { display:flex; gap:10px; margin-top:18px; }
        .metric-actions .btn { border-radius:10px; padding:9px 14px; }
        .chart-card { background:#fff; border:1px solid #d7dff0; border-radius:18px; padding:14px 16px 16px; box-shadow:0 8px 20px rgba(15,23,42,.05); margin-bottom:18px; }
        .chart-head { display:flex; justify-content:space-between; align-items:center; gap:10px; margin-bottom:4px; }
        .chart-head h3 { margin:0; font-weight:700; font-size:20px; color:#1f2d3d; }
        .jump { color:#365cff; font-size:15px; }
        canvas { width:100%!important; height:340px!important; }
        .detail-title { font-size:22px; font-weight:700; margin:10px 0 14px; color:#1f2d3d; }
        .detail-card { background:#fff; border:1px solid #d7dff0; border-radius:18px; padding:16px; box-shadow:0 8px 20px rgba(15,23,42,.05); margin-bottom:18px; }
        .pill { display:inline-block; padding:5px 11px; border-radius:999px; background:#e9eefc; color:#2246d1; font-size:13px; }
        .table thead th { background:#307ecc; color:#fff; text-align:center; }
        .table td { text-align:center; }
        .alert-success { background:#dff0d8; border-color:#d6e9c6; color:#3c763d; }
        .alert-info { background:#d9edf7; border-color:#bce8f1; color:#31708f; }
        @media (max-width: 768px) { .topbar { flex-direction:column; } canvas { height:280px!important; } .metric-value { font-size:40px; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="topbar">
        <div>
            <h3 class="header smaller lighter blue" style="margin-top:0;">Dashboard Internet of Things</h3>
            <div class="alert alert-info">Informasi detail informasi project : {{ $payload['project']['nama_project'] ?? 'Monitoring PH Air' }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-danger" type="submit">Keluar</button></form>
    </div>

    <div class="hero">
        <h2>Monitoring PH Air</h2>
        <p>Data realtime pH air, suhu air, dan kekeruhan air tampil langsung. Klik detail untuk melihat tabel dan grafik per sensor.</p>
    </div>

    <div class="row">
        @foreach (['phair' => ['label' => 'PH Air', 'color' => '#365cff'], 'suhu' => ['label' => 'Suhu Air', 'color' => '#e53935'], 'kekeruhan' => ['label' => 'Kekeruhan Air', 'color' => '#0f9d58']] as $key => $cfg)
            @php($latest = $payload['latest'][$key] ?? ['nilai' => '-', 'waktu' => '-', 'satuan' => ''])
            <div class="col-md-4 col-sm-12">
                <div class="metric-card">
                    <div class="metric-title">{{ $cfg['label'] }}</div>
                    <div class="metric-value" style="color: {{ $cfg['color'] }}" id="nilai-{{ $key }}">{{ $latest['nilai'] }}</div>
                    <div class="metric-meta" id="waktu-{{ $key }}">{{ $latest['waktu'] }} {{ $latest['satuan'] ? '· '.$latest['satuan'] : '' }}</div>
                    <div class="metric-actions">
                        <a class="btn btn-primary" href="{{ route('monitoring.detail', $key) }}">Lihat detail</a>
                        <a class="btn btn-default" href="#chart-{{ $key }}">Ke grafik</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="detail-title">Grafik Live</h3>
    <div class="row">
        @foreach (['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'] as $key => $label)
            <div class="col-md-4 col-sm-12" id="chart-{{ $key }}">
                <div class="chart-card">
                    <div class="chart-head">
                        <h3>{{ $label }}</h3>
                        <a href="{{ route('monitoring.detail', $key) }}" class="jump">Buka detail</a>
                    </div>
                    <canvas id="myChart{{ $key }}"></canvas>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="detail-title">Detail Sensor Realtime</h3>
    <div class="row">
        @foreach (['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'] as $key => $sensorLabel)
            @php($series = $payload['series'][$key] ?? ['labels' => [], 'values' => []])
            <div class="col-md-12">
                <div class="detail-card" id="detail-{{ $key }}">
                    <div class="topbar" style="align-items:center;">
                        <h3 style="margin:0; font-size:20px;">{{ $sensorLabel }}</h3>
                        <span class="pill" id="pill-{{ $key }}">{{ ($payload['latest'][$key]['nilai'] ?? '-') }} {{ $payload['latest'][$key]['satuan'] ?? '' }}</span>
                    </div>
                    <div style="overflow-x:auto; margin-top:12px;">
                        <table class="table table-bordered">
                            <thead><tr><th>No</th><th>Nama</th><th>Waktu</th><th>Nilai</th></tr></thead>
                            <tbody id="detailRows-{{ $key }}">
                                @foreach (collect($series['labels'])->reverse()->take(10) as $idx => $labelTime)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $sensorLabel }}</td>
                                        <td>{{ $labelTime }}</td>
                                        <td>{{ collect($series['values'])->reverse()->values()[$idx] ?? '-' }} {{ $payload['latest'][$key]['satuan'] ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
var initialPayload = @json($payload);
var sensorLabels = { phair: 'PH Air', suhu: 'Suhu Air', kekeruhan: 'Kekeruhan Air' };
var chartColors = { phair: 'rgba(54, 92, 255, 0.20)', suhu: 'rgba(229, 57, 53, 0.20)', kekeruhan: 'rgba(15, 157, 88, 0.20)' };
var borderColors = { phair: 'rgba(54, 92, 255, 1)', suhu: 'rgba(229, 57, 53, 1)', kekeruhan: 'rgba(15, 157, 88, 1)' };
var charts = {};
function makeChart(key) {
    var ctx = document.getElementById('myChart' + key);
    var series = initialPayload.series[key] || { labels: [], values: [] };
    charts[key] = new Chart(ctx, { type: 'line', data: { labels: series.labels || [], datasets: [{ label: '# Grafik ' + sensorLabels[key], data: series.values || [], backgroundColor: chartColors[key], borderColor: borderColors[key], borderWidth: 2, pointRadius: 2, fill: true, lineTension: 0.15 }] }, options: { responsive: true, maintainAspectRatio: false, animation: false, scales: { yAxes: [{ ticks: { beginAtZero: true } }] }, legend: { display: true } } });
}
function updateDetailRows(key, payload) {
    var labels = ((payload.series[key] || {}).labels || []).slice().reverse();
    var values = ((payload.series[key] || {}).values || []).slice().reverse();
    var satuan = ((payload.latest[key] || {}).satuan || '');
    var html = '';
    for (var i = 0; i < Math.min(labels.length, 10); i++) html += '<tr><td>' + (i + 1) + '</td><td>' + sensorLabels[key] + '</td><td>' + labels[i] + '</td><td>' + (values[i] ?? '-') + ' ' + satuan + '</td></tr>';
    document.getElementById('detailRows-' + key).innerHTML = html;
}
function refreshDashboard() {
    fetch('{{ route('monitoring.api') }}', { headers: { 'Accept': 'application/json' } }).then(function(response) { return response.json(); }).then(function(payload) {
        ['phair', 'suhu', 'kekeruhan'].forEach(function(key) {
            var latest = payload.latest[key] || { nilai: '-', waktu: '-', satuan: '' };
            document.getElementById('nilai-' + key).textContent = latest.nilai || '-';
            document.getElementById('waktu-' + key).textContent = (latest.waktu || '-') + (latest.satuan ? ' · ' + latest.satuan : '');
            var pill = document.getElementById('pill-' + key); if (pill) pill.textContent = (latest.nilai || '-') + ' ' + (latest.satuan || '');
            if (charts[key]) { charts[key].data.labels = (payload.series[key] || {}).labels || []; charts[key].data.datasets[0].data = (payload.series[key] || {}).values || []; charts[key].update(); }
            updateDetailRows(key, payload);
        });
    });
}
['phair', 'suhu', 'kekeruhan'].forEach(makeChart);
setInterval(refreshDashboard, 1000);
</script>
</body>
</html>
