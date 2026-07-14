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
        .sensor-row { display:flex; gap:14px; align-items:stretch; flex-wrap:nowrap; }
        .sensor-panel { flex:1 1 0; min-width:0; }
        .detail-row { display:flex; gap:14px; align-items:flex-start; flex-wrap:nowrap; }
        .detail-panel { flex:1 1 0; min-width:0; }
        .header { line-height:28px; margin-bottom:16px; margin-top:18px; padding-bottom:4px; border-bottom:1px solid #CCC; }
        .smaller { font-size:21px; }
        .lighter { font-weight:lighter; }
        .blue { color:#478fca!important; }
        .green { color:#69aa46!important; }
        .list-group { display:block; flex-direction:column; padding-bottom:-4px; margin-bottom:5px; }
        .list-group-item.active { z-index:2; margin-top:18px; color:#fff; background-color:#009ef7; border-color:#009ef7; }
        .btn-success, .btn-success.focus, .btn-success:focus { background-color:#45bb32!important; border-color:#3ac324; }
        .alert-info { background:#d9edf7; border-color:#bce8f1; color:#31708f; }
        .alert-success { background:#dff0d8; border-color:#d6e9c6; color:#3c763d; }
        canvas { width:100%!important; height:310px!important; }
        .sensor-panel { margin-bottom:24px; }
        .top-actions { display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
        .table thead th { background:#307ecc; color:#fff; text-align:center; }
        .table td { text-align:center; }
        @media (max-width:768px){ .top-actions{flex-direction:column;} .sensor-row,.detail-row{flex-direction:column; flex-wrap:wrap;} canvas{height:280px!important;} }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="top-actions">
        <div style="width:100%;">
            <h3 class="header smaller lighter blue">Dashboard Internet of Things</h3>
            <div class="alert alert-info">
                Informasi detail informasi project : {{ $payload['project']['nama_project'] ?? 'Monitoring PH Air' }}
                <br>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-danger" type="submit">Keluar</button></form>
    </div>

    <div class="sensor-row">
        @foreach (["phair" => "PH Air", "suhu" => "Suhu Air", "kekeruhan" => "Kekeruhan Air"] as $key => $namaSensor)
            @php($latest = $payload["latest"][$key] ?? ["nilai" => "-", "waktu" => "-", "satuan" => ""])
            <div class="sensor-panel">
                <h3 class="header smaller lighter green"># {{ $namaSensor }}</h3>
                <canvas id="myChart{{ $key }}" width="100%" height="50"></canvas>
                Lihat Tabel : <br>
                <a class="btn btn-primary btn-xs" href="{{ route('monitoring.detail', $key) }}">Lihat detail tabel {{ $namaSensor }}</a>
                <hr>
                <div class="alert alert-success">
                    <strong>
                        <i class="ace-icon fa fa-refresh"></i>
                        {{ $namaSensor }} <br>Realtime [ <span id="waktu-{{ $key }}">{{ $latest['waktu'] }}</span> ] :
                        <b><span id="nilai-{{ $key }}">{{ $latest['nilai'] }}</span> <span id="satuan-{{ $key }}">{{ $latest['satuan'] }}</span></b>
                    </strong>
                    <br>
                </div>
            </div>
        @endforeach
    </div>

    <h3 class="header smaller lighter blue">Detail Sensor Realtime</h3>
    <div class="detail-row">
        @foreach (['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'] as $key => $sensorLabel)
            @php($series = $payload['series'][$key] ?? ['labels' => [], 'values' => []])
            <div class="detail-panel" id="detail-{{ $key }}">
                <h3 class="header smaller lighter green"># Detail {{ $sensorLabel }}</h3>
                <div style="overflow-x:auto;">
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
        @endforeach
    </div>
</div>

<script>
var initialPayload = @json($payload);
var sensorLabels = { phair: 'PH Air', suhu: 'Suhu Air', kekeruhan: 'Kekeruhan Air' };
var chartColors = {
    phair: 'rgba(23, 99, 132, 0.2)',
    suhu: 'rgba(54, 162, 235, 0.2)',
    kekeruhan: 'rgba(54, 206, 86, 0.2)'
};
var borderColors = {
    phair: 'rgba(23, 99, 132, 0.2)',
    suhu: 'rgba(54, 162, 235, 0.2)',
    kekeruhan: 'rgba(54, 206, 86, 0.2)'
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
                borderWidth: 1
            }]
        },
        options: {
            animation: false,
            scales: { yAxes: [{ ticks: { beginAtZero: true } }] }
        }
    });
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
                updateDetailRows(key, payload);
            });
        });
}

['phair', 'suhu', 'kekeruhan'].forEach(makeChart);
setInterval(refreshDashboard, 1000);
</script>
</body>
</html>
