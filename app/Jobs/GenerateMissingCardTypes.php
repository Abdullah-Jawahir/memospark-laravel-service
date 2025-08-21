<?php

namespace App\Jobs;

use App\Models\Document;
use App\Models\StudyMaterial;
use App\Models\FileProcessCache;
use App\Services\FastApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class GenerateMissingCardTypes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $documentId;
    protected $missingTypes;
    protected $language;
    protected $difficulty;
    protected $filePath;
    protected $originalFilename;

    /**
     * Create a new job instance.
     */
    public function __construct($documentId, $missingTypes, $language, $difficulty, $filePath, $originalFilename)
    {
        $this->documentId = $documentId;
        $this->missingTypes = $missingTypes;
        $this->language = $language;
        $this->difficulty = $difficulty;
        $this->filePath = $filePath;
        $this->originalFilename = $originalFilename;
    }

    /**
     * Execute the job.
     */
    public function handle(FastApiService $fastApiService)
    {
        $document = Document::find($this->documentId);
        if (!$document || empty($this->missingTypes)) {
            return;
        }
        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $this->filePath,
            $this->originalFilename,
            mime_content_type($this->filePath),
            null,
            true
        );
        $fastApiResult = $fastApiService->processFile($uploadedFile, $this->language, $this->missingTypes, $this->difficulty);
        $generated = $fastApiResult['generated_content'] ?? $fastApiResult['generated_cards'] ?? $fastApiResult;
        // Save new study materials
        foreach ($this->missingTypes as $type) {
            if (!empty($generated[$type])) {
                foreach ($generated[$type] as $card) {
                    StudyMaterial::create([
                        'document_id' => $document->id,
                        'type' => $type,
                        'content' => $card,
                        'language' => $this->language,
                    ]);
                }
            }
        }
        // Update the cache result (merge new types)
        $cache = FileProcessCache::where('document_id', $document->id)->first();
        if ($cache) {
            $cacheResult = $cache->result ?? [];
            $cacheResult['generated_content'] = array_merge($cacheResult['generated_content'] ?? [], $generated);
            $cacheResult['generated_cards'] = array_merge($cacheResult['generated_cards'] ?? [], $generated);
            $cache->update(['result' => $cacheResult]);
        }
    }
}
