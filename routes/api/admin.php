<?php

use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\AnalyticsProfessionalController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\FinanceControllerRefactor;
use App\Http\Controllers\Admin\StatisticsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;


// Public
Route::post('login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])->name('api.admin.login');
/*
Route::delete('logout', [\App\Http\Controllers\Auth\LogoutController::class, 'destroy'])->name('api.admin.logout');
Route::post('auth/recover-password', [\App\Http\Controllers\Auth\AuthController::class, 'recoverPassword'])->name('api.admin.recoverPassword');
Route::post('auth/reset-password/{token}', [\App\Http\Controllers\Auth\AuthController::class, 'resetPassword']);*/


// Private - Con rate limiting específico para admin Angular
Route::middleware(['auth:sanctum', 'ability:admin:all', 'admin.rate.limit'])->group(function() {

    Route::resource('courses', App\Http\Controllers\Admin\CourseController::class)
        ->except(['create', 'edit'])->names([
            'index' => 'api.admin.courses.index',
            'store' => 'api.admin.courses.store',
            'show' => 'api.admin.courses.show',
            'update' => 'api.admin.courses.update',
            'destroy' => 'api.admin.courses.destroy',
        ]);

    Route::get('/courses/{id}/export/{lang}', [App\Http\Controllers\Admin\CourseController::class, 'exportDetails']);

    Route::get('/courses/{id}/sells/', [App\Http\Controllers\Admin\CourseController::class, 'getSellStats']);

    Route::get('getPlanner', [\App\Http\Controllers\Admin\PlannerController::class, 'getPlanner'])
        ->name('api.admin.planner');

    Route::get('clients/mains', [\App\Http\Controllers\Admin\ClientsController::class, 'getMains'])
        ->name('api.admin.clients.main');

    Route::get('clients/stats', [\App\Http\Controllers\Admin\ClientStatsController::class, 'index'])
        ->name('api.admin.clients.stats');

    Route::get('clients/by-type/{type}', [\App\Http\Controllers\Admin\ClientStatsController::class, 'getClientsByType'])
        ->name('api.admin.clients.by_type');

    Route::resource('clients', App\Http\Controllers\Admin\ClientsController::class)
        ->except(['create', 'edit'])->names([
            'index' => 'api.admin.clients.index',
            'store' => 'api.admin.clients.store',
            'show' => 'api.admin.clients.show',
            'update' => 'api.admin.clients.update',
            'destroy' => 'api.admin.clients.destroy',
        ]);

    Route::get('clients/{id}/utilizers', [\App\Http\Controllers\Admin\ClientsController::class, 'getUtilizers'])
        ->name('api.admin.clients.utilizers');

    Route::get('clients/course/{id}', [\App\Http\Controllers\Admin\ClientsController::class, 'getClientsByCourse'])
        ->name('api.admin.clients.courses.find');

    Route::post('monitors/available', [\App\Http\Controllers\Admin\MonitorController::class, 'getMonitorsAvailable'])
        ->name('api.admin.monitors.available');

    Route::post('monitors/available/{id}', [\App\Http\Controllers\Admin\MonitorController::class,
        'checkIfMonitorIsAvailable'])
        ->name('api.admin.monitor.availability');

    Route::post('planner/monitors/transfer', [\App\Http\Controllers\Admin\PlannerController::class, 'transferMonitor'])
        ->name('api.admin.planner.transfer');

    /** Booking **/
    Route::post('bookings',
        [\App\Http\Controllers\Admin\BookingController::class, 'store'])
        ->name('api.admin.bookings.store');

    Route::post('bookings/checkbooking',
        [\App\Http\Controllers\Admin\BookingController::class, 'checkClientBookingOverlap'])
        ->name('api.admin.bookings.bookingoverlap');

    /** Booking **/
    Route::post('bookings/payments/{id}',
        [\App\Http\Controllers\Admin\BookingController::class, 'payBooking'])
        ->name('api.admin.bookings.pay');

    Route::post('bookings/mail/{id}',
        [\App\Http\Controllers\Admin\BookingController::class, 'mailBooking'])
        ->name('api.admin.bookings.mail');

    Route::post('bookings/refunds/{id}',
        [\App\Http\Controllers\Admin\BookingController::class, 'refundBooking'])
        ->name('api.admin.bookings.refund');

    Route::post('bookings/cancel',
        [\App\Http\Controllers\Admin\BookingController::class, 'cancelBookings'])
        ->name('api.admin.bookings.cancel');

    Route::post('bookings/update',
        [\App\Http\Controllers\Admin\BookingController::class, 'update'])
        ->name('api.admin.bookings.update');

    Route::post('bookings/update/{id}/payment',
        [\App\Http\Controllers\Admin\BookingController::class, 'updatePayment'])
        ->name('api.admin.bookings.updatePayment');

    /** Statistics */
    /*    Route::get('statistics/bookings', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getTotalAvailablePlacesByCourseType'])
            ->name('api.admin.stats.bookings');

        Route::get('statistics/bookings/sells', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getCoursesWithDetails'])
            ->name('api.admin.stats.bookings.sells');

        Route::get('statistics/bookings/dates', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getBookingUsersByDateRange'])
            ->name('api.admin.stats.bookingsDates');

        Route::get('statistics/bookings/sports', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getBookingUsersBySport'])
            ->name('api.admin.stats.bookingsSports');

        Route::get('statistics/total', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getTotalPrice'])
            ->name('api.admin.stats.bookingsSports');


        Route::get('statistics/monitors/total', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getTotalMonitorPrice'])
            ->name('api.admin.stats.bookingsSports');

      Route::get('statistics/bookings/monitors', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getMonitorsBookings'])
            ->name('api.admin.stats.monitors');

        Route::get('statistics/bookings/monitors/active', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getActiveMonitors'])
            ->name('api.admin.stats.monitors.active');

        Route::get('statistics/bookings/monitors/hours', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getTotalWorkedHours'])
            ->name('api.admin.stats.monitors.hours');

        Route::get('statistics/bookings/monitors/sports', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getTotalWorkedHoursBySport'])
            ->name('api.admin.stats.monitors.sports');

      Route::get('statistics/bookings/monitors/{id}', [\App\Http\Controllers\Admin\StatisticsControllerOld::class, 'getMonitorDailyBookings'])
            ->name('api.admin.stats.monitors.id');*/


    /** Mailing */
    Route::post('mails/send', [\App\Http\Controllers\Admin\MailController::class, 'sendMail']);
    Route::get('mails/{mailId}/recipients', [\App\Http\Controllers\Admin\MailController::class, 'getRecipients']);

    /** Templates */
    Route::resource('templates', App\Http\Controllers\Admin\TemplateController::class)
        ->except(['create', 'edit'])->names([
            'index' => 'api.admin.templates.index',
            'store' => 'api.admin.templates.store',
            'show' => 'api.admin.templates.show',
            'update' => 'api.admin.templates.update',
            'destroy' => 'api.admin.templates.destroy',
        ]);

    /** Newsletter */
    Route::get('newsletters/stats', [\App\Http\Controllers\Admin\NewsletterController::class, 'stats'])
        ->name('api.admin.newsletters.stats');
    
    Route::get('newsletters/recent', [\App\Http\Controllers\Admin\NewsletterController::class, 'recent'])
        ->name('api.admin.newsletters.recent');
    
    Route::post('newsletters/subscriber-count', [\App\Http\Controllers\Admin\NewsletterController::class, 'subscriberCount'])
        ->name('api.admin.newsletters.subscriber-count');
    
    Route::get('newsletters/subscribers', [\App\Http\Controllers\Admin\NewsletterController::class, 'subscribers'])
        ->name('api.admin.newsletters.subscribers');

    Route::resource('newsletters', App\Http\Controllers\Admin\NewsletterController::class)
        ->except(['create', 'edit'])->names([
            'index' => 'api.admin.newsletters.index',
            'store' => 'api.admin.newsletters.store',
            'show' => 'api.admin.newsletters.show',
            'update' => 'api.admin.newsletters.update',
            'destroy' => 'api.admin.newsletters.destroy',
        ]);
    
    Route::post('newsletters/{id}/send', [\App\Http\Controllers\Admin\NewsletterController::class, 'send'])
        ->name('api.admin.newsletters.send');

    // Test endpoint for debugging
    Route::get('newsletters/test', [\App\Http\Controllers\Admin\NewsletterController::class, 'test']);

    /** Weather */
    Route::get('weather', [\App\Http\Controllers\Admin\HomeController::class, 'get12HourlyForecastByStation'])
        ->name('api.admin.weather');

    Route::get('weather/week', [\App\Http\Controllers\Admin\HomeController::class, 'get5DaysForecastByStation'])
        ->name('api.admin.weatherweek');

    // ==================== STATISTICS LEGACY (mantener para compatibilidad) ====================

    Route::get('statistics/monitors/total', [\App\Http\Controllers\Admin\StatisticsController::class, 'getTotalMonitorPrice'])
        ->name('api.admin.stats.bookingsSports');

    Route::get('statistics/bookings/monitors', [\App\Http\Controllers\Admin\StatisticsController::class, 'getMonitorsBookings'])
        ->name('api.admin.stats.monitors');

    Route::get('statistics/bookings/monitors/active', [\App\Http\Controllers\Admin\StatisticsController::class, 'getActiveMonitors'])
        ->name('api.admin.stats.monitors.active');

    Route::get('statistics/bookings/monitors/hours', [\App\Http\Controllers\Admin\StatisticsController::class, 'getTotalWorkedHours'])
        ->name('api.admin.stats.monitors.hours');

    Route::get('statistics/bookings/monitors/sports', [\App\Http\Controllers\Admin\StatisticsController::class, 'getTotalWorkedHoursBySport'])
        ->name('api.admin.stats.monitors.sports');

    Route::get('statistics/bookings/monitors/{id}', [\App\Http\Controllers\Admin\StatisticsController::class, 'getMonitorDailyBookings'])
        ->name('api.admin.stats.monitors.id');
    Route::get('/statistics/bookings/dates', [StatisticsController::class, 'getBookingUsersByDateRange']);
    Route::get('/statistics/bookings/sports', [StatisticsController::class, 'getBookingUsersBySport']);
    Route::get('/statistics/bookings/sells', [StatisticsController::class, 'getCoursesWithDetails']);
    Route::get('/statistics/bookings', [StatisticsController::class, 'getTotalAvailablePlacesByCourseType']);
    Route::get('/statistics/total', [StatisticsController::class, 'getTotalPrice']);

// Grupo principal de analytics - AHORA APUNTA A FinanceControllerRefactor
    Route::group(['prefix' => 'analytics'], function () {

        // ===== ENDPOINTS PRINCIPALES PARA EL FRONTEND =====
        Route::get('/summary', [FinanceControllerRefactor::class, 'getSummary'])
            ->name('analytics.summary');

        Route::get('/courses', [FinanceControllerRefactor::class, 'getCourseAnalytics'])
            ->name('analytics.courses');

        Route::get('/revenue', [FinanceControllerRefactor::class, 'getRevenueAnalytics'])
            ->name('analytics.revenue');

        Route::get('/payment-details', [FinanceControllerRefactor::class, 'getPaymentDetails'])
            ->name('analytics.payment-details');

        Route::get('/financial-dashboard', [FinanceControllerRefactor::class, 'getFinancialDashboard'])
            ->name('analytics.financial-dashboard');

        Route::get('/performance-comparison', [FinanceControllerRefactor::class, 'getPerformanceComparison'])
            ->name('analytics.performance-comparison');

        // ===== EXPORTACIONES =====
        Route::post('/export/csv', [FinanceControllerRefactor::class, 'exportToCSV'])
            ->name('analytics.export-csv');

        Route::post('/export/excel', [FinanceControllerRefactor::class, 'exportToExcel'])
            ->name('analytics.export-excel');

        Route::post('/export/pdf', [FinanceControllerRefactor::class, 'exportToPDF'])
            ->name('analytics.export-pdf');

        Route::get('/download/{filename}', [FinanceControllerRefactor::class, 'downloadExport'])
            ->name('analytics.download-export');

        // ===== ANALYTICS PROFESIONALES OPTIMIZADOS PARA ADMIN ANGULAR =====
        
        // Dashboard de temporada (endpoint principal del admin)
        Route::get('/finance/season-dashboard', [AnalyticsProfessionalController::class, 'seasonDashboard'])
            ->name('analytics.season-dashboard');

        // Análisis de ingresos por período (usado por gráficos admin)
        Route::get('/revenue-by-period', [AnalyticsProfessionalController::class, 'revenueByPeriod'])
            ->name('analytics.revenue-by-period');

        // Análisis detallado de cursos (tabla principal admin)
        Route::get('/courses-detailed', [AnalyticsProfessionalController::class, 'coursesDetailed'])
            ->name('analytics.courses-detailed');

        // Análisis de eficiencia de monitores (dashboard monitores)
        Route::get('/monitors-efficiency', [AnalyticsProfessionalController::class, 'monitorsEfficiency'])
            ->name('analytics.monitors-efficiency');

        // Gestión de caché de analytics (usado por admin)
        Route::delete('/cache/clear', [AnalyticsProfessionalController::class, 'clearCache'])
            ->name('analytics.cache-clear');
        
        Route::get('/cache/status', [AnalyticsProfessionalController::class, 'cacheStatus'])
            ->name('analytics.cache-status');

        // Análisis de conversión y abandono
        Route::get('/conversion-analysis', [FinanceControllerRefactor::class, 'getConversionAnalysis'])
            ->name('analytics.conversion-analysis');

        // Tendencias y predicciones
        Route::get('/trends-prediction', [FinanceControllerRefactor::class, 'getTrendsAndPredictions'])
            ->name('analytics.trends-prediction');

        // Métricas en tiempo real
        Route::get('/realtime-metrics', [FinanceControllerRefactor::class, 'getRealtimeMetrics'])
            ->name('analytics.realtime-metrics');

        // Análisis por deportes
        Route::get('/sports-performance', [FinanceControllerRefactor::class, 'getSportsPerformanceAnalytics'])
            ->name('analytics.sports-performance');

        // Análisis de temporadas
        Route::get('/seasonal-comparison', [FinanceControllerRefactor::class, 'getSeasonalComparison'])
            ->name('analytics.seasonal-comparison');

        // Análisis de clientes
        Route::get('/customer-insights', [FinanceControllerRefactor::class, 'getCustomerInsights'])
            ->name('analytics.customer-insights');

        // Análisis de capacidad
        Route::get('/capacity-analysis', [FinanceControllerRefactor::class, 'getCapacityAnalysis'])
            ->name('analytics.capacity-analysis');

        // KPIs específicos de monitor
        Route::get('/monitors/{monitorId}/daily', [FinanceControllerRefactor::class, 'getMonitorDailyAnalytics'])
            ->name('analytics.monitor-daily');

        Route::get('/monitors/{monitorId}/performance', [FinanceControllerRefactor::class, 'getMonitorPerformance'])
            ->name('analytics.monitor-performance');

        // Dashboards específicos
        Route::get('/executive-dashboard', [FinanceControllerRefactor::class, 'getExecutiveDashboard'])
            ->name('analytics.executive-dashboard');

        Route::get('/operational-dashboard', [FinanceControllerRefactor::class, 'getOperationalDashboard'])
            ->name('analytics.operational-dashboard');

        // Análisis de precios y rentabilidad
        Route::get('/pricing-analysis', [FinanceControllerRefactor::class, 'getPricingAnalysis'])
            ->name('analytics.pricing-analysis');

        // Análisis de satisfacción
        Route::get('/satisfaction-metrics', [FinanceControllerRefactor::class, 'getSatisfactionMetrics'])
            ->name('analytics.satisfaction-metrics');

        // Configuración y preferencias
        Route::get('/preferences', [FinanceControllerRefactor::class, 'getAnalyticsPreferences'])
            ->name('analytics.get-preferences');

        Route::post('/preferences', [FinanceControllerRefactor::class, 'saveAnalyticsPreferences'])
            ->name('analytics.save-preferences');

        // Consultas personalizadas
        Route::post('/custom-query', [FinanceControllerRefactor::class, 'executeCustomQuery'])
            ->name('analytics.custom-query');

    });

    Route::group(['prefix' => 'finance'], function () {

        Route::get('/season-dashboard', [FinanceController::class, 'getSeasonFinancialDashboard']);

        Route::get('/season-dashboard/export', [FinanceController::class, 'exportRealSalesReport']);
        /*        Route::get('/season-dashboard', [FinanceControllerRefactor::class, 'getSeasonFinancialDashboard'])
                    ->name('finance.season-dashboard');*/

        Route::get('/booking-details', [FinanceController::class, 'getBookingDetails']);
        Route::get('/export-pending-bookings', [FinanceController::class, 'exportPendingBookings']);
        Route::get('/export-cancelled-bookings', [FinanceController::class, 'exportCancelledBookings']);

        Route::get('/export-dashboard', [FinanceControllerRefactor::class, 'exportSeasonDashboard'])
            ->name('finance.export-dashboard');

        Route::post('/export-real-sales', [FinanceController::class, 'exportRealSalesReport'])
            ->name('finance.export-real-sales');



        // ==================== NUEVAS RUTAS PARA COURSE STATISTICS ====================

        Route::prefix('courses')->group(function () {

            // Estadísticas detalladas de un curso específico
            Route::get('/{courseId}/statistics', [FinanceController::class, 'getCourseStatistics'])
                ->where('courseId', '[0-9]+')
                ->name('api.admin.courses.statistics');

            // Exportar estadísticas de curso
            Route::get('/{courseId}/statistics/export', [FinanceController::class, 'exportCourseStatistics'])
                ->where('courseId', '[0-9]+')
                ->name('api.admin.courses.statistics.export');

            // Opcional: Comparar curso con similares
            Route::get('/{courseId}/statistics/compare', [FinanceController::class, 'compareCourseWithSimilar'])
                ->where('courseId', '[0-9]+')
                ->name('api.admin.courses.statistics.compare');
        });

    });

    Route::group(['prefix' => 'integrations'], function () {

        // Sincronización con Payrexx
        Route::post('/payrexx/sync', [FinanceControllerRefactor::class, 'syncPayrexxData'])
            ->name('integrations.payrexx-sync');

        // Exportación a Google Analytics
        Route::post('/google-analytics/export', [FinanceControllerRefactor::class, 'exportToGoogleAnalytics'])
            ->name('integrations.google-analytics-export');

        // Webhook para datos en tiempo real
        Route::post('/webhook/realtime-update', [FinanceControllerRefactor::class, 'handleRealtimeWebhook'])
            ->name('integrations.realtime-webhook');

    });

    Route::group(['prefix' => 'analytics-admin'], function () {

        // Permisos de acceso a analytics
        Route::get('/permissions', [FinanceControllerRefactor::class, 'getAnalyticsPermissions'])
            ->name('analytics-admin.permissions');

        Route::post('/permissions', [FinanceControllerRefactor::class, 'updateAnalyticsPermissions'])
            ->name('analytics-admin.update-permissions');

        // Configuración del sistema
        Route::get('/system-config', [FinanceControllerRefactor::class, 'getSystemConfig'])
            ->name('analytics-admin.system-config');

        Route::post('/system-config', [FinanceControllerRefactor::class, 'updateSystemConfig'])
            ->name('analytics-admin.update-system-config');

        // Logs y auditoría
        Route::get('/audit-logs', [FinanceControllerRefactor::class, 'getAuditLogs'])
            ->name('analytics-admin.audit-logs');

        // Performance monitoring
        Route::get('/performance-metrics', [FinanceControllerRefactor::class, 'getPerformanceMetrics'])
            ->name('analytics-admin.performance-metrics');

    });

});

// API pública para dashboards embebidos (con autenticación API)
Route::group(['prefix' => 'public-analytics', 'middleware' => 'api_token'], function () {

    Route::get('/embed/dashboard/{token}', [FinanceControllerRefactor::class, 'getEmbeddedDashboard'])
        ->name('public-analytics.embedded-dashboard');

    Route::get('/embed/kpis/{token}', [FinanceControllerRefactor::class, 'getEmbeddedKpis'])
        ->name('public-analytics.embedded-kpis');

});

// Rutas para desarrollo y debug (solo en entorno de desarrollo)
if (app()->environment(['local', 'development'])) {

    Route::group(['prefix' => 'debug-analytics'], function () {

        Route::get('/test-data-generation', [FinanceControllerRefactor::class, 'generateTestData'])
            ->name('debug.generate-test-data');

        Route::get('/performance-test', [FinanceControllerRefactor::class, 'performanceTest'])
            ->name('debug.performance-test');

        Route::get('/validate-calculations', [FinanceControllerRefactor::class, 'validateCalculations'])
            ->name('debug.validate-calculations');

        // Debug específico para detección de reservas de prueba
        Route::get('/debug-test-detection/{schoolId}', [FinanceControllerRefactor::class, 'debugTestBookingDetection'])
            ->name('debug.test-detection');

        // Debug financiero de reserva específica
        Route::get('/debug-booking-financials/{bookingId}', [FinanceControllerRefactor::class, 'debugBookingFinancials'])
            ->name('debug.booking-financials');

    });

    // ===== TIMING API v4 - Sistema de cronometraje =====
    Route::prefix('timing')->group(function () {
        
        // Endpoint de ingesta de eventos de cronometraje (con API Key auth)
        Route::post('/ingest', [\App\Http\Controllers\Admin\TimingController::class, 'ingest'])
            ->middleware('api.key:timing:write')
            ->name('timing.ingest');
        
        // Stream de eventos en tiempo real (SSE)
        Route::get('/stream', [\App\Http\Controllers\Admin\TimingController::class, 'stream'])
            ->name('timing.stream');
        
        // Resumen/estado inicial
        Route::get('/summary', [\App\Http\Controllers\Admin\TimingController::class, 'summary'])
            ->name('timing.summary');
        
        // CRUD de tiempos individuales
        Route::put('/times/{id}', [\App\Http\Controllers\Admin\TimingController::class, 'updateTime'])
            ->name('timing.times.update');
        
        Route::delete('/times/{id}', [\App\Http\Controllers\Admin\TimingController::class, 'deleteTime'])
            ->name('timing.times.delete');
    });

}

// ===== COURSE TIMING MANAGEMENT - Admin Panel Integration =====
Route::middleware(['auth:sanctum', 'ability:admin:all'])->group(function() {
    Route::resource('course-timing', \App\Http\Controllers\Admin\CourseTimingController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->names([
            'index' => 'api.admin.course-timing.index',
            'store' => 'api.admin.course-timing.store',
            'show' => 'api.admin.course-timing.show',
            'update' => 'api.admin.course-timing.update',
            'destroy' => 'api.admin.course-timing.destroy',
        ]);
});
