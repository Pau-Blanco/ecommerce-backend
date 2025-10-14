<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Periodo para estadísticas (últimos 30 días)
        $startDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Estadísticas generales
        $stats = [
            'total_products' => Product::count(),
            'total_orders' => Order::count(),
            'total_users' => User::count(),
            'total_categories' => Category::count(),
            'total_revenue' => Order::where('status', 'completed')->sum('total_amount'),
            'pending_orders' => Order::where('status', 'pending')->count(),
            'low_stock_products' => Product::where('stock', '<', 10)->count(),
        ];

        // Estadísticas de los últimos 30 días
        $recentStats = [
            'new_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'new_users' => User::whereBetween('created_at', [$startDate, $endDate])->count(),
            'new_products' => Product::whereBetween('created_at', [$startDate, $endDate])->count(),
            'recent_revenue' => Order::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('total_amount'),
        ];

        // Órdenes recientes
        $recent_orders = Order::with(['user:id,name,email'])
            ->latest()
            ->take(8)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'user_name' => $order->user->name ?? 'Cliente',
                    'user_email' => $order->user->email ?? 'N/A',
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'created_at' => $order->created_at->format('Y-m-d H:i'),
                    'items_count' => $order->orderItems->count(),
                ];
            });

        // Productos más vendidos
        $top_products = DB::table('order_items')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->select(
                'products.id',
                'products.name',
                'products.price',
                'products.stock',
                'products.image_url',
                DB::raw('SUM(order_items.quantity) as total_sold'),
                DB::raw('SUM(order_items.quantity * order_items.unit_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.price', 'products.stock', 'products.image_url')
            ->orderBy('total_sold', 'desc')
            ->take(6)
            ->get();

        // Productos con stock bajo
        $low_stock_products = Product::with('category:id,name')
            ->where('stock', '<', 10)
            ->orderBy('stock', 'asc')
            ->take(5)
            ->get(['id', 'name', 'stock', 'price', 'image_url', 'category_id']);

        return response()->json([
            'stats' => $stats,
            'recent_stats' => $recentStats,
            'recent_orders' => $recent_orders,
            'top_products' => $top_products,
            'low_stock_products' => $low_stock_products,
        ]);
    }
}
