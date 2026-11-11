<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('data_project')) {
            Schema::create('data_project', function ($table) {
                $table->string('id_project')->primary();
                $table->string('nama_project');
                $table->string('apikey')->nullable();
                $table->string('status')->default('aktif');
            });
        }

        if (! Schema::hasTable('data_sensor')) {
            Schema::create('data_sensor', function ($table) {
                $table->string('id_sensor')->primary();
                $table->string('nama_sensor');
                $table->string('satuan')->nullable();
            });
        }

        if (! Schema::hasTable('data_sensor_project')) {
            Schema::create('data_sensor_project', function ($table) {
                $table->string('id_sensor_project')->primary();
                $table->string('id_project');
                $table->string('id_sensor');
                $table->string('prefix');
                $table->string('status')->default('aktif');
            });
        }

        if (! Schema::hasTable('data_input')) {
            Schema::create('data_input', function ($table) {
                $table->string('id_input')->primary();
                $table->string('id_project');
                $table->string('id_sensor_project');
                $table->dateTime('waktu');
                $table->string('nilai')->nullable();
            });
        }

        config([
            'monitoring.api_key' => 'test-api-key',
            'monitoring.dashboard.username' => 'tester',
            'monitoring.dashboard.password' => 'secret123',
            'monitoring.telegram.bot_token' => null,
            'monitoring.telegram.chat_id' => null,
        ]);

        DB::table('data_project')->insert([
            'id_project' => 'PRJ1',
            'nama_project' => 'Monitoring PH Air',
            'apikey' => 'test-api-key',
            'status' => 'aktif',
        ]);

        $sensors = [
            ['id_sensor' => 'S1', 'nama_sensor' => 'PH Air', 'satuan' => 'pH', 'prefix' => 'phair', 'id_sensor_project' => 'SP1'],
            ['id_sensor' => 'S2', 'nama_sensor' => 'Suhu Air', 'satuan' => '°C', 'prefix' => 'suhu', 'id_sensor_project' => 'SP2'],
            ['id_sensor' => 'S3', 'nama_sensor' => 'Kekeruhan Air', 'satuan' => '%', 'prefix' => 'kekeruhan', 'id_sensor_project' => 'SP3'],
        ];

        foreach ($sensors as $sensor) {
            DB::table('data_sensor')->insert([
                'id_sensor' => $sensor['id_sensor'],
                'nama_sensor' => $sensor['nama_sensor'],
                'satuan' => $sensor['satuan'],
            ]);

            DB::table('data_sensor_project')->insert([
                'id_sensor_project' => $sensor['id_sensor_project'],
                'id_project' => 'PRJ1',
                'id_sensor' => $sensor['id_sensor'],
                'prefix' => $sensor['prefix'],
                'status' => 'aktif',
            ]);
        }
    }

    public function test_ingest_menyimpan_data_sensor_dengan_apikey_valid(): void
    {
        $response = $this->post('/server.php', [
            'apikey' => 'test-api-key',
            'phair' => '7.1',
            'suhu' => '27.5',
        ]);

        $response->assertOk()->assertJsonPath('out_1', '0');
        $this->assertDatabaseCount('data_input', 2);
    }

    public function test_ingest_menolak_apikey_tidak_valid(): void
    {
        $response = $this->post('/server.php', [
            'apikey' => 'salah',
            'phair' => '7.1',
        ]);

        $response->assertOk()->assertSee('Gagal');
        $this->assertDatabaseCount('data_input', 0);
    }

    public function test_esp32_http_get_menyimpan_ketiga_data_sensor(): void
    {
        $response = $this->get('/server.php?apikey=test-api-key&suhu=27.50&kekeruhan=100.00&phair=7.44');

        $response->assertOk()->assertJsonPath('out_1', '0');

        $this->assertDatabaseCount('data_input', 3);
        $this->assertDatabaseHas('data_input', ['id_sensor_project' => 'SP1', 'nilai' => '7.44']);
        $this->assertDatabaseHas('data_input', ['id_sensor_project' => 'SP2', 'nilai' => '27.50']);
        $this->assertDatabaseHas('data_input', ['id_sensor_project' => 'SP3', 'nilai' => '100.00']);
    }

    public function test_esp32_payload_tanpa_p_h_tidak_menyimpan_p_h(): void
    {
        $response = $this->get('/server.php?apikey=test-api-key&suhu=27.50&kekeruhan=100.00');

        $response->assertOk()->assertJsonPath('out_1', '0');

        $this->assertDatabaseCount('data_input', 2);
        $this->assertDatabaseMissing('data_input', ['id_sensor_project' => 'SP1']);
        $this->assertDatabaseHas('data_input', ['id_sensor_project' => 'SP2', 'nilai' => '27.50']);
        $this->assertDatabaseHas('data_input', ['id_sensor_project' => 'SP3', 'nilai' => '100.00']);
    }

    public function test_dashboard_wajib_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_login_dengan_kredensial_benar_mengarah_ke_dashboard(): void
    {
        $this->post('/login', ['username' => 'tester', 'password' => 'secret123'])
            ->assertRedirect(route('dashboard'));

        $this->withSession(['monitoring_user' => 'tester'])
            ->get('/')
            ->assertOk();
    }

    public function test_login_dengan_kredensial_salah_ditolak(): void
    {
        $this->post('/login', ['username' => 'tester', 'password' => 'wrong'])
            ->assertSessionHasErrors('username');
    }
}
