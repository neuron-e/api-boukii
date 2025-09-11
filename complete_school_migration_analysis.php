<?php
/**
 * ANÁLISIS COMPLETO DE MIGRACIÓN DE ESCUELA
 * School 13 (SSS Churwalden) de boukii_dev a boukii_pro (como school 15)
 */

echo "=== RESUMEN COMPLETO DE DATOS A MIGRAR ===" . PHP_EOL;
echo "School: SSS Churwalden (ID: 13 -> 15)" . PHP_EOL . PHP_EOL;

/**
 * NIVEL 1: TABLAS BASE (sin dependencias externas)
 * Estas se pueden migrar primero
 */
$level1_tables = [
    'schools' => 1,                     // Tabla principal
    'sports' => 5,                      // Deportes base
    'stations' => 1,                    // Estación base
    'seasons' => 1,                     // Temporada
    'degrees' => 63,                    // Grados/certificaciones
    'users' => 2,                       // Usuarios (pueden ir aquí si no dependen de otros)
    'clients' => 4,                     // Clientes
    'monitors' => 75,                   // Monitores
];

/**
 * NIVEL 2: TABLAS PIVOT DE RELACIONES BÁSICAS
 * Dependen de las tablas del nivel 1
 */
$level2_pivot_tables = [
    'school_users' => 2,                // users <-> schools
    'clients_schools' => 4,             // clients <-> schools  
    'monitors_schools' => 150,          // monitors <-> schools
    'school_sports' => 5,               // schools <-> sports
    'stations_schools' => 1,            // stations <-> schools
    'client_sports' => '?',             // clients <-> sports (pendiente analizar)
    'monitor_sports' => '?',            // monitors <-> sports (pendiente analizar)
    'school_colors' => 9,               // colores de escuela
    'school_salary_levels' => 9,        // niveles salariales
];

/**
 * NIVEL 3: TABLAS DE CURSOS
 * Dependen de school, sports, degrees
 */
$level3_course_tables = [
    'courses' => 3,                     // Cursos base
    'course_groups' => 52,              // Grupos de curso (depende de courses)
    'course_subgroups' => 52,           // Subgrupos (depende de course_groups)
    'course_dates' => 405,              // Fechas de curso (depende de courses)
];

/**
 * NIVEL 4: CERTIFICACIONES DE MONITORES
 * Dependen de monitors, sports, degrees
 */
$level4_monitor_certifications = [
    'monitor_sport_authorized_degrees' => '?', // certificaciones monitor-deporte-grado
    'degrees_school_sport_goals' => '?',       // objetivos grado-escuela-deporte
];

/**
 * NIVEL 5: RESERVAS Y PAGOS
 * Dependen de todo lo anterior
 */
$level5_booking_tables = [
    'bookings' => 1,                    // Reservas principales
    'booking_users' => 1,               // usuarios en reservas
    'payments' => 1,                    // pagos de reservas
    'vouchers' => 0,                    // vouchers (ninguno actualmente)
    'voucher_logs' => '?',              // logs de vouchers
];

/**
 * NIVEL 6: CONFIGURACIONES Y EMAILS
 * Pueden ir al final
 */
$level6_config_tables = [
    'mails' => 0,                       // plantillas de email
];

echo "NIVEL 1 - TABLAS BASE:" . PHP_EOL;
foreach($level1_tables as $table => $count) {
    echo "  $table: $count records" . PHP_EOL;
}

echo "\nNIVEL 2 - RELACIONES PIVOT:" . PHP_EOL;
foreach($level2_pivot_tables as $table => $count) {
    echo "  $table: $count records" . PHP_EOL;
}

echo "\nNIVEL 3 - ESTRUCTURA DE CURSOS:" . PHP_EOL;
foreach($level3_course_tables as $table => $count) {
    echo "  $table: $count records" . PHP_EOL;
}

echo "\nNIVEL 4 - CERTIFICACIONES:" . PHP_EOL;
foreach($level4_monitor_certifications as $table => $count) {
    echo "  $table: $count records" . PHP_EOL;
}

echo "\nNIVEL 5 - RESERVAS Y PAGOS:" . PHP_EOL;
foreach($level5_booking_tables as $table => $count) {
    echo "  $table: $count records" . PHP_EOL;
}

echo "\nNIVEL 6 - CONFIGURACIÓN:" . PHP_EOL;
foreach($level6_config_tables as $table => $count) {
    echo "  $table: $count records" . PHP_EOL;
}

echo PHP_EOL . "TOTAL ESTIMADO: ~800+ records a migrar" . PHP_EOL;

/**
 * CONSIDERACIONES ESPECIALES:
 * 
 * 1. MAPEO DE IDs: Todos los foreign keys deben ser remapeados
 * 2. SOFT DELETES: Verificar deleted_at en todas las consultas
 * 3. TIMESTAMPS: Mantener created_at/updated_at originales
 * 4. SCHEMA DIFFERENCES: Verificar diferencias entre dev/prod
 * 5. CONSTRAINT VIOLATIONS: Validar FK antes de insertar
 * 
 * ORDEN DE MIGRACIÓN CRÍTICO:
 * schools -> users/clients/monitors/sports/stations/degrees/seasons ->
 * pivot_relations -> courses -> course_groups/dates -> certifications ->
 * bookings -> booking_users/payments -> configuration
 */