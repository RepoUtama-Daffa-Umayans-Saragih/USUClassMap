<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyOrder;
use App\Models\FotocopyStore;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $scopedStoreId = auth()->user()?->scopedStoreId();
        $baseQuery = FotocopyOrder::query();

        if ($scopedStoreId !== null) {
            $baseQuery->where('store_id', $scopedStoreId);
        } elseif ($request->filled('store_id')) {
            $baseQuery->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('order_status')) {
            $baseQuery->where('order_status', $request->input('order_status'));
        }

        if ($request->filled('payment_method')) {
            $baseQuery->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $baseQuery->where(function ($sq) use ($q) {
                $sq->where('order_code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        // Hitung seluruh metrik dari query penuh SEBELUM memanggil paginate()
        $totalOrdersCount = (clone $baseQuery)->count();
        $totalRevenueSum = (int) round((float) (clone $baseQuery)->where('payment_status', 'paid')->sum('total_price'));

        // Grafik Tren 14 Hari Terakhir (WIB Asia/Jakarta)
        $nowWib = Carbon::now('Asia/Jakarta');
        $startDay = $nowWib->copy()->subDays(13)->startOfDay();
        $appTimezone = config('app.timezone', 'Asia/Jakarta');
        $recentRows = (clone $baseQuery)
            ->where('created_at', '>=', $startDay->copy()->timezone($appTimezone))
            ->get(['created_at', 'payment_status', 'total_price']);

        $trendMap = [];
        for ($i = 13; $i >= 0; $i--) {
            $d = $nowWib->copy()->subDays($i);
            $key = $d->format('Y-m-d');
            $trendMap[$key] = [
                'date' => $key,
                'date_label' => $d->format('d M'),
                'full_label' => $d->copy()->locale('id')->translatedFormat('l, d F Y'),
                'orders_count' => 0,
                'paid_revenue' => 0,
                'height_pct' => 0,
                'bar_px' => 4,
            ];
        }

        foreach ($recentRows as $row) {
            $k = $row->created_at?->copy()->timezone('Asia/Jakarta')->format('Y-m-d');
            if ($k && isset($trendMap[$k])) {
                $trendMap[$k]['orders_count']++;
                if (strtolower((string) $row->payment_status) === 'paid') {
                    $trendMap[$k]['paid_revenue'] += (int) round((float) $row->total_price);
                }
            }
        }

        $maxTrendOrders = max(1, ...array_column($trendMap, 'orders_count'));
        foreach ($trendMap as &$point) {
            if ($point['orders_count'] > 0) {
                $heightPct = max(8, (int) round(($point['orders_count'] / $maxTrendOrders) * 100));
                $point['height_pct'] = $heightPct;
                $point['bar_px'] = max(14, (int) round(180 * ($heightPct / 100)));
            } else {
                $point['height_pct'] = 0;
                $point['bar_px'] = 4;
            }
        }
        unset($point);

        $dailyTrend = array_values($trendMap);
        $trendTotals = [
            'orders' => array_sum(array_column($dailyTrend, 'orders_count')),
            'revenue' => array_sum(array_column($dailyTrend, 'paid_revenue')),
        ];

        // Ringkasan Metode Pembayaran: khusus transaksi berstatus lunas sesuai filter aktif
        $paidRows = (clone $baseQuery)
            ->where('payment_status', 'paid')
            ->get(['payment_method', 'total_price']);

        $paymentBreakdown = [
            'qris' => ['label' => 'QRIS Dinamis', 'count' => 0, 'revenue' => 0, 'percentage' => 0.0],
            'bank_transfer' => ['label' => 'Transfer Bank', 'count' => 0, 'revenue' => 0, 'percentage' => 0.0],
            'cash' => ['label' => 'Tunai Kasir', 'count' => 0, 'revenue' => 0, 'percentage' => 0.0],
            'other' => ['label' => 'Lainnya', 'count' => 0, 'revenue' => 0, 'percentage' => 0.0],
        ];

        foreach ($paidRows as $row) {
            $m = strtolower(trim((string) $row->payment_method));
            $bucket = match (true) {
                in_array($m, ['qris', 'qris_dinamis', 'qris dinamis'], true) => 'qris',
                in_array($m, ['bank_transfer', 'transfer', 'transfer bank', 'va', 'virtual_account'], true) => 'bank_transfer',
                in_array($m, ['cash', 'tunai', 'tunai kasir'], true) => 'cash',
                default => 'other',
            };
            $paymentBreakdown[$bucket]['count']++;
            $paymentBreakdown[$bucket]['revenue'] += (int) round((float) $row->total_price);
        }

        if ($paymentBreakdown['other']['count'] === 0) {
            unset($paymentBreakdown['other']);
        }

        $paymentBreakdownTotal = (int) array_sum(array_column($paymentBreakdown, 'revenue'));
        foreach ($paymentBreakdown as &$stats) {
            $stats['percentage'] = $totalRevenueSum > 0
                ? round(($stats['revenue'] / $totalRevenueSum) * 100, 1)
                : 0.0;
        }
        unset($stats);

        $orders = (clone $baseQuery)->with(['store', 'items'])->latest()->paginate(15)->withQueryString();

        $stores = $scopedStoreId !== null
            ? FotocopyStore::where('id', $scopedStoreId)->get(['id', 'name'])
            : FotocopyStore::all(['id', 'name']);

        return view('features.fotocopy.admin.reports.index', compact(
            'orders',
            'stores',
            'totalOrdersCount',
            'totalRevenueSum',
            'dailyTrend',
            'maxTrendOrders',
            'trendTotals',
            'paymentBreakdown',
            'paymentBreakdownTotal'
        ));
    }
}
