<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\HandlesCollectorDusunFilter;
use App\Http\Controllers\Controller;
use App\Services\Report21ColumnService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use HandlesCollectorDusunFilter;

    public function __construct(protected Report21ColumnService $reportService) {}

    public function report21Column(Request $request)
    {
        $tahun = (int) ($request->tahun ?? 2026);
        $buku = $request->buku;
        $desaId = $request->desa_id;
        $dusunFilter = $this->getEffectiveDusunFilter($request);
        $data = $this->reportService->generate21ColumnReport($tahun, $dusunFilter, $buku, $desaId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
