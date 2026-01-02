<?php

namespace App\Http\Controllers\Teach;

use App\Http\Controllers\AppBaseController;
use App\Services\Auth\LoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Class UserController
 * @package App\Http\Controllers\API
 */

class AuthController extends AppBaseController
{
    protected LoginService $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->loginService = $loginService;
    }


    /**
     * @OA\Post(
     *      path="/teach/login",
     *      summary="Teach Login",
     *      tags={"Auth"},
     *      description="Login user",
     *     @OA\RequestBody(
     *         @OA\JsonContent(),
     *         @OA\MediaType(
     *            mediaType="multipart/form-data",
     *            @OA\Schema(
     *               type="object",
     *               required={"email", "password"},
     *               @OA\Property(property="email", type="email"),
     *               @OA\Property(property="password", type="password")
     *            ),
     *        ),
     *    ),
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
     *                  ref="#/components/schemas/User"
     *              ),
     *              @OA\Property(
     *                  property="message",
     *                  type="string"
     *              )
     *          )
     *      )
     * )
     */
    public function login(Request $request)
    {
        try {
            Log::channel('teach')->info('TEACH_LOGIN_ENDPOINT_HIT', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'has_email' => $request->has('email'),
                'has_password' => $request->has('password'),
            ]);

            $result = $this->loginService->authenticate($request, ['monitor', 3]);

            if (!$result) {
                Log::channel('teach')->warning('TEACH_LOGIN_UNAUTHORIZED', [
                    'ip' => $request->ip(),
                    'email' => $request->input('email'),
                ]);
                return $this->sendError('Unauthorized.', 401);
            }

            Log::channel('teach')->info('TEACH_LOGIN_SUCCESS_RESPONSE', [
                'user_id' => $result['user']->id ?? null,
                'has_token' => isset($result['token']),
            ]);

            return $this->sendResponse($result, 'User login successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('teach')->error('TEACH_LOGIN_VALIDATION_ERROR', [
                'ip' => $request->ip(),
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::channel('teach')->error('TEACH_LOGIN_EXCEPTION', [
                'ip' => $request->ip(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->sendError('Internal server error during login', 500);
        }
    }

    /**
     * Change password for authenticated monitor
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        // Verify current password
        if (!\Hash::check($request->current_password, $user->password)) {
            return $this->sendError('Current password is incorrect', 400);
        }

        // Update password
        $user->password = \Hash::make($request->new_password);
        $user->save();

        return $this->sendResponse([], 'Password changed successfully');
    }

}

