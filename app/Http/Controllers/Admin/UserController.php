<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount(['orders', 'reviews']);

        // Búsqueda
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        // Filtro por rol
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Ordenamiento
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    public function show($id)
    {
        $user = User::withCount(['orders', 'reviews', 'wishlistProducts'])
            ->findOrFail($id);

        // Órdenes recientes del usuario
        $recent_orders = Order::where('user_id', $id)
            ->select('id', 'total_amount', 'status', 'created_at')
            ->latest()
            ->take(5)
            ->get();

        // Estadísticas del usuario
        $userStats = [
            'total_orders' => $user->orders_count,
            'total_reviews' => $user->reviews_count,
            'total_wishlist_items' => $user->wishlist_products_count,
            'total_spent' => Order::where('user_id', $id)
                ->where('status', 'completed')
                ->sum('total_amount'),
            'pending_orders' => Order::where('user_id', $id)
                ->whereIn('status', ['pending', 'processing'])
                ->count(),
        ];

        return response()->json([
            'user' => $user->makeHidden(['email_verified_at', 'remember_token']),
            'recent_orders' => $recent_orders,
            'stats' => $userStats
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'role' => 'sometimes|required|in:admin,user',
            'password' => 'nullable|min:6',
        ]);

        DB::beginTransaction();
        try {
            $updateData = $request->only(['name', 'email', 'role']);

            // Actualizar password si se proporciona
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            DB::commit();

            return response()->json([
                'message' => 'Usuario actualizado correctamente',
                'user' => $user->fresh()->makeHidden(['email_verified_at', 'remember_token'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
