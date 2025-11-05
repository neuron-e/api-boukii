<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Models\Newsletter;
use App\Models\Client;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Mail\NewsletterMailer;

class NewsletterController extends AppBaseController
{
    /**
     * Test endpoint to verify functionality
     */
    public function test(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Newsletter controller is working!',
            'timestamp' => now()
        ]);
    }

    /**
     * Get newsletter statistics for dashboard
     */
    public function stats(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);

        // Total subscribers (clients that accept newsletter per school)
        // IMPORTANT: Use the clients_schools pivot table, not the clients table directly
        $totalSubscribers = Client::query()
            ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
            ->where('clients_schools.school_id', $school->id)
            ->where('clients_schools.accepts_newsletter', true)
            ->distinct('clients.id')
            ->count('clients.id');

        // Total sent newsletters
        $totalSent = Newsletter::forSchool($school->id)
            ->where('status', 'sent')
            ->count();

        // Average open rate from all sent newsletters
        $averageOpenRate = Newsletter::forSchool($school->id)
            ->where('status', 'sent')
            ->where('sent_count', '>', 0)
            ->selectRaw('AVG(opened_count / sent_count * 100) as avg_rate')
            ->value('avg_rate') ?? 0;

        $stats = [
            'total_subscribers' => $totalSubscribers,
            'total_sent' => $totalSent,
            'open_rate' => round($averageOpenRate, 1),
        ];

        return $this->sendResponse($stats, 'Newsletter stats retrieved successfully');
    }

    /**
     * Get recent newsletters
     */
    public function recent(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);

        $newsletters = Newsletter::forSchool($school->id)
            ->with('user:id,first_name,last_name')
            ->recent(10)
            ->get()
            ->map(function ($newsletter) {
                return [
                    'id' => $newsletter->id,
                    'subject' => $newsletter->subject,
                    'content' => $newsletter->content,
                    'recipients' => $newsletter->total_recipients,
                    'sent_date' => $newsletter->sent_at,
                    'status' => $newsletter->status,
                    'open_rate' => $newsletter->open_rate,
                    'created_by' => $newsletter->user->first_name . ' ' . $newsletter->user->last_name,
                ];
            });

        return $this->sendResponse($newsletters, 'Recent newsletters retrieved successfully');
    }

    /**
     * Get all newsletters with pagination
     */
    public function index(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);
        
        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $status = $request->get('status');

        $query = Newsletter::forSchool($school->id)->with('user:id,first_name,last_name');

        if ($status) {
            $query->byStatus($status);
        }

        $newsletters = $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->sendResponse($newsletters, 'Newsletters retrieved successfully');
    }

    /**
     * Store a new newsletter
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'recipients' => 'required|array|min:1',
            'template_type' => 'nullable|string|in:welcome,promotion,newsletter,event',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $school = $this->getSchool($request);
        $user = $request->user();

        // Calculate total recipients
        $totalRecipients = $this->calculateRecipients($school->id, $request->recipients);

        $newsletter = Newsletter::create([
            'school_id' => $school->id,
            'user_id' => $user->id,
            'subject' => $request->subject,
            'content' => $request->content,
            'recipients_config' => $request->recipients,
            'total_recipients' => $totalRecipients,
            'template_type' => $request->template_type,
            'scheduled_at' => $request->scheduled_at,
            'status' => $request->scheduled_at ? 'scheduled' : 'draft',
            'metadata' => $request->metadata ?? [],
        ]);

        return $this->sendResponse($newsletter, 'Newsletter created successfully', 201);
    }

    /**
     * Show a specific newsletter
     */
    public function show(Request $request, $id): JsonResponse
    {
        $school = $this->getSchool($request);

        $newsletter = Newsletter::forSchool($school->id)
            ->with('user:id,first_name,last_name')
            ->find($id);

        if (!$newsletter) {
            return $this->sendError('Newsletter not found', [], 404);
        }

        return $this->sendResponse($newsletter, 'Newsletter retrieved successfully');
    }

    /**
     * Update a newsletter
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'recipients' => 'required|array|min:1',
            'template_type' => 'nullable|string|in:welcome,promotion,newsletter,event',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $school = $this->getSchool($request);

        $newsletter = Newsletter::forSchool($school->id)->find($id);

        if (!$newsletter) {
            return $this->sendError('Newsletter not found', [], 404);
        }

        // Only allow updates on drafts and scheduled newsletters
        if (!in_array($newsletter->status, ['draft', 'scheduled'])) {
            return $this->sendError('Cannot update a newsletter that has been sent', [], 400);
        }

        // Recalculate recipients
        $totalRecipients = $this->calculateRecipients($school->id, $request->recipients);

        $newsletter->update([
            'subject' => $request->subject,
            'content' => $request->content,
            'recipients_config' => $request->recipients,
            'total_recipients' => $totalRecipients,
            'template_type' => $request->template_type,
            'scheduled_at' => $request->scheduled_at,
            'status' => $request->scheduled_at ? 'scheduled' : 'draft',
            'metadata' => $request->metadata ?? $newsletter->metadata,
        ]);

        return $this->sendResponse($newsletter, 'Newsletter updated successfully');
    }

    /**
     * Delete a newsletter
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $school = $this->getSchool($request);

        $newsletter = Newsletter::forSchool($school->id)->find($id);

        if (!$newsletter) {
            return $this->sendError('Newsletter not found', [], 404);
        }

        // Only allow deletion of drafts
        if ($newsletter->status !== 'draft') {
            return $this->sendError('Cannot delete a newsletter that is not a draft', [], 400);
        }

        $newsletter->delete();

        return $this->sendResponse(null, 'Newsletter deleted successfully');
    }

    /**
     * Send newsletter immediately
     */
    public function send(Request $request, $id): JsonResponse
    {
        $school = $this->getSchool($request);

        $newsletter = Newsletter::forSchool($school->id)->find($id);

        if (!$newsletter) {
            return $this->sendError('Newsletter not found', [], 404);
        }

        if (!in_array($newsletter->status, ['draft', 'scheduled'])) {
            return $this->sendError('Newsletter cannot be sent', [], 400);
        }

        try {
            // Update status to sending
            $newsletter->update(['status' => 'sending']);

            // Get recipients
            $recipients = $this->getRecipients($school->id, $newsletter->recipients_config);

            // Send emails
            $sentCount = $this->sendNewsletterEmails($newsletter, $recipients);

            // Update newsletter with results
            $newsletter->update([
                'status' => 'sent',
                'sent_at' => now(),
                'sent_count' => $sentCount,
                'delivered_count' => $sentCount, // Assume all delivered for now
            ]);

            return $this->sendResponse($newsletter, 'Newsletter sent successfully');

        } catch (\Exception $e) {
            Log::error('Newsletter sending failed: ' . $e->getMessage());
            
            $newsletter->update(['status' => 'failed']);

            return $this->sendError('Failed to send newsletter', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get subscriber count for specific recipient configuration
     */
    public function subscriberCount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipients' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error', $validator->errors(), 400);
        }

        $school = $this->getSchool($request);

        // Get detailed count by type
        $counts = $this->getDetailedRecipientCount($school->id, $request->recipients);

        return $this->sendResponse($counts, 'Subscriber count calculated');
    }

    /**
     * Get detailed recipient count by type
     */
    private function getDetailedRecipientCount($schoolId, $recipientConfig): array
    {
        $counts = [
            'total' => 0,
            'by_type' => []
        ];

        $addedEmails = []; // Track unique emails

        foreach ($recipientConfig as $type) {
            $query = Client::query()
                ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
                ->where('clients_schools.school_id', $schoolId)
                ->where('clients_schools.accepts_newsletter', true);

            switch ($type) {
                case 'all':
                    // No additional filtering
                    break;
                case 'active':
                    $query->whereHas('bookingUsers', function ($q) {
                        $q->where('created_at', '>=', Carbon::now()->subMonths(6));
                    });
                    break;
                case 'inactive':
                    $query->whereDoesntHave('bookingUsers', function ($q) {
                        $q->where('created_at', '>=', Carbon::now()->subMonths(6));
                    });
                    break;
                case 'vip':
                    $query->where('clients_schools.is_vip', true);
                    break;
            }

            $emails = $query->select('clients.email')->distinct()->pluck('email')->toArray();
            $typeCount = count($emails);

            // Add to unique tracking
            foreach ($emails as $email) {
                if (!in_array($email, $addedEmails)) {
                    $addedEmails[] = $email;
                }
            }

            $counts['by_type'][$type] = $typeCount;
        }

        $counts['total'] = count($addedEmails);

        return $counts;
    }

    /**
     * Get newsletter subscriber statistics
     * Returns detailed stats: active, inactive, vip, total
     */
    public function subscriberStats(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);

        // Total subscribers (clients that accept newsletter in this school)
        $totalSubscribers = DB::table('clients_schools')
            ->where('school_id', $school->id)
            ->where('accepts_newsletter', true)
            ->distinct('client_id')
            ->count('client_id');

        // Active subscribers (accepts_newsletter = true AND have activity in last 3 months)
        $activeSubscribers = Client::query()
            ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
            ->where('clients_schools.school_id', $school->id)
            ->where('clients_schools.accepts_newsletter', true)
            ->where(function($query) {
                $query->whereHas('bookingUsers', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subMonths(3));
                });
            })
            ->distinct('clients.id')
            ->count('clients.id');

        // VIP subscribers (is_vip = true in clients_schools)
        $vipSubscribers = DB::table('clients_schools')
            ->where('school_id', $school->id)
            ->where('accepts_newsletter', true)
            ->where('is_vip', true)
            ->distinct('client_id')
            ->count('client_id');

        // Inactive subscribers (total - active)
        $inactiveSubscribers = $totalSubscribers - $activeSubscribers;

        $stats = [
            'active' => $activeSubscribers,
            'inactive' => $inactiveSubscribers > 0 ? $inactiveSubscribers : 0,
            'vip' => $vipSubscribers,
            'total' => $totalSubscribers
        ];

        return $this->sendResponse($stats, 'Subscriber stats retrieved successfully');
    }

    /**
     * Get list of subscribers filtered by type
     * Accepts query parameter: ?type={all|active|inactive|vip}
     */
    public function subscribers(Request $request): JsonResponse
    {
        $school = $this->getSchool($request);
        $type = $request->query('type', 'all');
        $perPage = $request->get('per_page', 50);

        $query = Client::query()
            ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
            ->where('clients_schools.school_id', $school->id)
            ->where('clients_schools.accepts_newsletter', true);

        // Apply filters based on type
        switch ($type) {
            case 'active':
                // Clients with accepts_newsletter = true AND activity in last 3 months
                $query->whereHas('bookingUsers', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subMonths(3));
                });
                break;

            case 'inactive':
                // Clients with accepts_newsletter = true BUT no activity in last 3 months
                $query->whereDoesntHave('bookingUsers', function($q) {
                    $q->where('created_at', '>=', Carbon::now()->subMonths(3));
                });
                break;

            case 'vip':
                // Clients with is_vip = true
                $query->where('clients_schools.is_vip', true);
                break;

            case 'all':
            default:
                // All subscribers (no additional filter)
                break;
        }

        // Get last booking date for "last activity" field
        $subscribers = $query
            ->leftJoin(DB::raw('(SELECT client_id, MAX(created_at) as last_booking_date FROM booking_users GROUP BY client_id) as last_bookings'),
                'clients.id', '=', 'last_bookings.client_id')
            ->select(
                'clients.id',
                'clients.first_name',
                'clients.last_name',
                'clients.email',
                'clients.created_at',
                'clients_schools.accepts_newsletter',
                'clients_schools.is_vip',
                DB::raw('COALESCE(last_bookings.last_booking_date, clients.created_at) as subscribed_at')
            )
            ->distinct('clients.id')
            ->paginate($perPage);

        // Transform the response to match expected format
        $transformedData = $subscribers->getCollection()->map(function($subscriber) {
            return [
                'id' => $subscriber->id,
                'name' => trim($subscriber->first_name . ' ' . $subscriber->last_name),
                'first_name' => $subscriber->first_name,
                'last_name' => $subscriber->last_name,
                'email' => $subscriber->email,
                'active' => $subscriber->accepts_newsletter,
                'accepts_newsletter' => $subscriber->accepts_newsletter,
                'vip' => $subscriber->is_vip,
                'is_vip' => $subscriber->is_vip,
                'created_at' => $subscriber->created_at,
                'subscribed_at' => $subscriber->subscribed_at
            ];
        });

        $subscribers->setCollection($transformedData);

        return $this->sendResponse($subscribers, 'Subscribers retrieved successfully');
    }

    /**
     * Export subscribers to CSV
     */
    public function exportSubscribers(Request $request)
    {
        $school = $this->getSchool($request);

        // Get all subscribers with their last activity
        $subscribers = Client::query()
            ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
            ->where('clients_schools.school_id', $school->id)
            ->where('clients_schools.accepts_newsletter', true)
            ->leftJoin(DB::raw('(SELECT client_id, MAX(created_at) as last_activity FROM booking_users GROUP BY client_id) as last_bookings'),
                'clients.id', '=', 'last_bookings.client_id')
            ->select(
                'clients.id',
                'clients.first_name',
                'clients.last_name',
                'clients.email',
                'clients.created_at',
                'clients_schools.is_vip',
                DB::raw('CASE
                    WHEN last_bookings.last_activity >= NOW() - INTERVAL 3 MONTH THEN "Activo"
                    ELSE "Inactivo"
                END as estado'),
                DB::raw('CASE WHEN clients_schools.is_vip = 1 THEN "Sí" ELSE "No" END as vip_status'),
                DB::raw('COALESCE(last_bookings.last_activity, clients.created_at) as ultima_actividad')
            )
            ->distinct('clients.id')
            ->orderBy('clients.id')
            ->get();

        // Generate CSV content
        $csvData = [];

        // CSV Headers
        $csvData[] = ['ID', 'Nombre', 'Email', 'Estado', 'VIP', 'Fecha de Registro', 'Última Actividad'];

        // CSV Rows
        foreach ($subscribers as $subscriber) {
            $csvData[] = [
                $subscriber->id,
                trim($subscriber->first_name . ' ' . $subscriber->last_name),
                $subscriber->email,
                $subscriber->estado,
                $subscriber->vip_status,
                Carbon::parse($subscriber->created_at)->format('Y-m-d'),
                $subscriber->ultima_actividad ? Carbon::parse($subscriber->ultima_actividad)->format('Y-m-d') : '-'
            ];
        }

        // Create CSV file
        $filename = 'subscribers_' . date('Y-m-d') . '.csv';
        $handle = fopen('php://temp', 'r+');

        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        // Return CSV response
        return response($csvContent, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    // Private helper methods

    private function calculateRecipients($schoolId, $recipientConfig): int
    {
        $recipients = $this->getRecipients($schoolId, $recipientConfig);
        return $recipients->count();
    }

    private function getRecipients($schoolId, $recipientConfig)
    {
        $school = School::find($schoolId);
        $query = Client::query()
            ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
            ->where('clients_schools.school_id', $school->id)
            ->where('clients_schools.accepts_newsletter', true);

        foreach ($recipientConfig as $type) {
            switch ($type) {
                case 'all':
                    // No additional filtering
                    break;
                case 'active':
                    // Clients with recent bookings (last 6 months)
                    $query->whereHas('bookingUsers', function ($q) {
                        $q->where('created_at', '>=', Carbon::now()->subMonths(6));
                    });
                    break;
                case 'inactive':
                    // Clients without recent bookings
                    $query->whereDoesntHave('bookingUsers', function ($q) {
                        $q->where('created_at', '>=', Carbon::now()->subMonths(6));
                    });
                    break;
                case 'vip':
                    // VIP clients (per school)
                    $query->where('clients_schools.is_vip', true);
                    break;
            }
        }

        return $query->select('clients.*')->distinct();
    }

    private function sendNewsletterEmails($newsletter, $recipients): int
    {
        $sentCount = 0;
        $school = School::find($newsletter->school_id);

        foreach ($recipients->get() as $recipient) {
            try {
                // Use the new Mailable class like the booking emails
                $newsletterMailer = new NewsletterMailer($newsletter, $recipient, $school);

                // Send the email using the same pattern as booking emails
                Mail::send($newsletterMailer);

                $sentCount++;
            } catch (\Exception $e) {
                Log::error("Failed to send newsletter to {$recipient->email}: " . $e->getMessage());
            }
        }

        return $sentCount;
    }

}
