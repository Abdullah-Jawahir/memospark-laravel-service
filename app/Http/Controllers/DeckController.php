<?php

namespace App\Http\Controllers;

use App\Models\Deck;
use Illuminate\Http\Request;
use App\Models\StudyMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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
                // Handle both single flashcard and array of flashcards
                if (isset($content['question']) && isset($content['answer'])) {
                    // Single flashcard format
                    $response['flashcards'][] = [
                        'id' => $m->id,
                        'type' => 'flashcard',
                        'question' => $content['question'],
                        'answer' => $content['answer'],
                        'difficulty' => $content['difficulty'] ?? 'medium',
                    ];
                } elseif (is_array($content)) {
                    // Array of flashcards
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
                // Handle both single quiz and array of quizzes
                if (isset($content['question']) && isset($content['options'])) {
                    // Single quiz format
                    $response['quizzes'][] = [
                        'id' => $m->id,  // Add StudyMaterial ID
                        'type' => 'quiz',
                        'question' => $content['question'],
                        'options' => $content['options'],
                        'correct_answer_option' => $content['correct_answer_option'] ?? $content['answer'] ?? '',
                        'answer' => $content['correct_answer_option'] ?? $content['answer'] ?? '',
                        'difficulty' => $content['difficulty'] ?? 'medium',
                    ];
                } elseif (is_array($content)) {
                    // Array of quizzes
                    foreach ($content as $qz) {
                        if (isset($qz['question']) && isset($qz['options'])) {
                            $response['quizzes'][] = [
                                'id' => $m->id,  // Add StudyMaterial ID
                                'type' => 'quiz',
                                'question' => $qz['question'],
                                'options' => $qz['options'],
                                'correct_answer_option' => $qz['correct_answer_option'] ?? $qz['answer'] ?? '',
                                'answer' => $qz['correct_answer_option'] ?? $qz['answer'] ?? '',
                                'difficulty' => $qz['difficulty'] ?? 'medium',
                            ];
                        }
                    }
                }
            } elseif ($m->type === 'exercise') {
                // Handle both single exercise and array of exercises
                if (isset($content['type']) && isset($content['instruction'])) {
                    // Single exercise format
                    $response['exercises'][] = [
                        'id' => $m->id,  // Add StudyMaterial ID
                        'type' => $content['type'],
                        'instruction' => $content['instruction'],
                        'exercise_text' => $content['question'] ?? $content['exercise_text'] ?? null,
                        'answer' => $content['answer'] ?? '',
                        'difficulty' => $content['difficulty'] ?? 'medium',
                        'concepts' => $content['concepts'] ?? null,
                        'definitions' => $content['definitions'] ?? null,
                    ];
                } elseif (is_array($content)) {
                    // Array of exercises
                    foreach ($content as $ex) {
                        if (isset($ex['type']) && isset($ex['instruction'])) {
                            $response['exercises'][] = [
                                'id' => $m->id,  // Add StudyMaterial ID
                                'type' => $ex['type'],
                                'instruction' => $ex['instruction'],
                                'exercise_text' => $ex['question'] ?? $ex['exercise_text'] ?? null,
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

        // Add debug information (remove this in production)
        $response['debug'] = [
            'deck_id_received' => $deckId,
            'materials_count' => $materials->count(),
            'materials_types' => $materials->pluck('type')->unique()->values(),
            'total_flashcards' => count($response['flashcards']),
            'total_quizzes' => count($response['quizzes']),
            'total_exercises' => count($response['exercises']),
        ];

        // Also include the raw materials for frontend to use when adding new cards
        $response['materials'] = $materials->map(function ($material) {
            return [
                'id' => $material->id,
                'type' => $material->type,
                'title' => ucfirst($material->type) . ' Material',
                'content_count' => is_array($material->content) ? count($material->content) : 1,
                'document_id' => $material->document_id
            ];
        });

        return response()->json($response);
    }

    /**
     * Generate missing material types for a deck
     */
    public function generateMissingMaterials(Request $request, $deckId)
    {
        $request->validate([
            'material_types' => 'required|array',
            'material_types.*' => 'string|in:flashcard,quiz,exercise',
        ]);

        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        // Ensure deck belongs to user
        $deck = Deck::where('id', $deckId)->where('user_id', $supabaseUser['id'])->first();
        if (!$deck) {
            return response()->json(['error' => 'Deck not found or access denied'], 404);
        }

        // Get documents for this deck
        $documents = \App\Models\Document::where('deck_id', $deckId)->get();
        if ($documents->isEmpty()) {
            return response()->json(['error' => 'No documents found for this deck'], 404);
        }

        $requestedTypes = $request->input('material_types');
        $generationStarted = false;

        foreach ($documents as $document) {
            // Check which types are missing for this document
            $existingTypes = StudyMaterial::where('document_id', $document->id)
                ->pluck('type')
                ->unique()
                ->toArray();

            Log::info($existingTypes);

            $missingTypes = array_diff($requestedTypes, $existingTypes);

            if (!empty($missingTypes)) {
                // Get the file path for processing
                $filePath = storage_path('app/uploads/' . $document->file_path);
                if (file_exists($filePath)) {
                    // Dispatch job to generate missing materials
                    \App\Jobs\GenerateMissingCardTypes::dispatch(
                        $document->id,
                        $missingTypes,
                        $document->language ?? 'en',
                        $document->difficulty ?? 'beginner',
                        $filePath,
                        $document->original_filename ?? 'document'
                    );
                    $generationStarted = true;
                }
            }
        }

        if ($generationStarted) {
            return response()->json([
                'success' => true,
                'message' => 'Material generation started. This may take a few minutes.',
                'status' => 'processing'
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'All requested material types already exist for this deck.',
                'status' => 'complete'
            ]);
        }
    }

    /**
     * Get a specific deck with its details
     *
     * @param Request $request
     * @param int $deckId
     * @return JsonResponse
     */
    public function show(Request $request, $deckId)
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

        // Get deck with materials count
        $deckWithCounts = Deck::where('id', $deckId)
            ->with(['studyMaterials'])
            ->first();

        $totalCards = 0;
        $materialTypes = [];
        $flashcardCount = 0;
        $quizCount = 0;
        $exerciseCount = 0;

        foreach ($deckWithCounts->studyMaterials as $material) {
            $content = $material->content ?? [];
            $materialTypes[] = $material->type;

            if ($material->type === 'flashcard') {
                // Handle both single flashcard and array of flashcards
                if (isset($content['question']) && isset($content['answer'])) {
                    // Single flashcard format
                    $flashcardCount++;
                } elseif (is_array($content)) {
                    // Array of flashcards
                    foreach ($content as $card) {
                        if (isset($card['question']) && isset($card['answer'])) {
                            $flashcardCount++;
                        }
                    }
                }
            } elseif ($material->type === 'quiz') {
                // Handle both single quiz and array of quizzes
                if (isset($content['question']) && isset($content['options'])) {
                    // Single quiz format
                    $quizCount++;
                } elseif (is_array($content)) {
                    // Array of quizzes
                    foreach ($content as $quiz) {
                        if (isset($quiz['question']) && isset($quiz['options'])) {
                            $quizCount++;
                        }
                    }
                }
            } elseif ($material->type === 'exercise') {
                // Handle both single exercise and array of exercises
                if (isset($content['type']) && isset($content['instruction'])) {
                    // Single exercise format
                    $exerciseCount++;
                } elseif (is_array($content)) {
                    // Array of exercises
                    foreach ($content as $exercise) {
                        if (isset($exercise['type']) && isset($exercise['instruction'])) {
                            $exerciseCount++;
                        }
                    }
                }
            }
        }

        $totalCards = $flashcardCount + $quizCount + $exerciseCount;

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $deck->id,
                'name' => $deck->name,
                'card_count' => $totalCards,
                'flashcard_count' => $flashcardCount,
                'quiz_count' => $quizCount,
                'exercise_count' => $exerciseCount,
                'material_types' => array_unique($materialTypes),
                'created_at' => $deck->created_at,
                'updated_at' => $deck->updated_at
            ]
        ]);
    }

    /**
     * Update deck details (e.g., name)
     *
     * @param Request $request
     * @param int $deckId
     * @return JsonResponse
     */
    public function update(Request $request, $deckId)
    {
        $supabaseUser = $request->get('supabase_user');
        if (!$supabaseUser || !isset($supabaseUser['id'])) {
            return response()->json(['error' => 'Supabase user not found'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Ensure deck belongs to user
        $deck = Deck::where('id', $deckId)->where('user_id', $supabaseUser['id'])->first();
        if (!$deck) {
            return response()->json(['error' => 'Deck not found or access denied'], 404);
        }

        try {
            $deck->name = $request->input('name');
            $deck->save();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $deck->id,
                    'name' => $deck->name,
                    'updated_at' => $deck->updated_at
                ],
                'message' => 'Deck updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update deck: ' . $e->getMessage()
            ], 500);
        }
    }
}
