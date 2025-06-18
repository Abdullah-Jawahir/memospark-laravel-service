<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\FastApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $documentId;
    protected $filePath;
    protected $originalFilename;
    protected $language;

    /**
     * Create a new job instance.
     */
    public function __construct($documentId, $filePath, $originalFilename, $language)
    {
        $this->documentId = $documentId;
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
        $this->language = $language;
    }

    /**
     * Execute the job.
     */
    public function handle(FastApiService $fastApiService): void
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

            // Create a mock UploadedFile for the FastAPI service
            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $this->originalFilename,
                mime_content_type($tempPath),
                null,
                true
            );

            // Process the document using FastAPI service
            $result = $fastApiService->processFile($uploadedFile, $this->language);

            $document->update([
                'status' => 'completed',
                'metadata' => array_merge($document->metadata, [
                    'processed_at' => now(),
                    'generated_cards' => $result['generated_cards'] ?? []
                ])
            ]);

            // Clean up temporary file
            unlink($tempPath);
        } catch (\Exception $e) {
            $document->update([
                'status' => 'failed',
                'metadata' => array_merge($document->metadata, [
                    'error' => $e->getMessage()
                ])
            ]);
        }
    }
}
