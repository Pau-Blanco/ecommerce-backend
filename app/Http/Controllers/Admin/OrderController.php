<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with(['user:id,name,email', 'orderItems.product:id,name,image_url']);

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', '%' . $search . '%')
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('email', 'like', '%' . $search . '%');
                    });
            });
        }

        // Filtro por estado
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filtro por fecha
        if ($request->has('start_date') && $request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date') && $request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Ordenamiento
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $orders = $query->paginate($perPage);

        // Estadísticas de órdenes por estado
        $orderStats = Order::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        return response()->json([
            'orders' => $orders,
            'stats' => $orderStats
        ]);
    }

    public function show($id)
    {
        $order = Order::with([
            'user:id,name,email',
            'orderItems.product:id,name,price,image_url',
            'orderItems' => function ($query) {
                $query->select('id', 'order_id', 'product_id', 'quantity', 'unit_price');
            }
        ])->findOrFail($id);

        return response()->json([
            'order' => $order
        ]);
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $request->validate([
            'status' => 'sometimes|required|in:pending,processing,completed,cancelled',
            'total_amount' => 'sometimes|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            $oldStatus = $order->status;

            $order->update([
                'status' => $request->status ?? $order->status,
                'total_amount' => $request->total_amount ?? $order->total_amount,
            ]);

            // Log del cambio de estado
            if ($oldStatus !== $order->status) {
                \Log::info("Orden #{$order->id} cambió de estado: {$oldStatus} -> {$order->status}");
            }

            DB::commit();

            return response()->json([
                'message' => 'Orden actualizada correctamente',
                'order' => $order->load(['user:id,name,email', 'orderItems.product:id,name'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar la orden',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
