<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        \Log::info('=== UPLOAD DEBUG START ===');
        \Log::info('User ID: ' . $request->user()->id);
        \Log::info('User role: ' . $request->user()->role);
        \Log::info('Is admin: ' . ($request->user()->isAdmin() ? 'YES' : 'NO'));
        \Log::info('Files received: ' . ($request->hasFile('image') ? 'YES' : 'NO'));

        // Verificar que el usuario es administrador
        if (!$request->user()->isAdmin()) {
            \Log::warning('User is not admin - Blocking upload');
            return response()->json([
                'message' => 'Unauthorized'
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            \Log::info('File details: ' . $file->getClientOriginalName() . ' - ' . $file->getSize() . ' bytes');

            $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');

            $url = Storage::disk('public')->url($path);

            \Log::info('File stored at: ' . $path);
            \Log::info('Generated URL: ' . $url);
            \Log::info('=== UPLOAD DEBUG END - SUCCESS ===');

            return response()->json([
                'url' => $url,
                'path' => $path,
            ], Response::HTTP_CREATED);
        }

        \Log::warning('No file received in request');
        return response()->json([
            'message' => 'No image uploaded'
        ], Response::HTTP_BAD_REQUEST);
    }
}
