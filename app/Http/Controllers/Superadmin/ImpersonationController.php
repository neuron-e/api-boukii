<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\AppBaseController;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends AppBaseController
{
    public function impersonate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => ['required', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $school = School::find($data['school_id']);
        if (!$school) {
            return $this->sendError('School not found', 404);
        }

        $query = User::where('type', 1)->whereHas('schoolUsers', function ($q) use ($school) {
            $q->where('school_id', $school->id);
        });

        if (!empty($data['user_id'])) {
            $query->where('id', $data['user_id']);
        }

        $user = $query->first();
        if (!$user) {
            return $this->sendError('Admin user not found', 404);
        }

        $token = $user->createToken('Boukii', ['admin:all'])->plainTextToken;

        return $this->sendResponse([
            'user' => $user,
            'token' => $token
        ], 'Impersonation token generated');
    }
}
