<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoringController extends Controller
{
    private string $defaultApiKey = 'e2907e0d85c49fcbff5ac006c696f6f9';

    public function ingest(Request $request)
    {
        $apikey = $request->input('apikey');

        if (blank($apikey)) {
            return response('Gagal');
        }

        $project = DB::table('data_project')
            ->where('apikey', $apikey)
            ->where('status', 'aktif')
            ->first();

        if (! $project) {
            return response('Gagal');
        }

        $sensors = DB::table('data_sensor_project')
            ->where('id_project', $project->id_project)
            ->where('status', 'aktif')
            ->get();

        foreach ($sensors as $sensor) {
            if (! $request->has($sensor->prefix)) {
                continue;
            }

            DB::table('data_input')->insert([
                'id_input' => 'INP'.now()->format('YmdHisv').random_int(100, 999),
                'id_project' => $project->id_project,
                'id_sensor_project' => $sensor->id_sensor_project,
                'waktu' => now()->format('Y-m-d H:i:s'),
                'nilai' => (string) $request->input($sensor->prefix),
            ]);
        }

        return response()->json([
            'out_1' => '0',
            'out_2' => '0',
            'out_3' => '0',
            'out_4' => '0',
            'out_5' => '0',
            'out_6' => '0',
            'out_7' => '0',
            'out_8' => '0',
            'out_9' => '0',
            'out_10' => '0',
        ]);
    }

    public function dashboard()
    {
        if (! session()->has('monitoring_user')) {
            return redirect()->route('login');
        }

        $project = $this->resolveMonitoringProject();
        $payload = $project ? $this->buildMonitoringPayload($project) : ['project' => null, 'latest' => [], 'series' => []];

        return view('dashboard', [
            'username' => session('monitoring_user'),
            'payload' => $payload,
        ]);
    }

    public function loginForm()
    {
        if (session()->has('monitoring_user')) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($credentials['username'] === 'user' && $credentials['password'] === 'user') {
            $request->session()->regenerate();
            $request->session()->put('monitoring_user', $credentials['username']);

            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'username' => 'Username atau password salah.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        $request->session()->forget('monitoring_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function api()
    {
        $project = $this->resolveMonitoringProject();

        if (! $project) {
            return response()->json(['message' => 'Project tidak ditemukan'], 404);
        }

        return response()->json($this->buildMonitoringPayload($project));
    }

    public function detail(string $sensor)
    {
        if (! session()->has('monitoring_user')) {
            return redirect()->route('login');
        }

        if (! in_array($sensor, ['phair', 'suhu', 'kekeruhan'], true)) {
            abort(404);
        }

        $project = $this->resolveMonitoringProject();
        $payload = $project ? $this->buildMonitoringPayload($project) : ['project' => null, 'latest' => [], 'series' => []];

        return view('dashboard_detail', [
            'username' => session('monitoring_user'),
            'sensor' => $sensor,
            'payload' => $payload,
        ]);
    }

    private function resolveMonitoringProject()
    {
        return DB::table('data_project')
            ->where('status', 'aktif')
            ->where('apikey', $this->defaultApiKey)
            ->first()
            ?? DB::table('data_project')->where('status', 'aktif')->orderByDesc('id_project')->first();
    }

    private function resolveMonitoringSensors(string $projectId)
    {
        return DB::table('data_sensor_project as sp')
            ->join('data_sensor as s', 's.id_sensor', '=', 'sp.id_sensor')
            ->where('sp.id_project', $projectId)
            ->where('sp.status', 'aktif')
            ->whereIn('sp.prefix', ['phair', 'suhu', 'kekeruhan'])
            ->select('sp.id_sensor_project', 'sp.prefix', 's.nama_sensor', 's.satuan')
            ->orderByRaw("FIELD(sp.prefix, 'phair', 'suhu', 'kekeruhan')")
            ->get();
    }

    private function buildMonitoringPayload(object $project): array
    {
        $sensors = $this->resolveMonitoringSensors($project->id_project);
        $series = [];
        $latest = [];

        foreach ($sensors as $sensor) {
            $rows = DB::table('data_input')
                ->where('id_sensor_project', $sensor->id_sensor_project)
                ->orderByDesc('id_input')
                ->limit(50)
                ->get(['waktu', 'nilai'])
                ->reverse()
                ->values();

            $labels = $rows->map(fn ($row) => $row->waktu)->values()->all();
            $values = $rows->map(fn ($row) => is_numeric($row->nilai) ? (float) $row->nilai : $row->nilai)->values()->all();
            $current = DB::table('data_input')
                ->where('id_sensor_project', $sensor->id_sensor_project)
                ->orderByDesc('id_input')
                ->first();

            $latest[$sensor->prefix] = [
                'nama_sensor' => $sensor->nama_sensor,
                'satuan' => $sensor->satuan,
                'nilai' => $current?->nilai ?? '-',
                'waktu' => $current?->waktu ?? '-',
            ];

            $series[$sensor->prefix] = [
                'id_sensor_project' => $sensor->id_sensor_project,
                'nama_sensor' => $sensor->nama_sensor,
                'satuan' => $sensor->satuan,
                'labels' => $labels,
                'values' => $values,
            ];
        }

        return [
            'project' => [
                'id_project' => $project->id_project,
                'nama_project' => $project->nama_project,
            ],
            'latest' => $latest,
            'series' => $series,
        ];
    }
}
