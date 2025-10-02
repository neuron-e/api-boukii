<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Webhooks externos con signature verification
        'api/payrexxNotification',  // Webhook de pagos (usa signature verification)
        'api/payrexx/finish',       // Finalización de pagos
        'api/admin/integrations/webhook/realtime-update',
        
        // Admin API endpoints - Angular usa Bearer tokens, no CSRF
        'api/admin/*',              // Admin panel usa Bearer authentication
        'api/teach/*',              // Teach app usa Bearer authentication
        
        // Endpoints CRUD usados por Angular admin sin CSRF
        'api/schools/*',            // Gestión de escuelas
        'api/languages',            // Listado de idiomas
        'api/mails',               // Sistema de correo
        'api/email-logs',          // Logs de email
        'api/translate',           // Servicio de traducción
        'api/booking-logs',        // Logs de reservas
        'api/payments',            // Gestión de pagos
        'api/booking-users',       // Usuarios de reservas
        'api/vouchers',            // Gestión de vouchers
        'api/vouchers-logs',       // Logs de vouchers
        'api/bookings',            // Gestión de reservas
        
        // Endpoints CRUD genéricos usados por admin y teach app
        'api/clients',
        'api/client-sports',       // Deportes de clientes
        'api/courses',
        'api/course-dates',
        'api/course-groups',
        'api/course-subgroups',
        'api/monitors',
        'api/monitor-nwds',        // NWDs de monitores
        'api/monitor-sports-degrees', // Títulos de monitores
        'api/monitors-schools',    // Relación monitores-escuelas
        'api/stations',
        'api/stations-schools',    // Relación estaciones-escuelas
        'api/sports',
        'api/degrees',
        'api/degrees-school-sport-goals', // Objetivos de deportes
        'api/evaluations',
        'api/evaluation-files',    // Archivos de evaluación
        'api/evaluation-fulfilled-goals', // Objetivos cumplidos
        'api/seasons',            // Temporadas
        'api/forgot-password',    // Reset de contraseña (público)
        'api/reset-password',     // Reset de contraseña (público)
    ];
}
