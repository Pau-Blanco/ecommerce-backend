<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['message' => 'TEST OK']);
    }
}
