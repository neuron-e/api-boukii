<?php

namespace App\Http\Controllers;

use App\Http\Utils\ResponseUtil;

/**
 * @OA\Server(url="/api")
 * @OA\Info(
 *   title="Boukii API",
 *   version="1.0.0"
 * ),
 * @SWG\SecurityScheme(
 *      securityScheme="bearer_token",   // you can name it whatever you want, but not forget to use the same in your request
 *      type="http",
 *      scheme="bearer"
 *      )
 * This class should be parent class for other API controllers
 * Class AppBaseController
 */
class AppBaseController extends Controller
{
    public function sendResponse($result, $message, $code = 200)
    {
        return response()->json(ResponseUtil::makeResponse($message, $result), $code);
    }

    public function sendError($error, $code = 404)
    {
        return response()->json(ResponseUtil::makeError($error), $code);
    }

    public function sendSuccess($message)
    {
        return response()->json([
            'success' => true,
            'message' => $message
        ], 200);
    }

    public function sendSuccessWithErrors($message, $errors, $data): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => $errors
        ], 200);
    }

    public function getMonitor($request) {
        $user = $request->user();
        $user->load('monitors');

        if (!$user->monitors || $user->monitors->isEmpty()) {
            return null;
        }

        return $user->monitors->first();
    }

    public function getSchool($request) {
        $user = $request->user();
        $user->load('schools');

        if (!$user->schools || $user->schools->isEmpty()) {
            return null;
        }

        return $user->schools->first();
    }

    public function ensureSchoolInRequest($request): void
    {
        if (!$request->has('school_id')) {
            $school = $this->getSchool($request);
            if ($school) {
                $request->merge(['school_id' => $school->id]);
            }
        }
    }
}
