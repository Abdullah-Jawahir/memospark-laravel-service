<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deck;

class DeckController extends Controller
{
    public function store(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $request->validate([
            'name' => 'required|string|max:255',
        ]);
        $deck = Deck::create([
            'user_id' => $supabaseUser['id'],
            'name' => $request->name,
        ]);
        return response()->json($deck, 201);
    }
}
