<?php

namespace App\Http\Controllers;

use App\Models\ScanLog;
use App\Services\ReturnCheckService;
use App\Services\ScanModeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScanHistoryController extends Controller
{
    public function __construct(
        private ReturnCheckService $returns,
        private ScanModeService $modes,
    ) {}

    public function index(): View
    {
        $user = Auth::user();

        [$scans, $lastScan] = $this->buildScanData($user);

        $todayKey = today()->toDateString();
        $todayScans = $scans->filter(fn ($s) => $s['dateRaw'] === $todayKey);

        $totalScansToday = $todayScans->count();
        $successScansToday = $todayScans->where('status', 'success')->count();
        $missingAlertsToday = $todayScans->whereIn('status', ['missing', 'warning'])->count();
        $avgDuration = $todayScans->isNotEmpty()
            ? round($todayScans->avg('durationSeconds'), 1)
            : 0;

        // Scan Berangkat / Scan Pulang tersedia kapan saja selama ada kegiatan aktif.
        $hasActivity = $user->activeActivity !== null;
        $canPack = $hasActivity;
        $canReturn = $hasActivity;
        $returnOpensAt = null;

        // Mode yang masih tersimpan tapi kegiatannya sudah tidak ada dibuang.
        $scanMode = $this->modes->current($user);
        if (! $hasActivity && $scanMode) {
            $this->modes->clear($user);
            $scanMode = null;
        }
        $scanModeRemaining = $scanMode ? $this->modes->remaining($user) : 0;

        return view('scan-history.index', compact(
            'scans',
            'totalScansToday',
            'successScansToday',
            'missingAlertsToday',
            'avgDuration',
            'lastScan',
            'canPack',
            'canReturn',
            'returnOpensAt',
            'scanMode',
            'scanModeRemaining',
        ));
    }

    public function latest(Request $request): JsonResponse
    {
        $latest = ScanLog::where('user_id', Auth::id())
            ->latest('scanned_at')
            ->first(['id', 'scanned_at']);

        return response()->json([
            'latestId' => $latest?->id,
            'latestAt' => $latest?->scanned_at?->toIso8601String(),
        ]);
    }

    /**
     * Pengguna memilih mode pindai: 'packing' (Scan Berangkat), 'return' (Scan Pulang),
     * atau 'none' (batal).
     */
    public function setMode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['packing', 'return', 'none'])],
        ]);

        $user = Auth::user();

        if ($data['mode'] === 'none') {
            $this->modes->clear($user);

            return response()->json(['mode' => null, 'remaining' => 0]);
        }

        if (! $user->activeActivity) {
            return response()->json([
                'message' => 'Pilih kegiatan aktif dulu di menu Kegiatan.',
            ], 422);
        }

        $this->modes->set($user, $data['mode']);

        return response()->json([
            'mode' => $data['mode'],
            'remaining' => ScanModeService::TTL_SECONDS,
        ]);
    }

    /** Unduh seluruh riwayat pindai sebagai CSV (bisa dibuka di Excel/Google Sheets). */
    public function export(): StreamedResponse
    {
        $user = Auth::user();

        [$scans] = $this->buildScanData($user);

        $statusLabels = [
            'success' => 'Berhasil',
            'missing' => 'Barang Kurang',
            'warning' => 'Peringatan',
        ];

        $filename = 'riwayat-pindai-' . Str::slug($user->name) . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($scans, $statusLabels) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Kegiatan', 'Tanggal', 'Waktu', 'Jenis Pindai', 'Status', 'Durasi',
                'Barang Terdeteksi', 'Barang Kurang', 'Ringkasan',
            ]);

            foreach ($scans as $scan) {
                fputcsv($out, [
                    $scan['activityName'],
                    $scan['date'],
                    $scan['time'],
                    $scan['scanType'],
                    $statusLabels[$scan['status']] ?? $scan['status'],
                    $scan['duration'],
                    implode(', ', $scan['detectedItems']),
                    implode(', ', $scan['missingItems']),
                    $scan['aiSummary'],
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Bangun daftar $scans (dikelompokkan per kegiatan + tanggal + fase) dan scan terbaru.
     * Dipakai bareng oleh index() dan export(), supaya angkanya selalu sama.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: ScanLog|null}
     */
    private function buildScanData($user): array
    {
        $scanLogs = ScanLog::with(['item', 'activity'])
            ->where('user_id', $user->id)
            ->latest('scanned_at')
            ->get();

        $scans = $scanLogs
            ->groupBy(fn (ScanLog $log) => $log->activity_id . '|' . $log->for_date->toDateString() . '|' . $log->phase)
            ->map(fn ($logs, string $key) => $this->summarizeGroup($logs, $key))
            ->sortByDesc('timestamp')
            ->values();

        return [$scans, $scanLogs->first()];
    }

    private function summarizeGroup($logs, string $key): array
    {
        $phase = explode('|', $key)[2] ?? 'packing';
        $first = $logs->first();
        $activity = $first->activity;
        $date = $first->for_date->copy();

        $detectedItems = $logs->pluck('item.name')->filter()->unique()->values();

        if ($phase === 'return') {
            $requiredItems = $activity
                ? $this->returns->packedItems($activity)->pluck('name')->values()
                : collect();
            $scanType = 'Scan Pulang';
        } else {
            $requiredItems = $activity
                ? $activity->items()->where('items.status', 'active')->orderBy('items.name')->pluck('items.name')
                : collect();
            $scanType = 'Scan Berangkat';
        }

        $normalizedDetected = $detectedItems->map(fn ($name) => mb_strtolower(trim($name)));
        $normalizedRequired = $requiredItems->map(fn ($name) => mb_strtolower(trim($name)));

        $missingItems = $requiredItems
            ->reject(fn ($name) => $normalizedDetected->contains(mb_strtolower(trim($name))))
            ->values();

        // Barang di luar daftar ikut tampil di "terdeteksi", tapi tidak dihitung ke angka "x / y".
        $detectedCount = $requiredItems->isEmpty()
            ? $detectedItems->count()
            : $normalizedDetected->filter(fn ($name) => $normalizedRequired->contains($name))->count();

        $status = match (true) {
            $requiredItems->isEmpty() => 'success',
            $missingItems->isEmpty() => 'success',
            $detectedItems->isEmpty() => 'missing',
            default => 'warning',
        };

        $itemsTotal = $requiredItems->isEmpty() ? $detectedItems->count() : $requiredItems->count();

        $lastLog = $logs->sortByDesc('scanned_at')->first();
        $firstLog = $logs->sortBy('scanned_at')->first();
        $durationSeconds = max(0.1, $firstLog->scanned_at->diffInSeconds($lastLog->scanned_at) ?: 0.5);

        return [
            'id' => $key,
            'activityName' => $activity?->name ?? '-',
            'time' => $lastLog->scanned_at->format('H:i'),
            'date' => $date->locale('id')->translatedFormat('j M Y'),
            'dateRaw' => $date->toDateString(),
            'scanType' => $scanType,
            'status' => $status,
            'duration' => rtrim(rtrim(number_format($durationSeconds, 1), '0'), '.') . ' detik',
            'durationSeconds' => $durationSeconds,
            'itemsDetected' => $detectedCount,
            'itemsTotal' => $itemsTotal,
            'detectedItems' => $detectedItems->all(),
            'missingItems' => $missingItems->all(),
            'aiSummary' => $this->summaryText($status, $phase, $activity?->name, $missingItems),
            'device' => 'ESP32-01',
            'location' => '-',
            'signal' => '-',
            'timestamp' => $lastLog->scanned_at->timestamp,
        ];
    }

    private function summaryText(string $status, string $phase, ?string $activityName, $missingItems): string
    {
        $label = $activityName ? "\"{$activityName}\"" : 'kegiatan ini';

        if ($status === 'success' && $missingItems->isEmpty()) {
            return $phase === 'return'
                ? 'Semua barang yang dibawa sudah kembali ke tas. Aman untuk pulang!'
                : "Semua barang untuk {$label} sudah lengkap.";
        }

        if ($status === 'missing') {
            return $phase === 'return'
                ? 'Belum ada barang yang terdeteksi kembali ke tas. Cek isi tas sebelum pulang.'
                : "Belum ada barang untuk {$label} yang terdeteksi. Cek kembali isi tas.";
        }

        return $phase === 'return'
            ? 'Barang berikut belum terdeteksi kembali ke tas: ' . $missingItems->implode(', ')
            : "Barang berikut belum dipindai untuk {$label}: " . $missingItems->implode(', ');
    }
}
