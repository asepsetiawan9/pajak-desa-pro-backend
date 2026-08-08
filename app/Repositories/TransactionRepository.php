<?php

namespace App\Repositories;

use App\Models\TransactionRecord;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionRepository
{
    public function getFilteredTransactions(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = TransactionRecord::query()->with(['operator:id,name', 'dhkpRows']);

        $effectivePerPage = !empty($filters['limit']) ? (int) $filters['limit'] : (!empty($filters['per_page']) ? (int) $filters['per_page'] : $perPage);
        if ($effectivePerPage <= 0) {
            $effectivePerPage = 10000;
        }

        if (!empty($filters['status_void'])) {
            $query->where('status_void', $filters['status_void'] === 'true');
        }

        if (!empty($filters['dusun']) && $filters['dusun'] !== 'ALL') {
            $dusuns = is_array($filters['dusun'])
                ? $filters['dusun']
                : array_map('trim', explode(',', $filters['dusun']));

            $query->whereHas('dhkpRows', function ($dq) use ($dusuns) {
                $dq->where(function ($q) use ($dusuns) {
                    foreach ($dusuns as $index => $d) {
                        if ($index === 0) {
                            $q->where('dusun', 'LIKE', $d);
                        } else {
                            $q->orWhere('dusun', 'LIKE', $d);
                        }
                    }
                });
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nomor_stts', 'like', "%{$search}%")
                  ->orWhereHas('dhkpRows', function ($dq) use ($search) {
                      $dq->where('nama_wp', 'like', "%{$search}%")
                         ->orWhere('nop', 'like', "%{$search}%");
                  });
            });
        }

        return $query->orderBy('tanggal_transaksi', 'desc')->paginate($effectivePerPage);
    }

    public function findById(int $id): ?TransactionRecord
    {
        return TransactionRecord::with(['operator', 'voidUser', 'dhkpRows'])->find($id);
    }

    public function findBySttsNumber(string $sttsNumber): ?TransactionRecord
    {
        return TransactionRecord::with(['operator', 'voidUser', 'dhkpRows'])
            ->where('nomor_stts', $sttsNumber)
            ->first();
    }

    public function create(array $data): TransactionRecord
    {
        return TransactionRecord::create($data);
    }

    public function update(TransactionRecord $transaction, array $data): TransactionRecord
    {
        $transaction->update($data);
        return $transaction->fresh();
    }
}
