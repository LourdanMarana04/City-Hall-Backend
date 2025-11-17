<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class SeniorCitizenController extends Controller
{
    /**
     * Verify senior citizen status by comparing birth dates
     */
    public function verifySeniorCitizen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB max
            'user_birth_date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Store the uploaded image
            $imagePath = $request->file('id_image')->store('senior_citizen_ids', 'public');

            // Extract birth date from image using OCR
            $extractedBirthDate = $this->extractBirthDateFromImage($imagePath);

            if (!$extractedBirthDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Could not extract birth date from the uploaded ID. Please ensure the image is clear and the birth date is visible.'
                ], 400);
            }

            // Compare the extracted birth date with user's entered birth date
            $userBirthDate = Carbon::parse($request->user_birth_date);
            $extractedDate = Carbon::parse($extractedBirthDate);

            // Allow for a small tolerance (1 day) in case of OCR errors
            $dateDifference = abs($userBirthDate->diffInDays($extractedDate));

            if ($dateDifference <= 1) {
                // Calculate age to confirm senior citizen status
                $age = $userBirthDate->age;

                if ($age >= 60) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Senior citizen verification successful!',
                        'verified' => true,
                        'age' => $age,
                        'extracted_birth_date' => $extractedBirthDate,
                        'user_birth_date' => $request->user_birth_date
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Age verification failed. You must be 60 years or older to be considered a senior citizen.',
                        'verified' => false,
                        'age' => $age
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Birth date mismatch. The birth date on your ID does not match the one you entered.',
                    'verified' => false,
                    'extracted_birth_date' => $extractedBirthDate,
                    'user_birth_date' => $request->user_birth_date
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred during verification. Please try again.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract birth date from image using OCR
     * This is a simplified implementation. In production, you would use a proper OCR service
     */
    private function extractBirthDateFromImage($imagePath)
    {
        // For now, we'll implement a basic OCR simulation
        // In a real implementation, you would use services like:
        // - Google Cloud Vision API
        // - AWS Textract
        // - Azure Computer Vision
        // - Tesseract OCR

        try {
            // Simulate OCR processing delay
            sleep(1);

            // This is a placeholder implementation
            // In production, you would:
            // 1. Call the OCR service with the image
            // 2. Extract text from the response
            // 3. Use regex patterns to find birth date

            // For demonstration, we'll return a simulated extracted date
            // In reality, this would be the actual extracted date from the image

            // Common date patterns to look for in IDs:
            $datePatterns = [
                '/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/', // MM/DD/YYYY or DD/MM/YYYY
                '/\b(\d{1,2})-(\d{1,2})-(\d{4})\b/', // MM-DD-YYYY or DD-MM-YYYY
                '/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/', // YYYY-MM-DD
                '/\b(\d{1,2})\s+(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+(\d{4})\b/i', // DD Month YYYY
            ];

            // For now, return null to simulate OCR failure
            // This would be replaced with actual OCR processing
            return null;

        } catch (\Exception $e) {
            \Log::error('OCR processing failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get senior citizen statistics
     */
    public function getSeniorCitizenStats()
    {
        try {
            $today = Carbon::today();

            $stats = [
                'total_senior_citizens_today' => \DB::table('queue_numbers')
                    ->where('senior_citizen', true)
                    ->whereDate('created_at', $today)
                    ->count(),

                'senior_citizens_waiting' => \DB::table('queue_numbers')
                    ->where('senior_citizen', true)
                    ->where('status', 'waiting')
                    ->whereDate('created_at', $today)
                    ->count(),

                'senior_citizens_completed' => \DB::table('queue_numbers')
                    ->where('senior_citizen', true)
                    ->where('status', 'completed')
                    ->whereDate('created_at', $today)
                    ->count(),

                'percentage_senior_citizens' => 0
            ];

            // Calculate percentage
            $totalToday = \DB::table('queue_numbers')
                ->whereDate('created_at', $today)
                ->count();

            if ($totalToday > 0) {
                $stats['percentage_senior_citizens'] = round(($stats['total_senior_citizens_today'] / $totalToday) * 100, 2);
            }

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve senior citizen statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
