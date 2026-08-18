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
            'username' => 'kades.barudua',
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
        $user = User::first();
        $response = $this->actingAs($user)->getJson('/api/v1/dhkp/summary?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['total_ketetapan', 'terbayar', 'sisa_piutang', 'persentase_realisasi', 'by_desa']
            ]);
    }

    public function test_super_admin_summary_returns_by_desa()
    {
        $superAdmin = User::where('role', 'SUPER_ADMIN_SYSTEM')->first();
        $response = $this->actingAs($superAdmin)->getJson('/api/v1/dhkp/summary?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_ketetapan',
                    'terbayar',
                    'by_desa' => [
                        '*' => ['desa_id', 'nama_desa', 'target', 'realisasi', 'persentase']
                    ]
                ]
            ]);
    }

    public function test_kasir_stts_payment_process_and_void_rollback()
    {
        $kolektor = User::where('username', 'kolektor.balok')->first();
        $dhkp = DhkpRow::where('status_bayar', 'BELUM_BAYAR')->first();

        // 1. Process payment for first NOP with items payload format
        $response = $this->actingAs($kolektor)->postJson('/api/v1/transactions', [
            'items' => [
                ['nop' => $dhkp->nop]
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
        $dhkpFresh = DhkpRow::find($dhkp->id);
        $this->assertEquals('LUNAS', $dhkpFresh->status_bayar);

        // 2. Void transaction via DELETE /transactions/{id}
        $voidResponse = $this->actingAs($kolektor)->deleteJson("/api/v1/transactions/{$transactionId}", [
            'reason' => 'Salah input nominal kasir',
        ]);

        $voidResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Check DHKP status is rolled back to BELUM_BAYAR
        $dhkpRollback = DhkpRow::find($dhkp->id);
        $this->assertEquals('BELUM_BAYAR', $dhkpRollback->status_bayar);
    }

    public function test_payment_with_flexible_nop_dhkp_id_and_tahun_fallback()
    {
        $kolektor = User::where('username', 'kolektor.balok')->first();
        $dhkp = DhkpRow::where('status_bayar', 'BELUM_BAYAR')->first();

        // Test payment using dhkp_id and nop without specifying root year or mismatched year
        $response = $this->actingAs($kolektor)->postJson('/api/v1/transactions/pay', [
            'dhkp_id' => $dhkp->id,
            'nop' => $dhkp->nop,
            'tahun' => 2024, // mismatched year test, should fallback and succeed
            'metode_pembayaran' => 'CASH',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $dhkpFresh = DhkpRow::find($dhkp->id);
        $this->assertEquals('LUNAS', $dhkpFresh->status_bayar);
    }

    public function test_custom_grouping_and_dhkp_deletion()
    {
        $user = User::first();

        // Test Grouping 1 KK
        $groupResponse = $this->actingAs($user)->postJson('/api/v1/transactions/group', [
            'trxIds' => ['1', '2'],
            'groupName' => 'Keluarga Pak Dedi',
        ]);

        $groupResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['customGroupId']]);

        // Test DHKP row deletion
        $dhkp = DhkpRow::first();
        $deleteResponse = $this->actingAs($user)->deleteJson("/api/v1/dhkp/{$dhkp->id}");
        $deleteResponse->assertStatus(200)->assertJsonPath('success', true);
        $this->assertNull(DhkpRow::find($dhkp->id));
    }

    public function test_21_column_report_generation()
    {
        $user = User::first();
        $response = $this->actingAs($user)->getJson('/api/v1/reports/21-column?tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['tahun', 'details', 'summary']
            ]);
    }

    public function test_dhkp_bulk_excel_import()
    {
        $user = User::first();
        $response = $this->actingAs($user)->postJson('/api/v1/dhkp/import', [
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

    public function test_dhkp_large_scale_batch_import()
    {
        $user = User::first();
        $testRows = [];
        for ($i = 1; $i <= 600; $i++) {
            $testRows[] = [
                'nop' => sprintf('32.05.010.009.009-%04d.0', $i),
                'nama_wp' => "WAJIB PAJAK MASSAL {$i}",
                'dusun' => 'BALOK',
                'blok' => 'Blok 01',
                'ketetapan_pbb' => 50000 + ($i * 100),
                'domisili' => ($i % 2 === 0) ? 'LUAR_DESA' : 'DALAM_DESA',
                'tahun' => 2026,
            ];
        }

        $response = $this->actingAs($user)->postJson('/api/v1/dhkp/import', [
            'rows' => $testRows,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 600);

        $this->assertDatabaseHas('dhkp_rows', [
            'nop' => '32.05.010.009.009-0001.0',
            'nama_wp' => 'WAJIB PAJAK MASSAL 1',
        ]);

        $this->assertDatabaseHas('dhkp_rows', [
            'nop' => '32.05.010.009.009-0600.0',
            'nama_wp' => 'WAJIB PAJAK MASSAL 600',
        ]);
    }

    public function test_settings_update_persists_identity_and_instansi()
    {
        $user = User::first();
        $payload = [
            'settings' => [
                'namaDesa' => 'Desa Barudua Baru',
                'kecamatan' => 'Cisaat',
                'kabupaten' => 'Kabupaten Sukabumi',
                'kodeDesa' => '32.02.010.005',
                'namaKades' => 'Drs. H. Mulyana',
                'nipKades' => '19800101 200801 1 001',
            ]
        ];

        $response = $this->actingAs($user)->postJson('/api/v1/settings', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.namaDesa', 'Desa Barudua Baru')
            ->assertJsonPath('data.kabupaten', 'Kabupaten Sukabumi')
            ->assertJsonPath('data.kecamatan', 'Cisaat')
            ->assertJsonPath('data.nama_desa', 'Desa Barudua Baru')
            ->assertJsonPath('data.nama_instansi', 'Kabupaten Sukabumi');

        $getReponse = $this->actingAs($user)->getJson('/api/v1/settings');
        $getReponse->assertStatus(200)
            ->assertJsonPath('data.namaDesa', 'Desa Barudua Baru')
            ->assertJsonPath('data.kabupaten', 'Kabupaten Sukabumi')
            ->assertJsonPath('data.nama_instansi', 'Kabupaten Sukabumi');
    }

    public function test_setoran_kecamatan_crud_and_verification_flow()
    {
        $superAdmin = User::where('role', 'SUPER_ADMIN_SYSTEM')->first() ?? User::first();
        $kades = User::where('role', 'KEPALA_DESA')->first();
        $adminDesa = User::where('role', 'SUPER_ADMIN')->whereNotNull('desa_id')->first() ?? $kades;

        // 1. Create Setoran Baru dari Desa ke Kas Kecamatan
        $storeResponse = $this->actingAs($adminDesa)->postJson('/api/v1/setoran-kecamatan', [
            'kategori' => 'SETOR_KECAMATAN',
            'tanggal_setor' => '2026-08-12',
            'tahun' => 2026,
            'nominal' => 5000000,
            'metode_setoran' => 'TRANSFER',
            'bank_tujuan' => 'Bank Jabar Banten (BJB)',
            'nomor_referensi' => 'REF882910',
            'penyetor_nama' => 'Asep Setiawan',
            'penyetor_jabatan' => 'Bendahara Desa',
            'penerima_kecamatan' => 'Kasi Goverment',
            'catatan_desa' => 'Setoran PBB Tahap I',
            'desa_id' => $adminDesa->desa_id ?? 1,
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nominal', 5000000)
            ->assertJsonPath('data.status', 'PENDING');

        $setoranId = $storeResponse->json('data.id');

        // 2. Create Pengeluaran Internal Desa (Status harus PENDING menunggu ACC Kades)
        $internalStoreResponse = $this->actingAs($adminDesa)->postJson('/api/v1/setoran-kecamatan', [
            'kategori' => 'KEGIATAN_DESA',
            'tanggal_setor' => '2026-08-12',
            'tahun' => 2026,
            'nominal' => 1500000,
            'metode_setoran' => 'TUNAI',
            'penyetor_nama' => 'Asep Setiawan',
            'penyetor_jabatan' => 'Bendahara Desa',
            'catatan_desa' => 'Biaya Konsumsi Posko Pajak',
            'desa_id' => $adminDesa->desa_id ?? 1,
        ]);

        $internalStoreResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nominal', 1500000)
            ->assertJsonPath('data.status', 'PENDING');

        $internalId = $internalStoreResponse->json('data.id');

        // 3. Summary Endpoint Check
        $summaryResponse = $this->actingAs($superAdmin)->getJson('/api/v1/setoran-kecamatan/summary?tahun=2026');
        $summaryResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // 4. Verify Setoran Kecamatan oleh Super Admin System (Kecamatan)
        $verifyResponse = $this->actingAs($superAdmin)->postJson("/api/v1/setoran-kecamatan/{$setoranId}/verify", [
            'status' => 'DITERIMA',
            'catatan_kecamatan' => 'Diterima di kas kecamatan.',
            'tanggal_diterima' => '2026-08-12',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'DITERIMA');

        // 5. ACC Pengeluaran Internal oleh Kepala Desa
        if ($kades) {
            $kadesAccResponse = $this->actingAs($kades)->postJson("/api/v1/setoran-kecamatan/{$internalId}/verify", [
                'status' => 'DITERIMA',
                'catatan_kecamatan' => 'Disetujui Kepala Desa untuk realisasi posko.',
                'tanggal_diterima' => '2026-08-12',
            ]);

            $kadesAccResponse->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.status', 'DITERIMA');
        }
    }

    public function test_audit_logs_endpoint_returns_activity_history()
    {
        $user = User::first();

        // Perform login to create audit log
        $this->postJson('/api/v1/auth/login', [
            'username' => 'kades.barudua',
            'password' => 'password123',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/audit-logs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'user_id', 'action', 'module', 'payload', 'created_at']
                ],
                'meta' => ['current_page', 'last_page', 'total']
            ]);
    }

    public function test_dusun_endpoint_returns_scoped_list_per_desa()
    {
        $adminDesa = User::where('username', 'admin.desa')->first();
        $superAdmin = User::where('role', 'SUPER_ADMIN_SYSTEM')->first();

        // 1. Admin Desa request dusuns for their own desa
        $resDesa = $this->actingAs($adminDesa)->getJson('/api/v1/dusun');
        $resDesa->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertIsArray($resDesa->json('data'));

        // 2. Super Admin request dusuns with desa_id filter
        $resSuper = $this->actingAs($superAdmin)->getJson("/api/v1/dusun?desa_id={$adminDesa->desa_id}");
        $resSuper->assertStatus(200)
            ->assertJsonPath('success', true);
        $this->assertIsArray($resSuper->json('data'));
    }

    public function test_master_dusun_crud_and_isolation()
    {
        $adminDesa = User::where('username', 'admin.desa')->first();
        $superAdmin = User::where('role', 'SUPER_ADMIN_SYSTEM')->first();

        // 1. Admin Desa creates new dusun
        $createRes = $this->actingAs($adminDesa)->postJson('/api/v1/dusuns', [
            'nama_dusun' => 'DUSUN MAWAR INDAH',
            'kode_dusun' => 'DSN-01',
            'rt_rw' => '001/002',
            'status_aktif' => true,
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.nama_dusun', 'DUSUN MAWAR INDAH')
            ->assertJsonPath('data.desa_id', $adminDesa->desa_id);

        $dusunId = $createRes->json('data.id');

        // 2. List master dusun contains the new dusun
        $listRes = $this->actingAs($adminDesa)->getJson('/api/v1/dusuns');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // 3. Dropdown format=names contains DUSUN MAWAR INDAH
        $namesRes = $this->actingAs($adminDesa)->getJson('/api/v1/dusuns?format=names');
        $namesRes->assertStatus(200);
        $this->assertContains('DUSUN MAWAR INDAH', $namesRes->json('data'));

        // 4. Update dusun
        $updateRes = $this->actingAs($adminDesa)->putJson("/api/v1/dusuns/{$dusunId}", [
            'nama_dusun' => 'DUSUN MAWAR RAYA',
            'rt_rw' => '001/003',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.nama_dusun', 'DUSUN MAWAR RAYA');

        // 5. Toggle Status
        $toggleRes = $this->actingAs($adminDesa)->patchJson("/api/v1/dusuns/{$dusunId}/toggle-status");
        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.status_aktif', false);

        // 6. Delete Dusun
        $delRes = $this->actingAs($adminDesa)->deleteJson("/api/v1/dusuns/{$dusunId}");
        $delRes->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_setoran_kecamatan_pending_reviews_endpoint()
    {
        $superAdmin = User::where('role', 'SUPER_ADMIN_SYSTEM')->first() ?? User::first();
        $kades = User::where('role', 'KEPALA_DESA')->first();
        $adminDesa = User::where('role', 'SUPER_ADMIN')->whereNotNull('desa_id')->first() ?? $kades;

        // 1. Hit pending reviews as Super Admin (Kecamatan)
        $resSuperAdmin = $this->actingAs($superAdmin)->getJson('/api/v1/setoran-kecamatan/pending-reviews');
        $resSuperAdmin->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role_context', 'KECAMATAN')
            ->assertJsonStructure([
                'data' => [
                    'role_context',
                    'review_label',
                    'need_action_count',
                    'counts' => [
                        'tambah_edit',
                        'permohonan_hapus',
                        'ditolak',
                        'total_pending',
                    ],
                    'items',
                ]
            ]);

        // 2. Hit pending reviews as Kepala Desa
        if ($kades) {
            $resKades = $this->actingAs($kades)->getJson('/api/v1/setoran-kecamatan/pending-reviews');
            $resKades->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.role_context', 'KEPALA_DESA');
        }

        // 3. Hit pending reviews as Admin Desa
        if ($adminDesa) {
            $resAdminDesa = $this->actingAs($adminDesa)->getJson('/api/v1/setoran-kecamatan/pending-reviews');
            $resAdminDesa->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.role_context', 'ADMIN_DESA');
        }
    }

    public function test_cannot_create_expense_when_cash_balance_is_zero_or_exceeded()
    {
        $adminDesa = User::where('username', 'admin.desa')->first() ?? User::where('role', 'SUPER_ADMIN')->whereNotNull('desa_id')->first();
        $desaId = $adminDesa->desa_id ?? 1;

        // 1. Uji saat saldo kas desa Rp 0 (karena DHKP LUNAS tahun 2030 adalah 0)
        $failZeroResponse = $this->actingAs($adminDesa)->postJson('/api/v1/setoran-kecamatan', [
            'kategori' => 'SETOR_KECAMATAN',
            'tanggal_setor' => '2030-01-01',
            'tahun' => 2030, // tahun tanpa realisasi lunas
            'nominal' => 1000000,
            'metode_setoran' => 'TRANSFER',
            'penyetor_nama' => 'Asep',
            'desa_id' => $desaId,
        ]);

        $failZeroResponse->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Saldo kas PBB-P2 desa saat ini Rp 0. Tidak dapat membuat pengeluaran baru sampai ada penerimaan pembayaran PBB-P2 yang masuk.');

        // 2. Uji saat nominal melebihi sisa saldo kas (misal minta 999.000.000)
        $failExceedResponse = $this->actingAs($adminDesa)->postJson('/api/v1/setoran-kecamatan', [
            'kategori' => 'KEGIATAN_DESA',
            'tanggal_setor' => '2026-08-12',
            'tahun' => 2026,
            'nominal' => 999000000000, // Nominal sangat besar melebihi kas
            'metode_setoran' => 'TUNAI',
            'penyetor_nama' => 'Asep',
            'desa_id' => $desaId,
        ]);

        $failExceedResponse->assertStatus(400)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('melebihi sisa saldo kas PBB-P2 yang tersedia', $failExceedResponse->json('message'));
    }
}
