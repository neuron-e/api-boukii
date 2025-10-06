<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\AppBaseController;
use App\Models\MonitorTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonitorTrainingAPIController extends AppBaseController
{
    /**
     * Get all monitor trainings
     */
    public function index(Request $request): JsonResponse
    {
        $query = MonitorTraining::with(['sport', 'school']);

        // Filter by monitor_id if provided
        if ($request->has('monitor_id')) {
            $query->where('monitor_id', $request->monitor_id);
        }

        // Filter by sport_id if provided
        if ($request->has('sport_id')) {
            $query->where('sport_id', $request->sport_id);
        }

        // Filter by school_id if provided
        if ($request->has('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        $trainings = $query->orderBy('created_at', 'desc')->get();

        return $this->sendResponse($trainings, 'Monitor Trainings retrieved successfully');
    }

    /**
     * Store a new monitor training
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'monitor_id' => 'required|integer|exists:monitors,id',
            'sport_id' => 'required|integer|exists:sports,id',
            'school_id' => 'required|integer|exists:schools,id',
            'training_name' => 'required|string|max:255',
            'training_proof' => 'nullable|string'
        ]);

        $training = MonitorTraining::create($validatedData);
        $training->load(['sport', 'school']);

        return $this->sendResponse($training, 'Monitor Training saved successfully');
    }

    /**
     * Display the specified monitor training
     */
    public function show($id): JsonResponse
    {
        $training = MonitorTraining::with(['sport', 'school'])->find($id);

        if (empty($training)) {
            return $this->sendError('Monitor Training not found');
        }

        return $this->sendResponse($training, 'Monitor Training retrieved successfully');
    }

    /**
     * Update the specified monitor training
     */
    public function update(Request $request, $id): JsonResponse
    {
        $training = MonitorTraining::find($id);

        if (empty($training)) {
            return $this->sendError('Monitor Training not found');
        }

        $validatedData = $request->validate([
            'training_name' => 'sometimes|required|string|max:255',
            'training_proof' => 'nullable|string'
        ]);

        $training->update($validatedData);
        $training->load(['sport', 'school']);

        return $this->sendResponse($training, 'Monitor Training updated successfully');
    }

    /**
     * Remove the specified monitor training
     */
    public function destroy($id): JsonResponse
    {
        $training = MonitorTraining::find($id);

        if (empty($training)) {
            return $this->sendError('Monitor Training not found');
        }

        $training->delete();

        return $this->sendSuccess('Monitor Training deleted successfully');
    }
}
