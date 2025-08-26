<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Deck;
use App\Models\StudyMaterial;

class DeckController extends Controller
{
    public function index(Request $request)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }
        $decks = Deck::where('user_id', $supabaseUser['id'])
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'name']);
        return response()->json($decks);
    }
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

    public function materials(Request $request, $deckId)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        // Ensure deck belongs to user
        $deck = Deck::where('id', $deckId)->where('user_id', $supabaseUser['id'])->first();
        if (!$deck) {
            return response()->json(['error' => 'Deck not found or access denied'], 404);
        }

        // Fetch materials via deck's documents
        $materials = StudyMaterial::with('document')
            ->whereHas('document', function ($q) use ($deckId) {
                $q->where('deck_id', $deckId);
            })
            ->orderBy('id')
            ->get();

        // Group by type and map to a simpler shape for FE
        $response = [
            'deck' => [
                'id' => $deck->id,
                'name' => $deck->name,
            ],
            'flashcards' => [],
            'quizzes' => [],
            'exercises' => [],
        ];

        foreach ($materials as $m) {
            $content = $m->content ?? [];
            if ($m->type === 'flashcard') {
                // Normalize potential shapes
                if (isset($content['question']) && isset($content['answer'])) {
                    $response['flashcards'][] = [
                        'id' => $m->id,
                        'type' => 'flashcard',
                        'question' => $content['question'],
                        'answer' => $content['answer'],
                        'difficulty' => $content['difficulty'] ?? 'medium',
                    ];
                } elseif (is_array($content)) {
                    foreach ($content as $card) {
                        if (isset($card['question']) && isset($card['answer'])) {
                            $response['flashcards'][] = [
                                'id' => $m->id,
                                'type' => 'flashcard',
                                'question' => $card['question'],
                                'answer' => $card['answer'],
                                'difficulty' => $card['difficulty'] ?? 'medium',
                            ];
                        }
                    }
                }
            } elseif ($m->type === 'quiz') {
                if (is_array($content)) {
                    foreach ($content as $qz) {
                        if (isset($qz['question']) && isset($qz['options']) && isset($qz['correct_answer_option'])) {
                            $response['quizzes'][] = [
                                'type' => 'quiz',
                                'question' => $qz['question'],
                                'options' => $qz['options'],
                                'correct_answer_option' => $qz['correct_answer_option'],
                                'answer' => $qz['correct_answer_option'],
                                'difficulty' => $qz['difficulty'] ?? 'medium',
                            ];
                        }
                    }
                }
            } elseif ($m->type === 'exercise') {
                if (is_array($content)) {
                    foreach ($content as $ex) {
                        if (isset($ex['type']) && isset($ex['instruction'])) {
                            $response['exercises'][] = [
                                'type' => $ex['type'],
                                'instruction' => $ex['instruction'],
                                'exercise_text' => $ex['exercise_text'] ?? null,
                                'answer' => $ex['answer'] ?? '',
                                'difficulty' => $ex['difficulty'] ?? 'medium',
                                'concepts' => $ex['concepts'] ?? null,
                                'definitions' => $ex['definitions'] ?? null,
                            ];
                        }
                    }
                }
            }
        }

        return response()->json($response);
    }
}
