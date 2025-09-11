<?php

namespace App\Http\Controllers\Api\V5;

use App\Http\Controllers\Controller;
use App\Http\Requests\V5\Context\SwitchSchoolRequest;
use App\Http\Requests\V5\Context\SwitchSeasonRequest;
use App\Models\School;
use App\V5\Models\UserSeasonRole;
use App\V5\Models\Season as V5Season;
use App\Models\Season as LegacySeason;
use App\Services\ContextService;
use App\Traits\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;

class ContextController extends Controller
{
    use ProblemDetails;

    public function __construct(private ContextService $contextService)
    {
    }

    /**
     * Get current context (school_id, season_id).
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $this->contextService->get($request->user())
        );
    }

    /**
     * Switch current school for the authenticated user.
     */
    public function switchSchool(SwitchSchoolRequest $request): JsonResponse
    {
        $user = $request->user();
        $schoolId = (int) $request->validated()['school_id'];

        try {
            $school = School::findOrFail($schoolId);
        } catch (ModelNotFoundException $e) {
            return $this->problem('School not found', Response::HTTP_NOT_FOUND);
        }

        try {
            $this->authorize('switch', $school);
        } catch (AuthorizationException $e) {
            return $this->problem($e->getMessage(), Response::HTTP_FORBIDDEN);
        }

        $context = $this->contextService->setSchool($user, $schoolId);

        return response()->json($context);
    }

    // Problem details generator provided by ProblemDetails trait.

    /**
     * Switch current season for the authenticated user.
     */
    public function switchSeason(SwitchSeasonRequest $request): JsonResponse
    {
        $user = $request->user();
        $seasonId = (int) $request->validated()['season_id'];

        // Resolve season from either V5 or legacy model
        $season = V5Season::find($seasonId) ?? LegacySeason::find($seasonId);
        if (! $season) {
            return $this->problem('Season not found', Response::HTTP_NOT_FOUND);
        }

        // Optional: if school context present, ensure season belongs to selected school
        $contextSchoolId = $request->get('context_school_id');
        if ($contextSchoolId && (int) $contextSchoolId !== (int) $season->school_id) {
            return $this->problem('Season does not belong to selected school', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Authorization: user must have access to the season (either superadmin or mapped via user_season_roles)
        $hasSeasonAccess = $user->hasRole('superadmin') ||
            UserSeasonRole::where('user_id', $user->id)->where('season_id', $season->id)->exists();

        if (! $hasSeasonAccess) {
            return $this->problem('Access denied to this season', Response::HTTP_FORBIDDEN);
        }

        $context = $this->contextService->setSeason($user, $seasonId);

        return response()->json($context);
    }
}
