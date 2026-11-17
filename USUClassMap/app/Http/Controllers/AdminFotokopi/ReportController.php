<?php

namespace App\Http\Controllers\AdminFotokopi;

use App\Http\Controllers\Controller;
use App\Models\FotocopyOrder;
use App\Models\FotocopyStore;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = FotocopyOrder::with(['store', 'items']);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->input('order_status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sq) use ($q) {
                $sq->where('order_code', 'like', "%{$q}%")
                    ->orWhere('customer_name', 'like', "%{$q}%")
                    ->orWhere('notes', 'like', "%{$q}%");
            });
        }

        $orders = $query->latest()->paginate(15)->withQueryString();

        $stores = FotocopyStore::all(['id', 'name']);
        $totalOrdersCount = (clone $query)->count();
        $totalRevenueSum = (clone $query)->where('payment_status', 'paid')->sum('total_price');

        return view('features.fotocopy.admin.reports.index', compact(
            'orders',
            'stores',
            'totalOrdersCount',
            'totalRevenueSum'
        ));
    }
}
