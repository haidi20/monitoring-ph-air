<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Internet of Things</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.3.0/Chart.bundle.js"></script>
    <style>
        body { background:#fff; color:#393939; font-family: Arial, Helvetica, sans-serif; padding:20px; }
        .navbar-lite { display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; }
        .header { line-height:28px; margin-bottom:16px; margin-top:18px; padding-bottom:4px; border-bottom:1px solid #CCC; }
        .smaller { font-size:21px; }
        .lighter { font-weight:lighter; }
        .blue { color:#478fca!important; }
        .green { color:#69aa46!important; }
        .alert-success { background:#dff0d8; border-color:#d6e9c6; color:#3c763d; }
        .alert-info { background:#d9edf7; border-color:#bce8f1; color:#31708f; }
        .btn-success, .btn-success.focus, .btn-success:focus { background-color:#45bb32!important; border-color:#3ac324; }
        .chart-box { margin-bottom:25px; min-height:440px; }
        canvas { width:100%!important; height:260px!important; }
        .table-wrap { overflow-x:auto; }
        .detail-table th { background:#307ecc; color:white; text-align:center; }
        .detail-table td { text-align:center; }
        .top-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="navbar-lite">
        <div>
            <h3 class="header smaller lighter blue" style="margin-top:0;">Dashboard Internet of Things</h3>
            <div class="alert alert-info">Informasi detail informasi project : {{ $payload['project']['nama_project'] ?? 'Monitoring PH Air' }}</div>
        </div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-danger" type="submit">Keluar</button></form>
    </div>

    <div class="row">
        @foreach (['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'] as $key => $sensorTitle)
            @php($latest = $payload['latest'][$key] ?? ['nilai' => '-', 'waktu' => '-', 'satuan' => ''])
            <div class="col-sm-4 chart-box">
                <h3 class="header smaller lighter green"># {{ $sensorTitle }}</h3>
                <canvas id="myChart{{ $key }}" width="100%" height="50"></canvas>
                <div class="alert alert-success">
                    <strong>
                        <i class="ace-icon fa fa-refresh"></i>
                        {{ $sensorTitle }} <br>Realtime [ <span id="waktu-{{ $key }}">{{ $latest['waktu'] }}</span> ] :
                        <b><span id="nilai-{{ $key }}">{{ $latest['nilai'] }}</span> <span id="satuan-{{ $key }}">{{ $latest['satuan'] }}</span></b>
                    </strong>
                    <br>
                </div>
                <div class="top-actions">
                    Lihat Tabel : <a class="btn btn-primary btn-xs" href="{{ route('monitoring.detail', $key) }}">Lihat detail tabel {{ $sensorTitle }}</a>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
var initialPayload = @json($payload);
var sensorLabels = { phair: 'PH Air', suhu: 'Suhu Air', kekeruhan: 'Kekeruhan Air' };
var chartColors = {
    phair: 'rgba(23, 99, 132, 0.2)',
    suhu: 'rgba(220, 38, 38, 0.2)',
    kekeruhan: 'rgba(16, 185, 129, 0.2)'
};
var borderColors = {
    phair: 'rgba(23, 99, 132, 1)',
    suhu: 'rgba(220, 38, 38, 1)',
    kekeruhan: 'rgba(16, 185, 129, 1)'
};
var charts = {};

function makeChart(key) {
    var ctx = document.getElementById('myChart' + key);
    var series = initialPayload.series[key] || { labels: [], values: [] };
    charts[key] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: series.labels || [],
            datasets: [{
                label: '# Grafik ' + sensorLabels[key],
                data: series.values || [],
                backgroundColor: [chartColors[key]],
                borderColor: [borderColors[key]],
                borderWidth: 1,
                fill: true
            }]
        },
        options: {
            animation: false,
            scales: {
                yAxes: [{ ticks: { beginAtZero: true } }]
            }
        }
    });
}

function refreshDashboard() {
    fetch('{{ route('monitoring.api') }}', { headers: { 'Accept': 'application/json' } })
        .then(function(response) { return response.json(); })
        .then(function(payload) {
            ['phair', 'suhu', 'kekeruhan'].forEach(function(key) {
                var latest = payload.latest[key] || { nilai: '-', waktu: '-', satuan: '' };
                document.getElementById('nilai-' + key).textContent = latest.nilai || '-';
                document.getElementById('waktu-' + key).textContent = latest.waktu || '-';
                document.getElementById('satuan-' + key).textContent = latest.satuan || '';
                if (charts[key]) {
                    charts[key].data.labels = (payload.series[key] || {}).labels || [];
                    charts[key].data.datasets[0].data = (payload.series[key] || {}).values || [];
                    charts[key].update();
                }
            });
        });
}

['phair', 'suhu', 'kekeruhan'].forEach(makeChart);
setInterval(refreshDashboard, 1000);
</script>
</body>
</html>
