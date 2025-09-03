<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\FlashcardReview;
use App\Models\StudyMaterial;

class FlashcardReviewController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'study_material_id' => 'required|exists:study_materials,id',
            'rating' => 'required|in:again,hard,good,easy',
            'reviewed_at' => 'nullable|date',
            'study_time' => 'nullable|integer|min:0',
            'session_id' => 'nullable|string',
        ]);

        $studyMaterial = StudyMaterial::findOrFail($validated['study_material_id']);
        if ($studyMaterial->type !== 'flashcard') {
            return response()->json(['error' => 'Only flashcard type study materials can be reviewed.'], 422);
        }

        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $review = FlashcardReview::create([
            'user_id' => $supabaseUser['id'],
            'study_material_id' => $validated['study_material_id'],
            'rating' => $validated['rating'],
            'reviewed_at' => $validated['reviewed_at'] ?? now(),
            'study_time' => $validated['study_time'] ?? 0,
            'session_id' => $validated['session_id'] ?? null,
        ]);

        return response()->json($review->load('studyMaterial'), 201);
    }

    public function index(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $query = FlashcardReview::with('studyMaterial')
            ->where('user_id', $supabaseUser['id'])
            ->whereHas('studyMaterial', function ($q) {
                $q->where('type', 'flashcard');
            });

        if ($request->has('session_id')) {
            $query->where('session_id', $request->input('session_id'));
        }
        if ($request->has('study_material_id')) {
            $query->where('study_material_id', $request->input('study_material_id'));
        }

        $reviews = $query->get();
        return response()->json($reviews);
    }
}
