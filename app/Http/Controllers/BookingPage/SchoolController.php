<?php

namespace App\Http\Controllers\BookingPage;

use App\Http\Controllers\AppBaseController;
use App\Models\Course;
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
            $school->load('sports');

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



}
