<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TemplateController extends Controller
{
    /**
     * Display a listing of the templates for the authenticated school.
     */
    public function index(Request $request)
    {
        try {
            $school = $request->user()->schools()->first();

            if (!$school) {
                return $this->sendError('User has no associated school', [], 403);
            }

            $templates = NewsletterTemplate::where('school_id', $school->id)
                                          ->orderBy('created_at', 'desc')
                                          ->get();

            return $this->sendResponse($templates, 'Templates retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving templates', $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created template in storage.
     */
    public function store(Request $request)
    {
        try {
            $school = $request->user()->schools()->first();

            if (!$school) {
                return $this->sendError('User has no associated school', [], 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error.', $validator->errors(), 422);
            }

            $template = NewsletterTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'subject' => $request->subject,
                'content' => $request->content,
                'school_id' => $school->id,
            ]);

            return $this->sendResponse($template, 'Template created successfully', 201);
        } catch (\Exception $e) {
            return $this->sendError('Error creating template', $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified template.
     */
    public function show(Request $request, $id)
    {
        try {
            $school = $request->user()->schools()->first();

            if (!$school) {
                return $this->sendError('User has no associated school', [], 403);
            }

            $template = NewsletterTemplate::where('id', $id)
                                         ->where('school_id', $school->id)
                                         ->first();

            if (!$template) {
                return $this->sendError('Template not found', [], 404);
            }

            return $this->sendResponse($template, 'Template retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving template', $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified template in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            $school = $request->user()->schools()->first();

            if (!$school) {
                return $this->sendError('User has no associated school', [], 403);
            }

            $template = NewsletterTemplate::where('id', $id)
                                         ->where('school_id', $school->id)
                                         ->first();

            if (!$template) {
                return $this->sendError('Template not found', [], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->sendError('Validation Error.', $validator->errors(), 422);
            }

            $template->update([
                'name' => $request->name,
                'description' => $request->description,
                'subject' => $request->subject,
                'content' => $request->content,
            ]);

            return $this->sendResponse($template, 'Template updated successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error updating template', $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified template from storage.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $school = $request->user()->schools()->first();

            if (!$school) {
                return $this->sendError('User has no associated school', [], 403);
            }

            $template = NewsletterTemplate::where('id', $id)
                                         ->where('school_id', $school->id)
                                         ->first();

            if (!$template) {
                return $this->sendError('Template not found', [], 404);
            }

            $template->delete();

            return $this->sendResponse([], 'Template deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error deleting template', $e->getMessage(), 500);
        }
    }

    /**
     * Send success response
     */
    public function sendResponse($result, $message, $code = 200)
    {
        $response = [
            'success' => true,
            'data'    => $result,
            'message' => $message,
        ];

        return response()->json($response, $code);
    }

    /**
     * Send error response
     */
    public function sendError($error, $errorMessages = [], $code = 404)
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['data'] = $errorMessages;
        }

        return response()->json($response, $code);
    }
}