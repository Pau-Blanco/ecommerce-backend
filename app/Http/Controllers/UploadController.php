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
        // Verificar que el usuario es administrador
        if (!$request->user()->isAdmin()) {
            return response()->json([
                'message' => 'Unauthorized'
            ], Response::HTTP_FORBIDDEN);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $fileName = Str::random(20) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('products', $fileName, 'public');

            $url = Storage::disk('public')->url($path);

            return response()->json([
                'url' => $url,
                'path' => $path,
            ], Response::HTTP_CREATED);
        }

        return response()->json([
            'message' => 'No image uploaded'
        ], Response::HTTP_BAD_REQUEST);
    }
}
