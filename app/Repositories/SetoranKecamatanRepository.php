<?php

namespace App\Repositories;

use App\Models\Desa;
use App\Models\DhkpRow;
use App\Models\DusunTarget;
use App\Models\SetoranKecamatan;
use App\Scopes\TenantScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SetoranKecamatanRepository
{
    /**
     * Check if authenticated user is Super Admin System (Kecamatan / Pusat)
     */
    private function isSuperAdmin(): bool
    {
        $user = auth()->user();
        return $user && (
            $user->role === 'SUPER_ADMIN_SYSTEM' ||
            is_null($user->desa_id)
        );
    }

    /**
     * Check if authenticated user can view internal village expenses (Pengeluaran Internal Desa)
     * Aturan Baku: Super Admin System (Kecamatan) maupun Super Admin (Admin Desa) DILARANG melihat pengeluaran kas internal desa.
     * Hanya Kepala Desa & Bendahara yang berhak mengakses & melihat pengeluaran kas internal desa.
     */
    private function canViewInternalExpenses(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->desa_id) {
            return false;
        }

        return in_array($user->role, ['KEPALA_DESA', 'BENDAHARA']);
    }

    /**
     * Get filtered setoran kecamatans with pagination
     */
    public function getFilteredData(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $isSuperAdmin = $this->isSuperAdmin();
        $canViewInternal = $this->canViewInternalExpenses();

        $query = $isSuperAdmin
            ? SetoranKecamatan::withoutGlobalScope(TenantScope::class)
                ->with([
                    'desa:id,nama_desa,kode_desa,nama_kecamatan',
                    'penerimaUser:id,name',
                    'creator:id,name'
                ])
            : SetoranKecamatan::query()
                ->with([
                    'desa:id,nama_desa,kode_desa,nama_kecamatan',
                    'penerimaUser:id,name',
                    'creator:id,name'
                ]);

        // Filter desa_id if Super Admin specifies
        if ($isSuperAdmin && !empty($filters['desa_id']) && $filters['desa_id'] !== 'ALL' && $filters['desa_id'] !== 'all') {
            $query->where('desa_id', $filters['desa_id']);
        }

        // Filter status
        if (!empty($filters['status']) && $filters['status'] !== 'ALL' && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        // Filter kategori:
        // Super Admin (Kecamatan) maupun Super Admin (Admin Desa) DILARANG melihat pengeluaran internal desa.
        // HANYA Kepala Desa & Bendahara yang berhak melihat pengeluaran kas internal desa.
        if (!$canViewInternal) {
            $query->where('kategori', 'SETOR_KECAMATAN');
        } else {
            // Kepala Desa / Bendahara: bisa memfilter sesuai kebutuhan internal desa
            if (!empty($filters['kategori']) && $filters['kategori'] !== 'ALL' && $filters['kategori'] !== 'all') {
                if ($filters['kategori'] === 'INTERNAL') {
                    $query->where('kategori', '!=', 'SETOR_KECAMATAN');
                } else {
                    $query->where('kategori', $filters['kategori']);
                }
            }
        }

        // Filter tahun
        if (!empty($filters['tahun'])) {
            $query->where('tahun', $filters['tahun']);
        }

        // Filter search (nomor_bukti, penyetor_nama, penerima_kecamatan, bank_tujuan)
        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nomor_bukti', 'like', $search)
                  ->orWhere('penyetor_nama', 'like', $search)
                  ->orWhere('penerima_kecamatan', 'like', $search)
                  ->orWhere('bank_tujuan', 'like', $search)
                  ->orWhere('nomor_referensi', 'like', $search);
            });
        }

        return $query->orderBy('tanggal_setor', 'desc')
                    ->orderBy('id', 'desc')
                    ->paginate($perPage);
    }

    /**
     * Get summary KPI & comparison per village
     */
    public function getKpiSummary(array $filters): array
    {
        $isSuperAdmin = $this->isSuperAdmin();
        $canViewInternal = $this->canViewInternalExpenses();
        $tahun = $filters['tahun'] ?? 2026;
        $targetDesaId = (!empty($filters['desa_id']) && $filters['desa_id'] !== 'ALL' && $filters['desa_id'] !== 'all')
            ? (int) $filters['desa_id']
            : null;

        // Base Setoran Query:
        // Jika user tidak berhak melihat internal (Super Admin System maupun Super Admin Desa):
        // HANYA hitung SETOR_KECAMATAN. Pengeluaran internal desa terisolasi 100% untuk Kades & Bendahara.
        $setoranQuery = (!$canViewInternal)
            ? ($isSuperAdmin
                ? SetoranKecamatan::withoutGlobalScope(TenantScope::class)->where('tahun', $tahun)->where('kategori', 'SETOR_KECAMATAN')
                : SetoranKecamatan::query()->where('tahun', $tahun)->where('kategori', 'SETOR_KECAMATAN'))
            : SetoranKecamatan::query()->where('tahun', $tahun);

        if ($isSuperAdmin && $targetDesaId) {
            $setoranQuery->where('desa_id', $targetDesaId);
        }

        $allSetoran = $setoranQuery->get();

        $totalDisetorkan = (float) $allSetoran->sum('nominal');
        $totalDiterima = (float) $allSetoran->where('status', 'DITERIMA')->where('kategori', 'SETOR_KECAMATAN')->sum('nominal');
        // Pengeluaran internal disembunyikan sepenuhnya dari Super Admin (System maupun Desa) (bernilai 0)
        $totalPengeluaranInternal = $canViewInternal
            ? (float) $allSetoran->where('status', 'DITERIMA')->where('kategori', '!=', 'SETOR_KECAMATAN')->sum('nominal')
            : 0.0;
        $totalPengeluaranDesa = $totalDiterima + $totalPengeluaranInternal;

        $totalPending = (float) $allSetoran->where('status', 'PENDING')->sum('nominal');
        $totalDitolak = (float) $allSetoran->where('status', 'DITOLAK')->sum('nominal');

        $countTotal = $allSetoran->count();
        $countDiterima = $allSetoran->where('status', 'DITERIMA')->count();
        $countPending = $allSetoran->where('status', 'PENDING')->count();
        $countDitolak = $allSetoran->where('status', 'DITOLAK')->count();

        // Total Realisasi Penerimaan PBB-P2 dari DHKP LUNAS
        $dhkpQuery = $isSuperAdmin
            ? DhkpRow::withoutGlobalScope(TenantScope::class)->where('tahun', $tahun)->where('status_bayar', 'LUNAS')
            : DhkpRow::query()->where('tahun', $tahun)->where('status_bayar', 'LUNAS');

        if ($isSuperAdmin && $targetDesaId) {
            $dhkpQuery->where('desa_id', $targetDesaId);
        }

        $totalRealisasiDesa = (float) $dhkpQuery->sum('ketetapan_pbb');

        // Sisa Kas:
        // Untuk Super Admin (Kecamatan maupun Desa): Menunjukkan Sisa Belum Disetor ke Kecamatan (Realisasi PBB - Total Setoran Diterima)
        // Untuk Pejabat Internal (Kades/Bendahara): Menunjukkan Sisa Saldo Kas Desa Riil (Realisasi PBB - Total Pengeluaran Desa)
        $sisaKasDesa = $canViewInternal
            ? max(0, $totalRealisasiDesa - $totalPengeluaranDesa)
            : max(0, $totalRealisasiDesa - $totalDiterima);

        // Rekapitulasi per Desa
        $rekapPerDesa = [];
        $desas = $isSuperAdmin
            ? ($targetDesaId ? Desa::where('id', $targetDesaId)->get() : Desa::where('status_aktif', true)->get())
            : (auth()->user() && auth()->user()->desa_id ? Desa::where('id', auth()->user()->desa_id)->get() : Desa::where('status_aktif', true)->get());

        foreach ($desas as $desa) {
            // Priority 1: Target PBB from DusunTarget table for this desa & year
            $desaTargetSum = (float) DusunTarget::withoutGlobalScope(TenantScope::class)
                ->where('desa_id', $desa->id)
                ->where('tahun', $tahun)
                ->sum('target_pbb');

            // Priority 2: Total ketetapan DHKP for this desa & year
            $desaDhkpTotal = (float) DhkpRow::withoutGlobalScope(TenantScope::class)
                ->where('desa_id', $desa->id)
                ->where('tahun', $tahun)
                ->sum('ketetapan_pbb');

            // Target PBB uses stored DusunTarget if present (>0), otherwise total DHKP ketetapan
            $targetPbb = $desaTargetSum > 0 ? $desaTargetSum : $desaDhkpTotal;

            $desaDhkpLunas = (float) DhkpRow::withoutGlobalScope(TenantScope::class)
                ->where('desa_id', $desa->id)
                ->where('tahun', $tahun)
                ->where('status_bayar', 'LUNAS')
                ->sum('ketetapan_pbb');

            // Untuk Super Admin atau pihak non-internal, transaksi yang diperiksa hanya SETOR_KECAMATAN
            $desaSetoranQuery = SetoranKecamatan::withoutGlobalScope(TenantScope::class)
                ->where('desa_id', $desa->id)
                ->where('tahun', $tahun);

            if (!$canViewInternal) {
                $desaSetoranQuery->where('kategori', 'SETOR_KECAMATAN');
            }

            $desaSetoranAll = $desaSetoranQuery->get();

            $disetorDiterima = (float) $desaSetoranAll->where('status', 'DITERIMA')->where('kategori', 'SETOR_KECAMATAN')->sum('nominal');
            $pengeluaranInternalDesa = $canViewInternal
                ? (float) $desaSetoranAll->where('status', 'DITERIMA')->where('kategori', '!=', 'SETOR_KECAMATAN')->sum('nominal')
                : 0.0;
            $totalPengeluaranDesaLocal = $disetorDiterima + $pengeluaranInternalDesa;
            $disetorPending = (float) $desaSetoranAll->where('status', 'PENDING')->sum('nominal');

            // Persentase Capaian Setoran Kecamatan = (Disetor ke Kecamatan Verified / Target PBB) * 100
            $persenDisetor = $targetPbb > 0 ? round(($disetorDiterima / $targetPbb) * 100, 2) : 0;
            $lastSetoran = $desaSetoranAll->sortByDesc('tanggal_setor')->first();

            $rekapPerDesa[] = [
                'desa_id' => $desa->id,
                'nama_desa' => $desa->nama_desa,
                'kode_desa' => $desa->kode_desa,
                'target_pbb' => $targetPbb,
                'realisasi_pbb' => $desaDhkpLunas,
                'total_disetor_diterima' => $disetorDiterima,
                'total_pengeluaran_internal' => $pengeluaranInternalDesa,
                'total_pengeluaran_desa' => $totalPengeluaranDesaLocal,
                'total_disetor_pending' => $disetorPending,
                'sisa_kas_desa' => $isSuperAdmin ? max(0, $desaDhkpLunas - $disetorDiterima) : max(0, $desaDhkpLunas - $totalPengeluaranDesaLocal),
                'persentase_disetor' => $persenDisetor,
                'tanggal_setor_terakhir' => $lastSetoran ? $lastSetoran->tanggal_setor->format('Y-m-d') : null,
                'status_terakhir' => $lastSetoran ? $lastSetoran->status : null,
            ];
        }

        return [
            'total_disetorkan' => $totalDisetorkan,
            'total_diterima' => $totalDiterima,
            'total_pengeluaran_internal' => $totalPengeluaranInternal,
            'total_pengeluaran_desa' => $totalPengeluaranDesa,
            'total_pending' => $totalPending,
            'total_ditolak' => $totalDitolak,
            'total_realisasi_desa' => $totalRealisasiDesa,
            'sisa_kas_desa' => $sisaKasDesa,
            'counts' => [
                'total' => $countTotal,
                'diterima' => $countDiterima,
                'pending' => $countPending,
                'ditolak' => $countDitolak,
            ],
            'rekap_per_desa' => $rekapPerDesa,
        ];
    }

    /**
     * Find setoran by ID
     */
    public function findById(int $id): ?SetoranKecamatan
    {
        $isSuperAdmin = $this->isSuperAdmin();
        $canViewInternal = $this->canViewInternalExpenses();

        $query = $isSuperAdmin
            ? SetoranKecamatan::withoutGlobalScope(TenantScope::class)
            : SetoranKecamatan::query();

        // Super Admin (Kecamatan maupun Desa) DILARANG melihat data pengeluaran internal desa
        if (!$canViewInternal) {
            $query->where('kategori', 'SETOR_KECAMATAN');
        }

        return $query->with(['desa:id,nama_desa,kode_desa,nama_kecamatan', 'penerimaUser:id,name', 'creator:id,name'])
            ->find($id);
    }

    /**
     * Get remaining available village cash balance
     */
    public function getSisaKasDesa(int $desaId, int $tahun): float
    {
        $dhkpLunas = (float) DhkpRow::withoutGlobalScope(TenantScope::class)
            ->where('desa_id', $desaId)
            ->where('tahun', $tahun)
            ->where('status_bayar', 'LUNAS')
            ->sum('ketetapan_pbb');

        $totalPengeluaranDiterima = (float) SetoranKecamatan::withoutGlobalScope(TenantScope::class)
            ->where('desa_id', $desaId)
            ->where('tahun', $tahun)
            ->where('status', 'DITERIMA')
            ->sum('nominal');

        return max(0.0, $dhkpLunas - $totalPengeluaranDiterima);
    }

    /**
     * Create new setoran record
     */
    public function create(array $data): SetoranKecamatan
    {
        if (empty($data['created_by']) && auth()->check()) {
            $data['created_by'] = auth()->id();
        }

        return SetoranKecamatan::create($data);
    }

    /**
     * Update setoran record
     */
    public function update(SetoranKecamatan $setoran, array $data): bool
    {
        return $setoran->update($data);
    }

    /**
     * Verify setoran record status from kecamatan
     */
    public function verify(SetoranKecamatan $setoran, string $status, ?string $catatanKecamatan, ?string $tanggalDiterima): bool
    {
        $updateData = [
            'status' => $status,
            'catatan_kecamatan' => $catatanKecamatan,
            'tanggal_diterima' => $tanggalDiterima ?? now(),
            'penerima_user_id' => auth()->id(),
        ];

        return $setoran->update($updateData);
    }

    /**
     * Delete setoran record
     */
    public function delete(SetoranKecamatan $setoran): bool
    {
        return $setoran->delete();
    }

    /**
     * Get pending reviews data & counts scoped by user role & village
     */
    public function getPendingReviewsData(?int $desaId = null): array
    {
        $user = auth()->user();
        $isSuperAdmin = $this->isSuperAdmin();
        $role = $user?->role ?? 'SUPER_ADMIN_SYSTEM';
        $userDesaId = $user?->desa_id;

        // Base Query
        $baseQuery = $isSuperAdmin
            ? SetoranKecamatan::withoutGlobalScope(TenantScope::class)->with(['desa:id,nama_desa,kode_desa', 'creator:id,name'])
            : SetoranKecamatan::query()->with(['desa:id,nama_desa,kode_desa', 'creator:id,name']);

        if ($isSuperAdmin && $desaId) {
            $baseQuery->where('desa_id', $desaId);
        }

        // Logic per role
        if ($isSuperAdmin) {
            // Admin Kecamatan HANYA memverifikasi transaksi SETOR_KECAMATAN
            $pendingQuery = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->whereIn('status', ['PENDING', 'PENDING_HAPUS']);
            $tambahEditCount = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->where('status', 'PENDING')->count();
            $hapusCount = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->where('status', 'PENDING_HAPUS')->count();
            $ditolakCount = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->where('status', 'DITOLAK')->count();
            $needActionCount = $tambahEditCount + $hapusCount;
            $roleContext = 'KECAMATAN';
            $reviewLabel = 'Verifikasi Setoran Kecamatan';
        } elseif ($role === 'KEPALA_DESA') {
            // Kepala Desa HANYA menyetujui pengeluaran internal desa
            $pendingQuery = (clone $baseQuery)->where('kategori', '!=', 'SETOR_KECAMATAN')->whereIn('status', ['PENDING', 'PENDING_HAPUS']);
            $tambahEditCount = (clone $baseQuery)->where('kategori', '!=', 'SETOR_KECAMATAN')->where('status', 'PENDING')->count();
            $hapusCount = (clone $baseQuery)->where('kategori', '!=', 'SETOR_KECAMATAN')->where('status', 'PENDING_HAPUS')->count();
            $ditolakCount = (clone $baseQuery)->where('kategori', '!=', 'SETOR_KECAMATAN')->where('status', 'DITOLAK')->count();
            $needActionCount = $tambahEditCount + $hapusCount;
            $roleContext = 'KEPALA_DESA';
            $reviewLabel = 'Persetujuan Pengeluaran Internal Desa';
        } elseif ($role === 'BENDAHARA') {
            // Bendahara: memantau seluruh pengeluaran desa (Setor Kecamatan & Internal) yang sedang diproses atau ditolak
            $pendingQuery = (clone $baseQuery)->whereIn('status', ['PENDING', 'PENDING_HAPUS', 'DITOLAK']);
            $tambahEditCount = (clone $baseQuery)->where('status', 'PENDING')->count();
            $hapusCount = (clone $baseQuery)->where('status', 'PENDING_HAPUS')->count();
            $ditolakCount = (clone $baseQuery)->where('status', 'DITOLAK')->count();
            $needActionCount = $tambahEditCount + $hapusCount + $ditolakCount;
            $roleContext = 'BENDAHARA';
            $reviewLabel = 'Status Pengajuan Pengeluaran Desa';
        } else {
            // Super Admin (Admin Desa) / role lainnya: HANYA memantau transaksi SETOR_KECAMATAN
            $pendingQuery = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->whereIn('status', ['PENDING', 'PENDING_HAPUS', 'DITOLAK']);
            $tambahEditCount = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->where('status', 'PENDING')->count();
            $hapusCount = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->where('status', 'PENDING_HAPUS')->count();
            $ditolakCount = (clone $baseQuery)->where('kategori', 'SETOR_KECAMATAN')->where('status', 'DITOLAK')->count();
            $needActionCount = $tambahEditCount + $hapusCount + $ditolakCount;
            $roleContext = 'ADMIN_DESA';
            $reviewLabel = 'Status Pengajuan Setoran ke Kecamatan';
        }

        $latestItems = $pendingQuery->orderBy('updated_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return [
            'role_context' => $roleContext,
            'review_label' => $reviewLabel,
            'need_action_count' => $needActionCount,
            'counts' => [
                'tambah_edit' => $tambahEditCount,
                'permohonan_hapus' => $hapusCount,
                'ditolak' => $ditolakCount,
                'total_pending' => $tambahEditCount + $hapusCount,
            ],
            'items' => $latestItems,
        ];
    }
}
