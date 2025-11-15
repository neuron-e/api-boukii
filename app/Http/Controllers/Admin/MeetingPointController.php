<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MeetingPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class MeetingPointController extends Controller
{
    /**
     * Display a listing of meeting points for the authenticated user's school
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $school = $user->schools()->first();

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }

        $query = MeetingPoint::forSchool($school->id);

        // Filter by active status if requested
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Search by name
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $meetingPoints = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $meetingPoints
        ]);
    }

    /**
     * Store a newly created meeting point
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $school = $user->schools()->first();

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'instructions' => 'nullable|string',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $meetingPoint = MeetingPoint::create([
            'school_id' => $school->id,
            'name' => $request->input('name'),
            'address' => $request->input('address'),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'instructions' => $request->input('instructions'),
            'active' => $request->input('active', true)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meeting point created successfully',
            'data' => $meetingPoint
        ], 201);
    }

    /**
     * Display the specified meeting point
     */
    public function show($id)
    {
        $user = Auth::user();
        $school = $user->schools()->first();

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }

        $meetingPoint = MeetingPoint::forSchool($school->id)->find($id);

        if (!$meetingPoint) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting point not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $meetingPoint
        ]);
    }

    /**
     * Update the specified meeting point
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $school = $user->schools()->first();

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }

        $meetingPoint = MeetingPoint::forSchool($school->id)->find($id);

        if (!$meetingPoint) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting point not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'instructions' => 'nullable|string',
            'active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $meetingPoint->update($request->only([
            'name',
            'address',
            'latitude',
            'longitude',
            'instructions',
            'active'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Meeting point updated successfully',
            'data' => $meetingPoint
        ]);
    }

    /**
     * Remove the specified meeting point
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $school = $user->schools()->first();

        if (!$school) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }

        $meetingPoint = MeetingPoint::forSchool($school->id)->find($id);

        if (!$meetingPoint) {
            return response()->json([
                'success' => false,
                'message' => 'Meeting point not found'
            ], 404);
        }

        $meetingPoint->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meeting point deleted successfully'
        ]);
    }
}
