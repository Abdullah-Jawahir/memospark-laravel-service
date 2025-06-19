<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\GuestUpload;
use App\Services\FastApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
  protected $fastApiService;

  public function __construct(FastApiService $fastApiService)
  {
    $this->fastApiService = $fastApiService;
  }

  public function upload(Request $request)
  {
    $request->validate([
      'file' => 'required|file|mimes:pdf,docx,pptx,jpg,jpeg,png|max:10240', // 10MB max
      'language' => 'required|in:en,si,ta',
      'is_guest' => 'required|in:0,1,true,false'
    ]);

    $file = $request->file('file');
    $originalFilename = $file->getClientOriginalName();
    $extension = $file->getClientOriginalExtension();

    // Generate a unique filename
    $filename = Str::uuid() . '.' . $extension;

    // Store the file
    $path = $file->storeAs('documents', $filename, 'private');

    // Convert is_guest to boolean
    $isGuest = filter_var($request->is_guest, FILTER_VALIDATE_BOOLEAN);

    // Get user ID from Supabase if authenticated
    $userId = null;
    if (!$isGuest && $request->has('supabase_user')) {
      $userId = $request->supabase_user['id'];
    }

    // Create document record
    $document = Document::create([
      'user_id' => $userId,
      'original_filename' => $originalFilename,
      'storage_path' => $path,
      'file_type' => $extension,
      'language' => $request->language,
      'status' => 'processing',
      'metadata' => [
        'size' => $file->getSize(),
        'mime_type' => $file->getMimeType(),
        'is_guest' => $isGuest
      ]
    ]);

    // If this is a guest upload, track it
    if ($isGuest && $request->has('guest_identifier')) {
      GuestUpload::createGuestUpload(
        $request->guest_identifier,
        $document->id,
        'ip'
      );
    }

    // Process the document asynchronously
    ProcessDocument::dispatch(
      $document->id,
      $path,
      $originalFilename,
      $request->language
    );

    return response()->json([
      'message' => 'File uploaded successfully',
      'document_id' => $document->id,
      'is_guest' => $isGuest
    ]);
  }

  public function status($id)
  {
    $document = Document::findOrFail($id);

    return response()->json([
      'status' => $document->status,
      'metadata' => $document->metadata
    ]);
  }
}
