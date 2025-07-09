<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\FastApiService;
use App\Services\FileProcessCacheService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $documentId;
    protected $filePath;
    protected $originalFilename;
    protected $language;
    protected $cardTypes;
    protected $difficulty;

    /**
     * Create a new job instance.
     */
    public function __construct($documentId, $filePath, $originalFilename, $language, $cardTypes = ['flashcard'], $difficulty = 'beginner')
    {
        $this->documentId = $documentId;
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
        $this->language = $language;
        $this->cardTypes = $cardTypes;
        $this->difficulty = $difficulty;
    }

    /**
     * Execute the job.
     */
    public function handle(FastApiService $fastApiService, FileProcessCacheService $fileProcessCacheService): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            return;
        }

        try {
            // Create a temporary file from storage
            $tempPath = tempnam(sys_get_temp_dir(), 'doc_');
            $fileContents = Storage::disk('private')->get($this->filePath);
            file_put_contents($tempPath, $fileContents);

            $result = [];

            // Prepare cache keys
            $cardTypes = $this->cardTypes;
            sort($cardTypes);
            $cardTypesJson = json_encode($cardTypes);
            $cardTypesHash = hash('sha256', $cardTypesJson);
            $fileHash = hash_file('sha256', $tempPath);

            // Try to find or create the cache entry
            $cache = \App\Models\FileProcessCache::firstOrCreate([
                'file_hash' => $fileHash,
                'language' => $this->language,
                'difficulty' => $this->difficulty,
                'card_types_hash' => $cardTypesHash,
            ], [
                'card_types' => $cardTypes,
                'status' => 'processing',
            ]);

            // If already processed, use cached result
            if ($cache->status === 'done') {
                $result = $cache->result['generated_cards'] ?? $cache->result;
                $status = 'done';
            } else if ($cache->status === 'failed') {
                $document->update([
                    'status' => 'failed',
                    'metadata' => array_merge($document->metadata, [
                        'error' => 'Processing failed previously.'
                    ])
                ]);
                unlink($tempPath);
                return;
            } else {
                // Not processed yet, call FastAPI
                try {
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tempPath,
                        $this->originalFilename,
                        mime_content_type($tempPath),
                        null,
                        true
                    );
                    $result = $fastApiService->processFile($uploadedFile, $this->language, $cardTypes, $this->difficulty);
                    $cache->update([
                        'result' => $result,
                        'status' => 'done',
                    ]);
                    $status = 'done';
                } catch (\Exception $e) {
                    $cache->update(['status' => 'failed']);
                    $document->update([
                        'status' => 'failed',
                        'metadata' => array_merge($document->metadata, [
                            'error' => $e->getMessage()
                        ])
                    ]);
                    unlink($tempPath);
                    return;
                }
            }

            // Update document status and metadata
            if ($status === 'done') {
                $document->update([
                    'status' => 'completed',
                    'metadata' => array_merge($document->metadata, [
                        'processed_at' => now(),
                        'generated_content' => $result['generated_content'] ?? $result ?? []
                    ])
                ]);
            }

            // Save study materials for authenticated users only (new format)
            Log::info('ProcessDocument: About to check study material creation', [
                'user_id' => $document->user_id,
                'result' => $result,
            ]);
            if ($document->user_id && !empty($result)) {
                $content = $result['generated_content'] ?? [];
                Log::info('ProcessDocument: Dumping $content', [
                    'content' => $content,
                    'flashcards_type' => gettype($content['flashcards'] ?? null),
                    'flashcards_count' => isset($content['flashcards']) && is_array($content['flashcards']) ? count($content['flashcards']) : null,
                    'flashcards_keys' => isset($content['flashcards']) && is_array($content['flashcards']) ? array_keys($content['flashcards']) : null,
                ]);
                // Save flashcards
                if (!empty($content['flashcards'])) {
                    foreach ($content['flashcards'] as $i => $card) {
                        Log::info('ProcessDocument: Creating StudyMaterial (flashcard)', [
                            'document_id' => $document->id,
                            'type' => 'flashcard',
                            'content' => $card,
                            'language' => $this->language,
                        ]);
                        if ($i === 0) {
                            // Try a minimal insert for the first flashcard
                            try {
                                $minimalData = [
                                    'document_id' => $document->id,
                                    'type' => 'flashcard',
                                    'content' => [
                                        'question' => $card['question'] ?? '',
                                        'answer' => $card['answer'] ?? '',
                                    ],
                                    'language' => $this->language,
                                ];
                                Log::info('ProcessDocument: Minimal insert data', $minimalData);
                                $sm = \App\Models\StudyMaterial::create($minimalData);
                                Log::info('ProcessDocument: Minimal StudyMaterial created (flashcard)', [
                                    'study_material_id' => $sm->id,
                                ]);
                            } catch (\Exception $e) {
                                Log::error('ProcessDocument: Failed minimal insert StudyMaterial (flashcard)', [
                                    'error' => $e->getMessage(),
                                    'data' => $minimalData,
                                ]);
                            }
                        }
                        try {
                            $sm = \App\Models\StudyMaterial::create([
                                'document_id' => $document->id,
                                'type' => 'flashcard',
                                'content' => $card,
                                'language' => $this->language,
                            ]);
                            Log::info('ProcessDocument: StudyMaterial created (flashcard)', [
                                'study_material_id' => $sm->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('ProcessDocument: Failed to create StudyMaterial (flashcard)', [
                                'error' => $e->getMessage(),
                                'data' => [
                                    'document_id' => $document->id,
                                    'type' => 'flashcard',
                                    'content' => $card,
                                    'language' => $this->language,
                                ]
                            ]);
                        }
                    }
                }
                // Save quizzes
                if (!empty($content['quizzes'])) {
                    foreach ($content['quizzes'] as $quiz) {
                        Log::info('ProcessDocument: Creating StudyMaterial (quiz)', [
                            'document_id' => $document->id,
                            'type' => 'quiz',
                            'content' => $quiz,
                            'language' => $this->language,
                        ]);
                        try {
                            $sm = \App\Models\StudyMaterial::create([
                                'document_id' => $document->id,
                                'type' => 'quiz',
                                'content' => $quiz,
                                'language' => $this->language,
                            ]);
                            Log::info('ProcessDocument: StudyMaterial created (quiz)', [
                                'study_material_id' => $sm->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('ProcessDocument: Failed to create StudyMaterial (quiz)', [
                                'error' => $e->getMessage(),
                                'data' => [
                                    'document_id' => $document->id,
                                    'type' => 'quiz',
                                    'content' => $quiz,
                                    'language' => $this->language,
                                ]
                            ]);
                        }
                    }
                }
                // Save exercises
                if (!empty($content['exercises'])) {
                    foreach ($content['exercises'] as $exercise) {
                        Log::info('ProcessDocument: Creating StudyMaterial (exercise)', [
                            'document_id' => $document->id,
                            'type' => 'exercise',
                            'content' => $exercise,
                            'language' => $this->language,
                        ]);
                        try {
                            $sm = \App\Models\StudyMaterial::create([
                                'document_id' => $document->id,
                                'type' => 'exercise',
                                'content' => $exercise,
                                'language' => $this->language,
                            ]);
                            Log::info('ProcessDocument: StudyMaterial created (exercise)', [
                                'study_material_id' => $sm->id,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('ProcessDocument: Failed to create StudyMaterial (exercise)', [
                                'error' => $e->getMessage(),
                                'data' => [
                                    'document_id' => $document->id,
                                    'type' => 'exercise',
                                    'content' => $exercise,
                                    'language' => $this->language,
                                ]
                            ]);
                        }
                    }
                }
            } else {
                Log::info('ProcessDocument: No study materials created', [
                    'user_id' => $document->user_id,
                    'result' => $result,
                ]);
            }

            // Clean up temporary file
            unlink($tempPath);
        } catch (\Exception $e) {
            Log::channel('fastapi')->error('ProcessDocument job failed', [
                'document_id' => $this->documentId,
                'file_path' => $this->filePath,
                'original_filename' => $this->originalFilename,
                'language' => $this->language,
                'card_types' => $this->cardTypes,
                'difficulty' => $this->difficulty,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $document->update([
                'status' => 'failed',
                'metadata' => array_merge($document->metadata, [
                    'error' => $e->getMessage()
                ])
            ]);
        }
    }
}
