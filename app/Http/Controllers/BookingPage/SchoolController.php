<?php

namespace App\Http\Controllers\BookingPage;

use App\Http\Controllers\AppBaseController;
use App\Models\Course;
use App\Models\Degree;
use App\Models\Sport;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Response;
use Validator;

;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */

class SchoolController extends SlugAuthController
{

    /**
     * @OA\Get(
     *      path="/slug/school",
     *      summary="getSchooldata",
     *      tags={"BookingPage"},
     *      description="Get school data by slug",
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/School")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $school = $this->school;

            // Load sports with explicit deduplication to prevent duplicates
            $uniqueSports = Sport::whereHas('schools', function($query) use ($school) {
                $query->where('school_id', $school->id);
            })->distinct()->get();

            $school->setRelation('sports', $uniqueSports);

            // Ensure booking.social keys exist in settings for consumer convenience
            try {
                $settingsStr = $school->settings;
                $settings = is_string($settingsStr) ? json_decode($settingsStr, true) : $settingsStr;
                if (is_array($settings)) {
                    if (!isset($settings['booking']) || !is_array($settings['booking'])) {
                        $settings['booking'] = [];
                    }
                    if (!isset($settings['booking']['social']) || !is_array($settings['booking']['social'])) {
                        $settings['booking']['social'] = [];
                    }
                    foreach (['facebook','instagram','x','youtube','tiktok','linkedin'] as $key) {
                        if (!array_key_exists($key, $settings['booking']['social'])) {
                            $settings['booking']['social'][$key] = null;
                        }
                    }
                    $school->settings = json_encode($settings);
                }
            } catch (\Throwable $e) {
                // noop
            }

            return $this->sendResponse($school, 'School retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * @OA\Get(
     *      path="/slug/degrees",
     *      summary="getSchoolDegrees",
     *      tags={"BookingPage"},
     *      description="Get degrees available in school for a specific sport",
     *      @OA\Parameter(
     *          name="sport_id",
     *          description="ID of the sport",
     *          required=true,
     *          in="query",
     *          @OA\Schema(type="integer")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                  property="success",
     *                  type="boolean"
     *              ),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  @OA\Items(ref="#/components/schemas/Degree")
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function getDegrees(Request $request): JsonResponse
    {
        try {
            $sportId = $request->get('sport_id');

            if (!$sportId) {
                return $this->sendError('sport_id is required', 400);
            }

            // Get degrees available in this school for the specified sport
            $degrees = Degree::where('school_id', $this->school->id)
                            ->where('sport_id', $sportId)
                            ->where('active', 1)
                            ->orderBy('degree_order', 'asc')
                            ->get();

            return $this->sendResponse($degrees, 'Degrees retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError($e->getMessage(), 500);
        }
    }

}