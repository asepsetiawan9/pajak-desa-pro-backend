<?php

namespace Tests\Feature;

use App\Models\DhkpRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'kades.malangbong',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['access_token', 'user']
            ]);
    }

    public function test_dhkp_summary_returns_kpi()
    {
        $response = $this->getJson('/api/v1/dhkp/summary?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['total_ketetapan', 'terbayar', 'sisa_piutang', 'persentase_realisasi']
            ]);
    }

    public function test_kasir_stts_payment_process_and_void_rollback()
    {
        $kolektor = User::where('username', 'kolektor.balok')->first();

        // 1. Process payment for NOP 32.05.010.001.001-0002.0 with items payload format
        $response = $this->actingAs($kolektor)->postJson('/api/v1/transactions', [
            'items' => [
                ['nop' => '32.05.010.001.001-0002.0']
            ],
            'tahun' => 2026,
            'metode' => 'TUNAI',
            'uangDibayar' => 200000,
            'kembalian' => 35000,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $transactionId = $response->json('data.id');

        // Check DHKP status is now LUNAS
        $dhkp = DhkpRow::where('nop', '32.05.010.001.001-0002.0')->first();
        $this->assertEquals('LUNAS', $dhkp->status_bayar);

        // 2. Void transaction via DELETE /transactions/{id}
        $voidResponse = $this->actingAs($kolektor)->deleteJson("/api/v1/transactions/{$transactionId}", [
            'reason' => 'Salah input nominal kasir',
        ]);

        $voidResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Check DHKP status is rolled back to BELUM_BAYAR
        $dhkpFresh = DhkpRow::where('nop', '32.05.010.001.001-0002.0')->first();
        $this->assertEquals('BELUM_BAYAR', $dhkpFresh->status_bayar);
    }

    public function test_custom_grouping_and_dhkp_deletion()
    {
        // Test Grouping 1 KK
        $groupResponse = $this->postJson('/api/v1/transactions/group', [
            'trxIds' => ['1', '2'],
            'groupName' => 'Keluarga Pak Dedi',
        ]);

        $groupResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['customGroupId']]);

        // Test DHKP row deletion
        $dhkp = DhkpRow::first();
        $deleteResponse = $this->deleteJson("/api/v1/dhkp/{$dhkp->id}");
        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNull(DhkpRow::find($dhkp->id));
    }

    public function test_21_column_report_generation()
    {
        $response = $this->getJson('/api/v1/reports/21-column?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['tahun', 'details', 'summary']
            ]);
    }

    public function test_dhkp_bulk_excel_import()
    {
        $response = $this->postJson('/api/v1/dhkp/import', [
            'rows' => [
                [
                    'nop' => '32.05.010.009.009-9999.0',
                    'nama_wp' => 'TEST IMPORT USER',
                    'dusun' => 'BALOK',
                    'blok' => 'Blok 01',
                    'ketetapan_pbb' => 150000,
                    'domisili' => 'DALAM_DESA',
                    'tahun' => 2026,
                ]
            ]
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.created', 1);

        $this->assertDatabaseHas('dhkp_rows', [
            'nop' => '32.05.010.009.009-9999.0',
            'nama_wp' => 'TEST IMPORT USER',
        ]);
    }

    public function test_settings_update_persists_identity_and_instansi()
    {
        $payload = [
            'settings' => [
                'namaDesa' => 'Desa Sukamaju Baru',
                'kecamatan' => 'Cisaat',
                'kabupaten' => 'Kabupaten Sukabumi',
                'kodeDesa' => '32.02.010.005',
                'namaKades' => 'Drs. H. Mulyana',
                'nipKades' => '19800101 200801 1 001',
            ]
        ];

        $response = $this->postJson('/api/v1/settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.namaDesa', 'Desa Sukamaju Baru')
            ->assertJsonPath('data.kabupaten', 'Kabupaten Sukabumi')
            ->assertJsonPath('data.kecamatan', 'Cisaat')
            ->assertJsonPath('data.nama_desa', 'Desa Sukamaju Baru')
            ->assertJsonPath('data.nama_instansi', 'Kabupaten Sukabumi');

        $getReponse = $this->getJson('/api/v1/settings');
        $getReponse->assertStatus(200)
            ->assertJsonPath('data.namaDesa', 'Desa Sukamaju Baru')
            ->assertJsonPath('data.kabupaten', 'Kabupaten Sukabumi')
            ->assertJsonPath('data.nama_instansi', 'Kabupaten Sukabumi');
    }
}
