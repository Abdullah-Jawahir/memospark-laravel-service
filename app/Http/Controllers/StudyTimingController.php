<?php

namespace App\Http\Controllers;

use App\Models\StudyActivityTiming;
use App\Models\StudySessionTiming;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudyTimingController extends Controller
{
  /**
   * Start a new activity timing record
   */
  public function startActivity(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'session_id' => 'required|string',
        'activity_type' => 'required|in:flashcard,quiz,exercise',
        'activity_details' => 'nullable|array',
        'start_time' => 'required|date',
      ]);

      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'message' => 'Validation failed',
          'errors' => $validator->errors()
        ], 400);
      }

      $timing = StudyActivityTiming::create([
        'session_id' => $request->session_id,
        'activity_type' => $request->activity_type,
        'start_time' => $request->start_time,
        'duration_seconds' => 0, // Will be updated when ended
        'activity_details' => $request->activity_details ?? [],
      ]);

      return response()->json([
        'success' => true,
        'data' => ['timing_id' => $timing->id]
      ]);
    } catch (\Exception $e) {
      Log::error('Failed to start activity timing: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to start activity timing'
      ], 500);
    }
  }

  /**
   * End an activity timing record
   */
  public function endActivity(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'timing_id' => 'required|integer|exists:study_activity_timings,id',
        'end_time' => 'required|date',
        'duration_seconds' => 'required|integer|min:0',
      ]);

      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'message' => 'Validation failed',
          'errors' => $validator->errors()
        ], 400);
      }

      $timing = StudyActivityTiming::findOrFail($request->timing_id);
      $timing->update([
        'end_time' => $request->end_time,
        'duration_seconds' => $request->duration_seconds,
      ]);

      return response()->json(['success' => true]);
    } catch (\Exception $e) {
      Log::error('Failed to end activity timing: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to end activity timing'
      ], 500);
    }
  }

  /**
   * Record a complete activity timing (for quick activities)
   */
  public function recordActivity(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'session_id' => 'required|string',
        'activity_type' => 'required|in:flashcard,quiz,exercise',
        'duration_seconds' => 'required|integer|min:0',
        'activity_details' => 'nullable|array',
        'recorded_at' => 'required|date',
      ]);

      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'message' => 'Validation failed',
          'errors' => $validator->errors()
        ], 400);
      }

      StudyActivityTiming::create([
        'session_id' => $request->session_id,
        'activity_type' => $request->activity_type,
        'start_time' => $request->recorded_at,
        'end_time' => $request->recorded_at,
        'duration_seconds' => $request->duration_seconds,
        'activity_details' => $request->activity_details ?? [],
      ]);

      return response()->json(['success' => true]);
    } catch (\Exception $e) {
      Log::error('Failed to record activity timing: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to record activity timing'
      ], 500);
    }
  }

  /**
   * Get study timing summary for a session
   */
  public function getTimingSummary($sessionId)
  {
    try {
      $sessionTiming = StudySessionTiming::where('session_id', $sessionId)->first();
      $activities = StudyActivityTiming::where('session_id', $sessionId)
        ->orderBy('created_at')
        ->get();

      if (!$sessionTiming) {
        // Create a session timing record if it doesn't exist
        $sessionTiming = StudySessionTiming::create([
          'session_id' => $sessionId,
          'session_start' => now(),
        ]);
      }

      // Calculate totals from activities
      $flashcardTime = $activities->where('activity_type', 'flashcard')->sum('duration_seconds');
      $quizTime = $activities->where('activity_type', 'quiz')->sum('duration_seconds');
      $exerciseTime = $activities->where('activity_type', 'exercise')->sum('duration_seconds');
      $totalTime = $flashcardTime + $quizTime + $exerciseTime;

      return response()->json([
        'success' => true,
        'data' => [
          'session_id' => $sessionId,
          'total_study_time' => $totalTime,
          'flashcard_time' => $flashcardTime,
          'quiz_time' => $quizTime,
          'exercise_time' => $exerciseTime,
          'activities' => $activities->map(function ($activity) {
            return [
              'id' => $activity->id,
              'activity_type' => $activity->activity_type,
              'start_time' => $activity->start_time,
              'end_time' => $activity->end_time,
              'duration_seconds' => $activity->duration_seconds,
              'activity_details' => $activity->activity_details,
            ];
          })
        ]
      ]);
    } catch (\Exception $e) {
      Log::error('Failed to get timing summary: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to get timing summary'
      ], 500);
    }
  }

  /**
   * Update session timing totals
   */
  public function updateSessionTiming(Request $request)
  {
    try {
      $validator = Validator::make($request->all(), [
        'session_id' => 'required|string',
        'total_study_time' => 'required|integer|min:0',
        'flashcard_time' => 'required|integer|min:0',
        'quiz_time' => 'required|integer|min:0',
        'exercise_time' => 'required|integer|min:0',
        'updated_at' => 'required|date',
      ]);

      if ($validator->fails()) {
        return response()->json([
          'success' => false,
          'message' => 'Validation failed',
          'errors' => $validator->errors()
        ], 400);
      }

      StudySessionTiming::updateOrCreate(
        ['session_id' => $request->session_id],
        [
          'total_study_time' => $request->total_study_time,
          'flashcard_time' => $request->flashcard_time,
          'quiz_time' => $request->quiz_time,
          'exercise_time' => $request->exercise_time,
          'session_start' => $request->session_start ?? now(),
        ]
      );

      return response()->json(['success' => true]);
    } catch (\Exception $e) {
      Log::error('Failed to update session timing: ' . $e->getMessage());
      return response()->json([
        'success' => false,
        'message' => 'Failed to update session timing'
      ], 500);
    }
  }
}
