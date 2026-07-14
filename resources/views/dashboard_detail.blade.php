<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Grafik Sensor</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.5.0/css/font-awesome.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.3.0/Chart.bundle.js"></script>
    <style>
        body { background:#fff; color:#393939; font-family: Arial, Helvetica, sans-serif; padding:20px; }
        .header { line-height:28px; margin-bottom:16px; margin-top:18px; padding-bottom:4px; border-bottom:1px solid #CCC; }
        .smaller { font-size:21px; }
        .lighter { font-weight:lighter; }
        .green { color:#69aa46!important; }
        .blue { color:#478fca!important; }
        .alert-success { background:#dff0d8; border-color:#d6e9c6; color:#3c763d; }
        canvas { width:100%!important; height:360px!important; }
        .table-wrap { overflow-x:auto; }
        .detail-table th { background:#307ecc; color:white; text-align:center; }
        .detail-table td { text-align:center; }
    </style>
</head>
<body>
@php
    $sensorNames = ['phair' => 'PH Air', 'suhu' => 'Suhu Air', 'kekeruhan' => 'Kekeruhan Air'];
    $sensorTitle = $sensorNames[$sensor] ?? strtoupper($sensor);
    $latest = $payload['latest'][$sensor] ?? ['nilai' => '-', 'waktu' => '-', 'satuan' => ''];
    $series = $payload['series'][$sensor] ?? ['labels' => [], 'values' => []];
@endphp
<div class="container-fluid">
    <a class="btn btn-default" href="{{ route('dashboard') }}"><i class="fa fa-arrow-left"></i> Kembali</a>
    <h3 class="header smaller lighter green"># Grafik {{ $sensorTitle }} [{{ $series['id_sensor_project'] ?? '-' }}]</h3>
    <canvas id="myChartDetail" width="100%" height="50"></canvas>
    <br>
    <div class="table-wrap">
        <table class="table table-bordered detail-table">
            <thead>
                <tr><th>No</th><th>Nama</th><th>Waktu</th><th>Nilai</th></tr>
            </thead>
            <tbody id="detailRows">
                @foreach (collect($series['labels'])->reverse()->take(50) as $index => $waktu)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $sensorTitle }}</td>
                        <td>{{ $waktu }}</td>
                        <td>{{ collect($series['values'])->reverse()->values()[$index] ?? '-' }} {{ $latest['satuan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="alert alert-success">
        <strong>
            <i class="ace-icon fa fa-refresh"></i>
            {{ $sensorTitle }} Realtime [ <span id="detailWaktu">{{ $latest['waktu'] }}</span> ] :
            <b><span id="detailNilai">{{ $latest['nilai'] }}</span> <span id="detailSatuan">{{ $latest['satuan'] }}</span></b>
        </strong>
        <br>
    </div>
    <a class="btn btn-primary" href="#" onclick="window.print(); return false;">Print Report</a>
</div>
<script>
var sensorKey = @json($sensor);
var sensorTitle = @json($sensorTitle);
var initialPayload = @json($payload);
var series = initialPayload.series[sensorKey] || { labels: [], values: [] };
var ctx = document.getElementById('myChartDetail');
var myChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: series.labels || [],
        datasets: [{
            label: '# Grafik ' + sensorTitle,
            data: series.values || [],
            backgroundColor: ['rgba(23, 99, 132, 0.2)'],
            borderColor: ['rgba(23, 99, 132, 1)'],
            borderWidth: 1
        }]
    },
    options: {
        animation: false,
        scales: { yAxes: [{ ticks: { beginAtZero: true } }] }
    }
});

function renderRows(payload) {
    var labels = ((payload.series[sensorKey] || {}).labels || []).slice().reverse();
    var values = ((payload.series[sensorKey] || {}).values || []).slice().reverse();
    var satuan = ((payload.latest[sensorKey] || {}).satuan || '');
    document.getElementById('detailRows').innerHTML = labels.slice(0, 50).map(function(waktu, index) {
        return '<tr><td>' + (index + 1) + '</td><td>' + sensorTitle + '</td><td>' + waktu + '</td><td>' + (values[index] || '-') + ' ' + satuan + '</td></tr>';
    }).join('');
}

function refreshDetail() {
    fetch('{{ route('monitoring.api') }}', { headers: { 'Accept': 'application/json' } })
        .then(function(response) { return response.json(); })
        .then(function(payload) {
            var latest = payload.latest[sensorKey] || { nilai: '-', waktu: '-', satuan: '' };
            var series = payload.series[sensorKey] || { labels: [], values: [] };
            document.getElementById('detailNilai').textContent = latest.nilai || '-';
            document.getElementById('detailWaktu').textContent = latest.waktu || '-';
            document.getElementById('detailSatuan').textContent = latest.satuan || '';
            myChart.data.labels = series.labels || [];
            myChart.data.datasets[0].data = series.values || [];
            myChart.update();
            renderRows(payload);
        });
}

setInterval(refreshDetail, 1000);
</script>
</body>
</html>
