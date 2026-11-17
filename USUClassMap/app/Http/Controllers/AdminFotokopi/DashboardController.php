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
        $totalStores = FotocopyStore::count();
        $totalServices = FotocopyService::count();
        $totalOrders = FotocopyOrder::count();
        $totalRevenue = FotocopyOrder::where('payment_status', 'paid')->sum('total_price');

        $pendingOrders = FotocopyOrder::where('order_status', 'Menunggu')->count();
        $processingOrders = FotocopyOrder::where('order_status', 'Diproses')->count();
        $completedOrders = FotocopyOrder::where('order_status', 'Selesai')->count();

        $recentOrders = FotocopyOrder::with(['store', 'items'])
            ->latest()
            ->take(6)
            ->get();

        $stores = FotocopyStore::withCount(['services', 'orders'])
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
