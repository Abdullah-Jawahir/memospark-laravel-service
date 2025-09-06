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
    public function handle(FileProcessCacheService $fileProcessCacheService): void
    {
        $document = Document::find($this->documentId);

        if (!$document) {
            Log::warning('ProcessDocument job: Document not found', ['document_id' => $this->documentId]);
            return;
        }

        // Check if document processing has been cancelled
        if ($document->status === 'cancelled') {
            Log::info('ProcessDocument job: Document processing was cancelled, aborting', [
                'document_id' => $this->documentId
            ]);
            return;
        }

        try {
            // Create a temporary file from storage
            $tempPath = tempnam(sys_get_temp_dir(), 'doc_');
            $fileContents = Storage::disk('private')->get($this->filePath);
            file_put_contents($tempPath, $fileContents);

            // Use the new caching strategy
            $result = $fileProcessCacheService->processAndCacheFile(
                $tempPath,
                $this->originalFilename,
                $this->language,
                $this->cardTypes,
                $this->difficulty,
                $this->documentId
            );

            // Check again if document was cancelled during processing
            $document->refresh();
            if ($document->status === 'cancelled') {
                Log::info('ProcessDocument job: Document was cancelled during processing, aborting', [
                    'document_id' => $this->documentId
                ]);
                // Clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
                return;
            }

            if ($result['status'] === 'done') {
                // Update document status and metadata
                $document->update([
                    'status' => 'completed',
                    'metadata' => array_merge($document->metadata, [
                        'processed_at' => now(),
                        'generated_content' => $result['result']['generated_content'] ?? $result['result'] ?? []
                    ])
                ]);

                // Save study materials for authenticated users only (if not already cached)
                if ($document->user_id && !empty($result['result'])) {
                    $this->saveStudyMaterials($document, $result['result']);
                }
            } else if ($result['status'] === 'failed') {
                $document->update([
                    'status' => 'failed',
                    'metadata' => array_merge($document->metadata, [
                        'error' => $result['message'] ?? 'Processing failed.'
                    ])
                ]);
                // Cleanup failed document and related records/files
                try {
                    // Replicate controller cleanup logic inline to avoid coupling
                    \App\Models\StudyMaterial::where('document_id', $document->id)->delete();
                    \App\Models\FileProcessCache::where('document_id', $document->id)->delete();
                    if ($document->guestUpload) {
                        $document->guestUpload()->delete();
                    }
                    if ($document->storage_path && Storage::disk('private')->exists($document->storage_path)) {
                        Storage::disk('private')->delete($document->storage_path);
                    }
                    try {
                        $document->forceDelete();
                    } catch (\Exception $e) {
                        $document->delete();
                    }
                    if ($document->deck_id) {
                        $deck = \App\Models\Deck::find($document->deck_id);
                        if ($deck) {
                            $remainingDocs = Document::where('deck_id', $deck->id)->count();
                            if ($remainingDocs === 0) {
                                $deck->delete();
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::channel('fastapi')->error('Cleanup during job failure encountered an error', [
                        'document_id' => $document->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
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

    /**
     * Save study materials to database
     */
    private function saveStudyMaterials(Document $document, array $result): void
    {
        $content = $result['generated_content'] ?? $result;

        Log::info('ProcessDocument: About to check study material creation', [
            'user_id' => $document->user_id,
            'result' => $result,
        ]);

        if (empty($content)) {
            Log::info('ProcessDocument: No content to save');
            return;
        }

        // Save flashcards
        if (!empty($content['flashcards'])) {
            foreach ($content['flashcards'] as $card) {
                Log::info('ProcessDocument: Creating StudyMaterial (flashcard)', [
                    'document_id' => $document->id,
                    'type' => 'flashcard',
                    'content' => $card,
                    'language' => $this->language,
                ]);
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
    }
}
