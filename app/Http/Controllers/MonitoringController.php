<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MonitoringController extends Controller
{
    /**
     * Menerima data sensor dari perangkat IoT (ESP32).
     *
     * Alur: validasi apikey -> cari project aktif -> simpan setiap nilai
     * sensor yang dikirim ke tabel data_input -> picu alert Telegram bila
     * nilai di luar ambang batas. Selalu membalas JSON out_1..out_10 '0'
     * sebagai format respons yang diharapkan firmware.
     */
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

            $nilai = (string) $request->input($sensor->prefix);

            DB::table('data_input')->insert([
                'id_input' => 'INP'.now()->format('YmdHisv').random_int(100, 999),
                'id_project' => $project->id_project,
                'id_sensor_project' => $sensor->id_sensor_project,
                'waktu' => now()->format('Y-m-d H:i:s'),
                'nilai' => $nilai,
            ]);

            $this->dispatchThresholdAlert($sensor, $nilai);
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

    /**
     * Menampilkan halaman dashboard utama.
     *
     * Wajib login (session monitoring_user). Menyiapkan payload berisi
     * nilai terakhir dan deret riwayat semua sensor untuk dirender view.
     */
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

    /**
     * Menampilkan form login.
     *
     * Jika pengguna sudah login, langsung dialihkan ke dashboard.
     */
    public function loginForm()
    {
        if (session()->has('monitoring_user')) {
            return redirect()->route('dashboard');
        }

        return view('login');
    }

    /**
     * Memproses percobaan login.
     *
     * Memvalidasi input, mencocokkan dengan kredensial dari config
     * (env MONITORING_DASHBOARD_USERNAME/PASSWORD). Berhasil: regenerasi
     * session dan simpan penanda login. Gagal: kembali dengan error.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($credentials['username'] === config('monitoring.dashboard.username')
            && $credentials['password'] === config('monitoring.dashboard.password')) {
            $request->session()->regenerate();
            $request->session()->put('monitoring_user', $credentials['username']);

            return redirect()->route('dashboard');
        }

        return back()->withErrors([
            'username' => 'Username atau password salah.',
        ])->onlyInput('username');
    }

    /**
     * Keluar dari dashboard.
     *
     * Menghapus penanda login, meng-invalidate session, dan regenerasi
     * token CSRF untuk keamanan.
     */
    public function logout(Request $request)
    {
        $request->session()->forget('monitoring_user');
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Endpoint JSON data monitoring (/api/monitoring).
     *
     * Mengembalikan payload yang sama dengan dashboard (nilai terbaru +
     * deret riwayat) dalam format JSON, tanpa perlu login.
     */
    public function api()
    {
        $project = $this->resolveMonitoringProject();

        if (! $project) {
            return response()->json(['message' => 'Project tidak ditemukan'], 404);
        }

        return response()->json($this->buildMonitoringPayload($project));
    }

    /**
     * Menampilkan halaman detail per sensor (phair/suhu/kekeruhan).
     *
     * Wajib login. Prefix sensor divalidasi whitelist; selain itu 404.
     * Payload sama dengan dashboard, view difokuskan ke satu sensor.
     */
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

    /**
     * Mencari project monitoring aktif yang dipakai dashboard/API.
     *
     * Prioritas: project dengan apikey sesuai MONITORING_API_KEY.
     * Fallback: project aktif dengan id_project terbaru.
     */
    private function resolveMonitoringProject()
    {
        return DB::table('data_project')
            ->where('status', 'aktif')
            ->where('apikey', config('monitoring.api_key'))
            ->first()
            ?? DB::table('data_project')->where('status', 'aktif')->orderByDesc('id_project')->first();
    }

    /**
     * Mengirim alert Telegram bila nilai sensor di luar ambang batas.
     *
     * Lewat (no-op) bila Telegram belum dikonfigurasi, threshold sensor
     * tidak terdaftar, atau nilai bukan angka. Bila nilai di luar rentang
     * min-max, kirim pesan ke chat; kegagalan kirim hanya dicatat di log.
     */
    private function dispatchThresholdAlert(object $sensor, string $nilai): void
    {
        $botToken = config('monitoring.telegram.bot_token');
        $chatId = config('monitoring.telegram.chat_id');
        $threshold = config("monitoring.thresholds.{$sensor->prefix}");

        if (blank($botToken) || blank($chatId) || blank($threshold) || ! is_numeric($nilai)) {
            return;
        }

        $value = (float) $nilai;

        if ($value >= $threshold['min'] && $value <= $threshold['max']) {
            return;
        }

        $response = Http::asForm()->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
            'chat_id' => $chatId,
            'text' => sprintf(
                '[Alert] %s: %s %s di luar ambang batas (%s - %s)',
                $sensor->nama_sensor,
                $nilai,
                $sensor->satuan,
                $threshold['min'],
                $threshold['max']
            ),
        ]);

        if (! $response->successful()) {
            Log::warning('Gagal mengirim alert Telegram.', ['response' => $response->json()]);
        }
    }

    /**
     * Mengambil daftar sensor aktif milik sebuah project.
     *
     * Hanya prefix phair, suhu, kekeruhan. Hasil diurutkan sesuai urutan
     * prefix tersebut (bukan urutan database) agar tampilan konsisten.
     */
    private function resolveMonitoringSensors(string $projectId)
    {
        $sensors = DB::table('data_sensor_project as sp')
            ->join('data_sensor as s', 's.id_sensor', '=', 'sp.id_sensor')
            ->where('sp.id_project', $projectId)
            ->where('sp.status', 'aktif')
            ->whereIn('sp.prefix', ['phair', 'suhu', 'kekeruhan'])
            ->select('sp.id_sensor_project', 'sp.prefix', 's.nama_sensor', 's.satuan')
            ->get();

        return $sensors->sortBy(fn ($sensor) => array_search($sensor->prefix, ['phair', 'suhu', 'kekeruhan'], true))->values();
    }

    /**
     * Menyusun payload lengkap untuk dashboard/detail/API.
     *
     * Hanya memakai pembacaan hari ini (zona waktu aplikasi) supaya grafik
     * tidak mencampur data historis lama. Untuk setiap sensor: ambil 50 data
     * terakhir hari ini (dibalik menjadi urutan waktu naik) sebagai deret
     * grafik, plus satu nilai terbaru sebagai kondisi saat ini. Bila belum
     * ada data hari ini, nilai terbaru diisi '-' dan deret grafik kosong.
     * Hasilnya struktur project + latest + series.
     */
    private function buildMonitoringPayload(object $project): array
    {
        $sensors = $this->resolveMonitoringSensors($project->id_project);
        $series = [];
        $latest = [];
        [$startOfDay, $endOfDay] = $this->todayRange();

        foreach ($sensors as $sensor) {
            $rows = DB::table('data_input')
                ->where('id_sensor_project', $sensor->id_sensor_project)
                ->where('waktu', '>=', $startOfDay)
                ->where('waktu', '<', $endOfDay)
                ->orderByDesc('id_input')
                ->limit(50)
                ->get(['waktu', 'nilai'])
                ->reverse()
                ->values();

            $labels = $rows->map(fn ($row) => $this->formatReadableDateTime($row->waktu))->values()->all();
            $values = $rows->map(fn ($row) => is_numeric($row->nilai) ? (float) $row->nilai : $row->nilai)->values()->all();
            $current = DB::table('data_input')
                ->where('id_sensor_project', $sensor->id_sensor_project)
                ->where('waktu', '>=', $startOfDay)
                ->where('waktu', '<', $endOfDay)
                ->orderByDesc('id_input')
                ->first();

            $latest[$sensor->prefix] = [
                'nama_sensor' => $sensor->nama_sensor,
                'satuan' => $sensor->satuan,
                'nilai' => $current?->nilai ?? '-',
                'waktu' => $this->formatReadableDateTime($current?->waktu),
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

    /**
     * Rentang satu hari penuh pada zona waktu aplikasi.
     *
     * Dipakai sebagai batas bawah (inklusif) dan batas atas (eksklusif)
     * query data_input sehingga hanya pembacaan hari ini yang terambil.
     *
     * @return array{0: string, 1: string}
     */
    private function todayRange(): array
    {
        $start = Carbon::today();

        return [
            $start->format('Y-m-d H:i:s'),
            $start->copy()->addDay()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Memformat tanggal-waktu ke teks Indonesia yang mudah dibaca.
     *
     * Contoh: "Senin, 03 Maret 2026 jam 14:05". Nilai kosong -> '-'.
     */
    private function formatReadableDateTime(?string $dateTime): string
    {
        if (blank($dateTime)) {
            return '-';
        }

        $date = Carbon::parse($dateTime);
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return sprintf(
            '%s, %02d %s %s jam %s:%s',
            $days[$date->dayOfWeek],
            $date->day,
            $months[$date->month],
            $date->year,
            $date->format('H'),
            $date->format('i')
        );
    }
}
