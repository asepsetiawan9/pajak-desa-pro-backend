<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Desa;
use App\Models\SetoranKecamatan;
use App\Repositories\SetoranKecamatanRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SetoranKecamatanService
{
    public function __construct(
        protected SetoranKecamatanRepository $repository
    ) {}

    public function getFilteredSetoran(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->getFilteredData($filters, $perPage);
    }

    public function getSummary(array $filters): array
    {
        return $this->repository->getKpiSummary($filters);
    }

    public function getPendingReviews(?int $desaId = null): array
    {
        return $this->repository->getPendingReviewsData($desaId);
    }

    public function getById(int $id): ?SetoranKecamatan
    {
        return $this->repository->findById($id);
    }

    public function createSetoran(array $data): SetoranKecamatan
    {
        $user = auth()->user();
        $desaId = $data['desa_id'] ?? ($user ? $user->desa_id : null);

        // Fetch desa code for custom nomor_bukti
        $kodeDesa = 'DESA';
        if ($desaId) {
            $desa = Desa::find($desaId);
            if ($desa) {
                $kodeDesa = $desa->kode_desa;
            }
        }

        // Auto generate nomor_bukti if missing
        if (empty($data['nomor_bukti'])) {
            $datePrefix = date('Ymd', strtotime($data['tanggal_setor'] ?? now()));
            $randomSuffix = strtoupper(Str::random(4));
            $data['nomor_bukti'] = "STR/{$kodeDesa}/{$datePrefix}/{$randomSuffix}";
        }

        if (empty($data['desa_id']) && $desaId) {
            $data['desa_id'] = $desaId;
        }

        $kategori = $data['kategori'] ?? 'SETOR_KECAMATAN';
        $data['kategori'] = $kategori;
        $tahun = (int) ($data['tahun'] ?? date('Y'));
        $nominal = (float) ($data['nominal'] ?? 0);

        // Validasi Saldo Kas Desa: Jika Saldo Kas Rp 0 atau nominal melebihi sisa kas, tolak pengeluaran
        if ($desaId) {
            $sisaKas = $this->repository->getSisaKasDesa((int) $desaId, $tahun);
            if ($sisaKas <= 0) {
                throw new \Exception("Saldo kas PBB-P2 desa saat ini Rp 0. Tidak dapat membuat pengeluaran baru sampai ada penerimaan pembayaran PBB-P2 yang masuk.");
            }
            if ($nominal > $sisaKas) {
                throw new \Exception("Nominal pengeluaran (Rp " . number_format($nominal, 0, ',', '.') . ") melebihi sisa saldo kas PBB-P2 yang tersedia (Rp " . number_format($sisaKas, 0, ',', '.') . ").");
            }
        }

        if ($kategori === 'SETOR_KECAMATAN') {
            $data['perlu_verifikasi_kecamatan'] = true;
            $data['status'] = $data['status'] ?? 'PENDING';
        } else {
            $data['perlu_verifikasi_kecamatan'] = false;
            $data['status'] = $data['status'] ?? 'PENDING';
            $data['tanggal_diterima'] = null;
        }

        $setoran = $this->repository->create($data);

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'CREATE_SETORAN',
            'module' => 'SETORAN_KECAMATAN',
            'payload' => [
                'setoran_id' => $setoran->id,
                'nomor_bukti' => $setoran->nomor_bukti,
                'jumlah_setoran' => $setoran->jumlah_setoran,
                'desa_id' => $setoran->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $setoran;
    }

    public function updateSetoran(int $id, array $data): SetoranKecamatan
    {
        $setoran = $this->repository->findById($id);
        if (!$setoran) {
            throw new \Exception("Data setoran tidak ditemukan.");
        }

        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));
        $isSameDesa = $user && ((int)$user->desa_id === (int)$setoran->desa_id);

        if (!$isSuperAdmin && !$isSameDesa) {
            throw new \Exception("Anda tidak memiliki wewenang untuk mengubah data desa ini.");
        }

        $desaId = $setoran->desa_id;
        $tahun = (int) ($data['tahun'] ?? $setoran->tahun);
        $nominalBaru = isset($data['nominal']) ? (float) $data['nominal'] : (float) $setoran->nominal;

        // Validasi Saldo Kas Desa saat Edit
        if ($desaId) {
            $sisaKas = $this->repository->getSisaKasDesa((int) $desaId, $tahun);
            // Kembalikan nominal lama ke buffer kapasitas jika transaksi lama sudah DITERIMA
            $availableKas = $sisaKas + ($setoran->status === 'DITERIMA' ? (float) $setoran->nominal : 0.0);
            if ($availableKas <= 0) {
                throw new \Exception("Saldo kas PBB-P2 desa saat ini Rp 0. Tidak dapat memproses pengeluaran.");
            }
            if ($nominalBaru > $availableKas) {
                throw new \Exception("Nominal pengeluaran (Rp " . number_format($nominalBaru, 0, ',', '.') . ") melebihi sisa saldo kas PBB-P2 yang tersedia (Rp " . number_format($availableKas, 0, ',', '.') . ").");
            }
        }

        // Setiap kali data diedit oleh pihak desa, kembalikan status ke PENDING untuk verifikasi ulang
        if (!$isSuperAdmin) {
            $data['status'] = 'PENDING';
            $data['tanggal_diterima'] = null;
            $data['penerima_user_id'] = null;
            if ($setoran->kategori === 'SETOR_KECAMATAN' || ($data['kategori'] ?? '') === 'SETOR_KECAMATAN') {
                $data['perlu_verifikasi_kecamatan'] = true;
                $data['catatan_kecamatan'] = 'Data diedit oleh Desa, menunggu verifikasi ulang Kecamatan.';
            } else {
                $data['perlu_verifikasi_kecamatan'] = false;
                $data['catatan_kecamatan'] = 'Data diedit oleh Desa, menunggu ACC ulang Kepala Desa.';
            }
        }

        $this->repository->update($setoran, $data);

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'UPDATE_SETORAN',
            'module' => 'SETORAN_KECAMATAN',
            'payload' => [
                'setoran_id' => $setoran->id,
                'nomor_bukti' => $setoran->nomor_bukti,
                'nominal' => $setoran->nominal,
                'kategori' => $setoran->kategori,
                'status' => $setoran->status,
                'desa_id' => $setoran->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $setoran->fresh(['desa', 'penerimaUser', 'creator']);
    }

    public function verifySetoran(int $id, string $status, ?string $catatanKecamatan, ?string $tanggalDiterima): ?SetoranKecamatan
    {
        $setoran = $this->repository->findById($id);
        if (!$setoran) {
            throw new \Exception("Data catatan pengeluaran/setoran tidak ditemukan.");
        }

        if (!in_array($status, ['DITERIMA', 'DITOLAK', 'PENDING', 'PENDING_HAPUS', 'APPROVE_DELETE', 'REJECT_DELETE'])) {
            throw new \Exception("Status verifikasi tidak valid.");
        }

        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));
        $isKades = $user && $user->role === 'KEPALA_DESA';

        // Authorization check:
        if ($setoran->kategori === 'SETOR_KECAMATAN') {
            // Setor ke kecamatan HANYA bisa diverifikasi oleh Admin Kecamatan (Super Admin)
            if (!$isSuperAdmin) {
                throw new \Exception("Verifikasi setor ke kecamatan hanya dapat dilakukan oleh pihak Kecamatan.");
            }
        } else {
            // Pengeluaran internal desa: HANYA Kepala Desa dari desa terkait yang berhak verifikasi/ACC (Super Admin TIDAK BISA)
            $isSameDesaKades = $isKades && ((int)$user->desa_id === (int)$setoran->desa_id);
            if (!$isSameDesaKades) {
                throw new \Exception("Persetujuan (ACC) pengeluaran internal desa hanya dapat dilakukan oleh Kepala Desa terkait.");
            }
        }

        // Jika status adalah APPROVE_DELETE (Verifikator menyetujui permohonan hapus):
        if ($status === 'APPROVE_DELETE') {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => 'APPROVE_DELETE_SETORAN',
                'module' => 'SETOR_KECAMATAN',
                'payload' => [
                    'setoran_id' => $setoran->id,
                    'nomor_bukti' => $setoran->nomor_bukti,
                    'kategori' => $setoran->kategori,
                    'status' => 'DELETED',
                    'catatan' => $catatanKecamatan,
                    'desa_id' => $setoran->desa_id,
                ],
                'ip_address' => request()->ip(),
            ]);

            $this->repository->delete($setoran);
            return null;
        }

        // Jika status adalah REJECT_DELETE (Verifikator menolak permohonan hapus):
        if ($status === 'REJECT_DELETE') {
            $status = 'DITERIMA';
            $catatanKecamatan = $catatanKecamatan ?: 'Permohonan penghapusan ditolak oleh pejabat berwenang.';
        }

        $this->repository->verify($setoran, $status, $catatanKecamatan, $tanggalDiterima);

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'VERIFY_SETORAN',
            'module' => 'SETOR_KECAMATAN',
            'payload' => [
                'setoran_id' => $setoran->id,
                'nomor_bukti' => $setoran->nomor_bukti,
                'status' => $status,
                'catatan' => $catatanKecamatan,
                'tanggal_diterima' => $tanggalDiterima,
                'desa_id' => $setoran->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return $setoran->fresh(['desa']);
    }

    public function deleteSetoran(int $id): ?array
    {
        $setoran = $this->repository->findById($id);
        if (!$setoran) {
            throw new \Exception("Data setoran tidak ditemukan.");
        }

        $user = auth()->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));
        $isKades = $user && $user->role === 'KEPALA_DESA';
        $isSameDesa = $user && ((int)$user->desa_id === (int)$setoran->desa_id);

        if (!$isSuperAdmin && !$isSameDesa) {
            throw new \Exception("Anda tidak memiliki wewenang untuk menghapus data desa ini.");
        }

        // Direct Delete:
        // - Super Admin (Kecamatan) hanya bisa langsung hapus SETOR_KECAMATAN
        // - Kepala Desa hanya bisa langsung hapus Pengeluaran Internal desanya
        $isAuthorizedDirectDelete =
            ($isSuperAdmin && $setoran->kategori === 'SETOR_KECAMATAN') ||
            ($isKades && $isSameDesa && $setoran->kategori !== 'SETOR_KECAMATAN');

        if ($isAuthorizedDirectDelete) {
            AuditLog::create([
                'user_id' => $user?->id,
                'action' => 'DELETE_SETORAN',
                'module' => 'SETOR_KECAMATAN',
                'payload' => [
                    'setoran_id' => $setoran->id,
                    'nomor_bukti' => $setoran->nomor_bukti,
                    'nominal' => $setoran->nominal,
                    'kategori' => $setoran->kategori,
                    'status' => $setoran->status,
                    'desa_id' => $setoran->desa_id,
                ],
                'ip_address' => request()->ip(),
            ]);

            $this->repository->delete($setoran);
            return [
                'action' => 'DELETED',
                'is_deleted' => true,
                'message' => 'Data pengeluaran / setoran berhasil dihapus.',
            ];
        }

        // Jika user adalah Admin Desa / Bendahara:
        // Setoran ke Kecamatan butuh verifikasi Admin Kecamatan
        // Pengeluaran Internal butuh verifikasi Kepala Desa
        $verifierTitle = $setoran->kategori === 'SETOR_KECAMATAN' ? 'Admin Kecamatan' : 'Kepala Desa';
        $setoran->status = 'PENDING_HAPUS';
        $setoran->catatan_kecamatan = "Permohonan penghapusan diajukan oleh Admin Desa ({$user->name}), menunggu verifikasi {$verifierTitle}.";
        $setoran->save();

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'REQUEST_DELETE_SETORAN',
            'module' => 'SETORAN_KECAMATAN',
            'payload' => [
                'setoran_id' => $setoran->id,
                'nomor_bukti' => $setoran->nomor_bukti,
                'nominal' => $setoran->nominal,
                'kategori' => $setoran->kategori,
                'status' => 'PENDING_HAPUS',
                'desa_id' => $setoran->desa_id,
            ],
            'ip_address' => request()->ip(),
        ]);

        return [
            'action' => 'REQUESTED_DELETE',
            'is_deleted' => false,
            'setoran' => $setoran->fresh(['desa', 'penerimaUser', 'creator']),
            'message' => "Permohonan penghapusan berhasil diajukan dan menunggu verifikasi {$verifierTitle}.",
        ];
    }
}
