<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyOrder;
use App\Models\FotocopyService;
use App\Models\FotocopyStore;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $scopedStoreId = auth()->user()?->scopedStoreId();

        $storeQuery = FotocopyStore::query();
        $serviceQuery = FotocopyService::query();
        $orderQuery = FotocopyOrder::query();

        if ($scopedStoreId !== null) {
            $storeQuery->where('id', $scopedStoreId);
            $serviceQuery->where('store_id', $scopedStoreId);
            $orderQuery->where('store_id', $scopedStoreId);
        }

        $totalStores = (clone $storeQuery)->count();
        $totalServices = (clone $serviceQuery)->count();
        $totalOrders = (clone $orderQuery)->count();
        $totalRevenue = (clone $orderQuery)->where('payment_status', 'paid')->sum('total_price');

        $pendingOrders = (clone $orderQuery)->where('order_status', 'Menunggu')->count();
        $processingOrders = (clone $orderQuery)->where('order_status', 'Diproses')->count();
        $completedOrders = (clone $orderQuery)->where('order_status', 'Selesai')->count();

        $recentOrders = (clone $orderQuery)
            ->with(['store', 'items'])
            ->latest()
            ->take(6)
            ->get();

        $stores = (clone $storeQuery)
            ->withCount(['services', 'orders'])
            ->orderBy('rating', 'desc')
            ->take(5)
            ->get();

        return view('features.fotocopy.admin.dashboard', compact(
            'totalStores',
            'totalServices',
            'totalOrders',
            'totalRevenue',
            'pendingOrders',
            'processingOrders',
            'completedOrders',
            'recentOrders',
            'stores'
        ));
    }
}
