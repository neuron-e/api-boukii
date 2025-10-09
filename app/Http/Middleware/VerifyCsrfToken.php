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
        'api/languages*',           // Listado de idiomas (todos los métodos)
        'api/mails*',               // Sistema de correo (todos los métodos)
        'api/email-logs*',          // Logs de email (todos los métodos)
        'api/translate',            // Servicio de traducción
        'api/booking-logs*',        // Logs de reservas (todos los métodos)
        'api/payments*',            // Gestión de pagos (todos los métodos)
        'api/booking-users*',       // Usuarios de reservas (todos los métodos)
        'api/vouchers*',            // Gestión de vouchers (todos los métodos)
        'api/vouchers-logs*',       // Logs de vouchers (todos los métodos)
        'api/bookings*',            // Gestión de reservas (todos los métodos)
        'api/booking-user-extras*', // Extras de booking users (todos los métodos)

        // Endpoints CRUD genéricos usados por admin y teach app
        'api/clients*',             // Gestión de clientes (todos los métodos)
        'api/clients-utilizers*',   // Utilizadores de clientes (todos los métodos)
        'api/clients-schools*',     // Relación clientes-escuelas (todos los métodos)
        'api/client-sports*',       // Deportes de clientes (todos los métodos)
        'api/client-observations*', // Observaciones de clientes (todos los métodos)
        'api/courses*',             // Gestión de cursos (todos los métodos)
        'api/course-dates*',        // Fechas de cursos (todos los métodos)
        'api/course-groups*',       // Grupos de cursos (todos los métodos)
        'api/course-subgroups*',    // Subgrupos de cursos (todos los métodos)
        'api/course-extras*',       // Extras de cursos (todos los métodos)
        'api/course-intervals*',    // Intervalos de cursos V4 (todos los métodos)
        'api/monitors*',            // Gestión de monitores (todos los métodos)
        'api/monitor-nwds*',        // NWDs de monitores (todos los métodos)
        'api/monitor-observations*',// Observaciones de monitores (todos los métodos)
        'api/monitor-sports-degrees*', // Títulos de monitores (todos los métodos)
        'api/monitor-sport-authorized-degrees*', // Grados autorizados (todos los métodos)
        'api/monitor-trainings*',   // Formaciones de monitores (todos los métodos)
        'api/monitors-schools*',    // Relación monitores-escuelas (todos los métodos)
        'api/stations*',            // Gestión de estaciones (todos los métodos)
        'api/stations-schools*',    // Relación estaciones-escuelas (todos los métodos)
        'api/station-services*',    // Servicios de estaciones (todos los métodos)
        'api/service-types*',       // Tipos de servicios (todos los métodos)
        'api/sports*',              // Gestión de deportes (todos los métodos)
        'api/sport-types*',         // Tipos de deportes (todos los métodos)
        'api/school-sports*',       // Deportes de escuelas (todos los métodos)
        'api/school-colors*',       // Colores de escuelas (todos los métodos)
        'api/school-salary-levels*',// Niveles salariales (todos los métodos)
        'api/school-users*',        // Usuarios de escuelas (todos los métodos)
        'api/degrees*',             // Gestión de niveles (todos los métodos)
        'api/degrees-school-sport-goals*', // Objetivos de deportes (todos los métodos)
        'api/evaluations*',         // Evaluaciones (todos los métodos)
        'api/evaluation-files*',    // Archivos de evaluación (todos los métodos)
        'api/evaluation-fulfilled-goals*', // Objetivos cumplidos (todos los métodos)
        'api/seasons*',             // Temporadas (todos los métodos)
        'api/tasks*',               // Tareas (todos los métodos)
        'api/task-checks*',         // Checks de tareas (todos los métodos)
        'api/users*',               // Gestión de usuarios (todos los métodos)
        'api/discount-codes*',      // Códigos de descuento (todos los métodos)
        'api/forgot-password',      // Reset de contraseña (público)
        'api/reset-password',       // Reset de contraseña (público)
        'api/availability*',        // Sistema de disponibilidad (todos los métodos)
    ];
}
