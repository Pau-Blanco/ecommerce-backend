<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario está autenticado
        if (!auth()->check()) {
            return response()->json([
                'message' => 'No autenticado. Por favor inicia sesión.'
            ], 401);
        }

        // Verificar que es admin (usando role en lugar de is_admin)
        $user = auth()->user();
        if (!$user->isAdmin()) {
            return response()->json([
                'message' => 'Acceso denegado. Se requieren permisos de administrador.',
                'user_role' => $user->role
            ], 403);
        }

        return $next($request);
    }
}
