<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\AppBaseController;
use App\Mail\BlankMailer;
use App\Models\BookingUser;
use App\Models\Client;
use App\Models\Course;
use App\Models\EmailLog;
use App\Models\Monitor;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MailController extends AppBaseController
{
    /**
     * @OA\Post(
     *      path="/admin/mails/send",
     *      summary="Send Mail",
     *      tags={"Admin"},
     *      description="Send emails to clients and/or monitors based on provided criteria.",
     *      @OA\RequestBody(
     *          required=true,
     *          description="Request body for sending emails.",
     *          @OA\JsonContent(
     *              required={"start_date", "end_date", "subject", "body"},
     *              @OA\Property(property="start_date", type="string", format="date", description="Start date for filtering courses."),
     *              @OA\Property(property="end_date", type="string", format="date", description="End date for filtering courses."),
     *              @OA\Property(property="course_ids", type="array", description="Array of course IDs to filter courses.",
     *                  @OA\Items(type="integer")
     *              ),
     *              @OA\Property(property="subject", type="string", description="Subject of the email to send."),
     *              @OA\Property(property="body", type="string", description="Body content of the email to send."),
     *              @OA\Property(property="monitors", type="boolean", description="Flag to send emails to monitors (default is false)."),
     *              @OA\Property(property="clients", type="boolean", description="Flag to send emails to clients (default is false).")
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Successful operation",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(
     *                  property="data",
     *                  type="array",
     *                  description="Array of email addresses that received the email.",
     *                  @OA\Items(type="string")
     *              ),
     *          ),
     *      ),
     *      @OA\Response(
     *          response=400,
     *          description="Bad request",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(property="success", type="boolean"),
     *              @OA\Property(
     *                  property="error",
     *                  type="string",
     *                  description="Error message if the request is invalid.",
     *              ),
     *          ),
     *      )
     * )
     */
    public function sendMail(Request $request): \Illuminate\Http\JsonResponse
    {

        $validator = Validator::make($request->all(), [
            'subject' => 'required',
            'body' => 'required'
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 400);
        }


        $school = $this->getSchool($request);
        //TODO: review dates

        $startDate = Carbon::parse($request->input('start_date'))->toDateString(); // "2024-12-21"
        $endDate = Carbon::parse($request->input('end_date'))->addDay()->toDateString(); // "2024-12-22"

        $courseIds = $request->input('course_ids');
        $subject = $request->input('subject');
        $body = $request->input('body');
        $sendToMonitors = $request->input('monitors', false);
        $sendToClients = $request->input('clients', false);

        // Inicializar una lista de correos únicos
        $uniqueEmails = [];

        if ($courseIds) {
            // Buscar cursos por IDs
            $courses = Course::whereIn('id', $courseIds)->where('school_id',  $school->id)->get();
        } elseif ($startDate && $endDate) {
            // Buscar cursos por rango de fechas
            $courses = Course::whereHas('courseDates', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })->where('school_id',  $school->id)->get();
        } else {
            return $this->sendError('No dates or ids provided');
        }



        foreach ($courses as $course) {
            if ($sendToClients) {
                // Buscar booking_users únicos relacionados con el curso y dentro del rango de fechas
                $bookingUsers = BookingUser::with('booking.clientMain')
                    ->where('status', 1)
                    ->whereHas('booking', function ($query) {
                        $query->where('status', '!=', 2); // La Booking no debe tener status 2
                    })
                    ->whereIn('booking_id', function ($query) use ($course,
                    $startDate, $endDate) {
                    $query->select('id')
                        ->from('bookings')
                        ->where('course_id', $course->id)
                        ->whereBetween('date', [$startDate, $endDate]);
                })->distinct('booking.client_main_id')->get();
                foreach ($bookingUsers as $bookingUser) {
                    $client = Client::find($bookingUser->booking->client_main_id);
                    if ($client && !in_array($client->email, $uniqueEmails)) {
                        // Agregar el correo del cliente a la lista
                        if ($client->email) {
                            $uniqueEmails[] = $client->email;
                        }
                    }
                }
            }

            if ($sendToMonitors) {
                // Buscar monitores relacionados con el curso y dentro del rango de fechas
                $monitors = Monitor::whereIn('id', function ($query) use ($course, $startDate, $endDate) {
                    $query->select('monitor_id')
                        ->from('course_subgroups')
                        ->where('course_id', $course->id)
                        ->whereNotNull('monitor_id')
                        ->whereIn('course_date_id', function ($subQuery) use ($startDate, $endDate) {
                            $subQuery->select('id')
                                ->from('course_dates')
                                ->whereBetween('date', [$startDate, $endDate]);
                        });
                })->distinct('email')->get();

                foreach ($monitors as $monitor) {
                    if (!in_array($monitor->email, $uniqueEmails)) {
                        // Agregar el correo del monitor a la lista
                        if ($monitor->email) {
                            $uniqueEmails[] = $monitor->email;
                        }
                    }
                }

                // Buscar monitores en los booking users dentro del rango de fechas
                $monitorBookingUsers = BookingUser::whereIn('booking_id', function ($query)
                use ($startDate, $endDate, $course) {
                    $query->select('booking_id')
                        ->from('bookings')
                        ->where('course_id', $course->id)
                        ->whereBetween('date', [$startDate, $endDate]);
                })->whereNotNull('monitor_id')
                    ->where('status', 1)
                    ->whereHas('booking', function ($query) {
                        $query->where('status', '!=', 2); // La Booking no debe tener status 2
                    })
                    ->distinct('monitor_id')->get();

                foreach ($monitorBookingUsers as $monitorUser) {
                    $monitor = Monitor::find($monitorUser->monitor_id);
                    if ($monitor && !in_array($monitor->email, $uniqueEmails)) {
                        // Agregar el correo del monitor a la lista
                        if ($monitor->email) {
                            $uniqueEmails[] = $monitor->email;
                        }
                    }
                }
            }
        }

        // Enviar el correo a los correos únicos
        if (!empty($uniqueEmails)) {
            $maxRecipientsPerEmail = 50; // Puedes ajustar este valor según tus necesidades
            $uniqueEmails[] = 'theboukiiteam@boukii.ch';
            $chunks = array_chunk($uniqueEmails, $maxRecipientsPerEmail);

            foreach ($chunks as $recipientChunk) {
                $blankMailer = new BlankMailer($subject, $body, [], $recipientChunk, $school);
                dispatch(function () use ($school, $recipientChunk, $blankMailer) {
                    // N.B. try-catch because some test users enter unexistant emails, throwing Swift_TransportException
                    try {
                        Mail::bcc($recipientChunk)->send($blankMailer);
                    } catch (\Exception $ex) {
                        \Illuminate\Support\Facades\Log::debug('Admin/MailController SenMailer: ' .
                            $ex->getMessage());
                    }
                })->afterResponse();
            }

            EmailLog::create([
                'school_id' => $school->id,
                'date' => Carbon::today(),
                'from' => 'booking@boukii.ch',
                'to' =>  implode(', ', $uniqueEmails),
                'subject' => $subject,
                'body' => $body
            ]);

            return $this->sendResponse($uniqueEmails, 'Correo enviado correctamente');
        }

        return $this->sendError('Emails not found');

    }

    /**
     * Get recipients for a specific email/mail
     */
    public function getRecipients(Request $request, $mailId)
    {
        try {
            $school = $this->getSchool($request);

            // Try to get from EmailLog first
            $emailLog = EmailLog::where('id', $mailId)
                ->where('school_id', $school->id)
                ->first();

            if ($emailLog) {
                $recipients = $this->parseEmailLogRecipients($emailLog);
                return $this->sendResponse($recipients, 'Recipients retrieved successfully');
            }

            // If not found in EmailLog, check if it's a newsletter
            $newsletter = \App\Models\Newsletter::where('id', $mailId)
                ->where('school_id', $school->id)
                ->first();

            if ($newsletter) {
                $recipients = $this->getNewsletterRecipients($newsletter, $school);
                return $this->sendResponse($recipients, 'Newsletter recipients retrieved successfully');
            }

            return $this->sendResponse([], 'No recipients found');

        } catch (\Exception $e) {
            return $this->sendError('Error retrieving recipients', $e->getMessage());
        }
    }

    private function parseEmailLogRecipients($emailLog)
    {
        $recipients = [];
        $emailAddresses = array_map('trim', explode(',', $emailLog->to));

        foreach ($emailAddresses as $email) {
            // Try to find the client/monitor by email
            $client = Client::where('email', $email)->first();
            if ($client) {
                $recipients[] = [
                    'type' => 'client',
                    'name' => $client->first_name . ' ' . $client->last_name,
                    'email' => $client->email
                ];
                continue;
            }

            $monitor = Monitor::where('email', $email)->first();
            if ($monitor) {
                $recipients[] = [
                    'type' => 'monitor',
                    'name' => $monitor->first_name . ' ' . $monitor->last_name,
                    'email' => $monitor->email
                ];
                continue;
            }

            // If not found in clients or monitors, treat as system/unknown
            $recipients[] = [
                'type' => 'system',
                'name' => 'Unknown Recipient',
                'email' => $email
            ];
        }

        return $recipients;
    }

    private function getNewsletterRecipients($newsletter, $school)
    {
        $recipients = [];
        // recipients_config is cast to array in the Newsletter model.
        // Only json_decode if it's a string; otherwise use as-is.
        $config = $newsletter->recipients_config;
        if (is_string($config)) {
            $decoded = json_decode($config, true);
            $config = $decoded !== null ? $decoded : [];
        }

        if (empty($config)) {
            return $recipients;
        }

        // Normalize types: support legacy { type: 'all' } and new ['all','active'] formats
        $types = [];
        if (is_array($config)) {
            if (array_keys($config) !== range(0, count($config) - 1)) {
                // associative structure like { type: 'all' } or { type: ['all','active'] }
                $val = $config['type'] ?? 'all';
                $types = is_array($val) ? $val : [$val];
            } else {
                // already an indexed array of types e.g. ['all', 'active']
                $types = $config;
            }
        } else {
            $types = ['all'];
        }

        $types = array_unique($types);

        $appendClients = function($clients) use (&$recipients) {
            foreach ($clients as $client) {
                $recipients[] = [
                    'type' => 'client',
                    'name' => $client->first_name . ' ' . $client->last_name,
                    'email' => $client->email
                ];
            }
        };

        foreach ($types as $type) {
            switch ($type) {
                case 'all':
                    $clients = Client::query()
                        ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
                        ->where('clients_schools.school_id', $school->id)
                        ->where('clients_schools.accepts_newsletter', true)
                        ->get(['clients.first_name', 'clients.last_name', 'clients.email']);
                    $appendClients($clients);
                    break;
                case 'active':
                    // Active clients in last 3 months (heuristic)
                    $clients = Client::query()
                        ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
                        ->where('clients_schools.school_id', $school->id)
                        ->where('clients_schools.accepts_newsletter', true)
                        ->where('clients.updated_at', '>=', now()->subMonths(3))
                        ->get(['clients.first_name', 'clients.last_name', 'clients.email']);
                    $appendClients($clients);
                    break;
                case 'inactive':
                    $clients = Client::query()
                        ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
                        ->where('clients_schools.school_id', $school->id)
                        ->where('clients_schools.accepts_newsletter', true)
                        ->where('clients.updated_at', '<', now()->subMonths(3))
                        ->get(['clients.first_name', 'clients.last_name', 'clients.email']);
                    $appendClients($clients);
                    break;
                case 'vip':
                    $clients = Client::query()
                        ->join('clients_schools', 'clients_schools.client_id', '=', 'clients.id')
                        ->where('clients_schools.school_id', $school->id)
                        ->where('clients_schools.accepts_newsletter', true)
                        ->where('clients_schools.is_vip', true)
                        ->get(['clients.first_name', 'clients.last_name', 'clients.email']);
                    $appendClients($clients);
                    break;
            }
        }

        // Deduplicate by email
        $recipients = collect($recipients)
            ->unique('email')
            ->values()
            ->all();

        return $recipients;
    }
}
