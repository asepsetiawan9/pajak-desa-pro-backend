<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesCollectorDusunFilter;
use App\Http\Controllers\Controller;
use App\Repositories\TransactionRepository;
use App\Services\PaymentService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    use HandlesCollectorDusunFilter;

    public function __construct(
        protected PaymentService $paymentService,
        protected TransactionRepository $transactionRepository
    ) {}

    public function index(Request $request)
    {
        $filters = $request->all();
        $user = $request->user();
        $isSuperAdmin = $user && ($user->role === 'SUPER_ADMIN_SYSTEM' || is_null($user->desa_id));

        if (!$isSuperAdmin && $user && $user->desa_id) {
            $filters['desa_id'] = $user->desa_id;
        }

        $effectiveDusun = $this->getEffectiveDusunFilter($request);
        if ($effectiveDusun) {
            $filters['dusun'] = $effectiveDusun;
        }

        $transactions = $this->transactionRepository->getFilteredTransactions($filters);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $transaction = $this->transactionRepository->findById($id);

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaksi tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    public function pay(Request $request)
    {
        $user = $request->user();
        if ($user && $user->role === 'KEPALA_DESA') {
            return response()->json([
                'success' => false,
                'message' => 'Kepala Desa hanya memiliki akses melihat data dan tidak dapat melakukan transaksi pembayaran.',
            ], 403);
        }

        $payload = $request->all();
        $operatorId = $user ? $user->id : 1;

        $transaction = $this->paymentService->processPayment($payload, $operatorId);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran Kasir STTS berhasil diproses',
            'data' => $transaction,
        ], 201);
    }

    public function void(Request $request, int $id)
    {
        $user = $request->user();
        if ($user && $user->role === 'KEPALA_DESA') {
            return response()->json([
                'success' => false,
                'message' => 'Kepala Desa hanya memiliki akses melihat data dan tidak dapat melakukan pembatalan transaksi.',
            ], 403);
        }

        $reason = $request->input('reason', 'Pembatalan transaksi oleh operator');
        $userId = $user ? $user->id : 1;

        $transaction = $this->paymentService->voidTransaction($id, $reason, $userId);

        return response()->json([
            'success' => true,
            'message' => 'Transaksi STTS berhasil di-void dan status DHKP di-rollback',
            'data' => $transaction,
        ]);
    }

    public function createGroup(Request $request)
    {
        $validated = $request->validate([
            'trxIds' => 'required|array',
            'groupName' => 'required|string',
        ]);

        $groupId = 'GRP-' . strtoupper(substr(md5($validated['groupName'] . time()), 0, 6));

        return response()->json([
            'success' => true,
            'message' => "Pengelompokan 1 KK ({$validated['groupName']}) berhasil dibuat",
            'data' => ['customGroupId' => $groupId],
        ]);
    }

    public function dissolveGroup(Request $request)
    {
        $validated = $request->validate([
            'customGroupId' => 'required|string',
        ]);

        return response()->json([
            'success' => true,
            'message' => "Pengelompokan {$validated['customGroupId']} berhasil dibubarkan",
            'data' => ['customGroupId' => $validated['customGroupId']],
        ]);
    }
}
