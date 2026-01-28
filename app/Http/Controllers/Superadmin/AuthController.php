<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Services\Auth\LoginService;
use Illuminate\Http\Request;

class AuthController extends AppBaseController
{
    protected LoginService $loginService;

    public function __construct(LoginService $loginService)
    {
        $this->loginService = $loginService;
    }

    public function login(Request $request)
    {
        $result = $this->loginService->authenticate($request, ['superadmin', 4]);

        if (!$result) {
            return $this->sendError('Unauthorized', 401);
        }

        return $this->sendResponse($result, 'Superadmin login successful.');
    }
}
