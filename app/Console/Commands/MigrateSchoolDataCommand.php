<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\School;
use App\Models\Course;
use App\Models\Booking;
use App\Models\Season;
use Carbon\Carbon;

class MigrateSchoolDataCommand extends Command
{
    protected $signature = 'boukii:migrate-school 
                            {school_id : ID de la escuela a migrar}
                            {action : Acción a realizar (export|import|migrate-direct|validate)}
                            {--file= : Archivo para importar (requerido para import)}
                            {--source-db= : Base de datos origen (para migrate-direct)}
                            {--target-db= : Base de datos de destino (para import y migrate-direct)}
                            {--dst-school-id= : ID de la escuela destino existente (no crear nueva)}
                            {--dry-run : Solo mostrar qué se haría sin ejecutar}';

    protected $description = 'Migra datos de una escuela específica entre entornos (develop -> production)';

    private $schoolId;
    private $tables = [
        'direct' => [
            'schools',
            'seasons',
            'school_users',
            'school_sports', 
            'degrees',
            'school_colors',
            'school_salary_levels',
            'stations_schools',
            'clients_schools',
            'monitors_schools',
            'vouchers',
            'evaluations',
            // Tablas adicionales encontradas
            'client_observations',
            'discounts_codes', 
            'email_log',
            'feature_flag_history',
            'mails',
            'monitor_nwd',
            'monitor_observations', 
            'payments',
            'tasks'
        ],
        'courses' => [
            'courses',
            'course_dates',
            'course_extras', 
            'course_groups',
            'course_subgroups'
        ],
        'bookings' => [
            'bookings',
            'booking_users',
            'booking_user_extras',
            'booking_logs',
            'booking_payment_notice_log',
            'vouchers_log'
        ],
        // Nuevas categorías de relaciones indirectas
        'clients' => [
            'clients',  // Tabla principal de clientes
            'clients_sports',
            'clients_utilizers'
        ],
        'degrees' => [
            'degrees_school_sport_goals'
        ],
        'monitors' => [
            'monitors',  // Tabla principal de monitores
            'monitor_sports_degrees'
        ],
        'users' => [
            'users'
        ]
    ];

    public function handle()
    {
        $this->schoolId = $this->argument('school_id');
        $action = $this->argument('action');

        $this->info("🏫 Migración de datos de escuela - ID: {$this->schoolId}");
        $this->info("🔧 Acción: {$action}");
        $this->newLine();

        // Validar o crear escuela según la acción
        $school = School::find($this->schoolId);
        
        if ($action === 'import' && !$school) {
            // Para importación, buscar un ID disponible si la escuela no existe
            $availableId = $this->findAvailableSchoolId();
            $this->warn("⚠️  Escuela ID {$this->schoolId} no existe en destino.");
            $this->info("🆕 Se usará nuevo ID disponible: {$availableId}");
            $this->schoolId = $availableId;
        } elseif ($action !== 'import' && !$school) {
            $this->error("❌ Error: Escuela con ID {$this->schoolId} no encontrada");
            return 1;
        }

        $school = School::find($this->schoolId);
        if ($school) {
            $this->info("✅ Escuela encontrada: {$school->name}");
        }
        $this->newLine();

        switch ($action) {
            case 'export':
                return $this->exportSchoolData();
            case 'import':
                return $this->importSchoolData();
            case 'migrate-direct':
                return $this->migrateSchoolDataDirect();
            case 'validate':
                return $this->validateSchoolData();
            default:
                $this->error("❌ Acción no válida. Use: export, import, migrate-direct, validate");
                return 1;
        }
    }

    private function exportSchoolData()
    {
        $this->info("📤 Iniciando exportación de datos...");
        
        $filename = "school_{$this->schoolId}_export_" . Carbon::now()->format('Y-m-d_H-i-s') . ".json";
        $filepath = storage_path("app/exports/{$filename}");
        
        // Crear directorio si no existe
        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $exportData = [
            'metadata' => [
                'school_id' => $this->schoolId,
                'exported_at' => Carbon::now()->toISOString(),
                'version' => '1.0'
            ],
            'data' => []
        ];

        try {
            // Exportar tablas directas
            $this->line("📊 Exportando tablas directas...");
            foreach ($this->tables['direct'] as $table) {
                // Special handling for tables with different column names
                $column = 'school_id';
                if ($table === 'schools') {
                    $column = 'id';
                } elseif (in_array($table, ['evaluations'])) {
                    // Check if evaluations table has booking_id instead of school_id
                    try {
                        $testQuery = DB::table($table)->where('school_id', $this->schoolId)->limit(1)->get();
                        $column = 'school_id';
                    } catch (\Exception $e) {
                        // Skip this table if it doesn't have school_id
                        continue;
                    }
                }
                
                $records = $this->getTableRecords($table, $column, $this->schoolId);
                if (!empty($records)) {
                    $exportData['data'][$table] = $records;
                }
            }

            // Exportar cursos y datos relacionados
            $courseIds = $this->getCourseIds();
            if (!empty($courseIds)) {
                $this->line("📚 Exportando cursos y datos relacionados...");
                
                foreach ($this->tables['courses'] as $table) {
                    $column = $table === 'courses' ? 'school_id' : 'course_id';
                    $value = $table === 'courses' ? $this->schoolId : $courseIds;
                    
                    if ($table === 'course_subgroups') {
                        $groupIds = $this->getCourseGroupIds($courseIds);
                        $column = 'course_group_id';
                        $value = $groupIds;
                    }
                    
                    if (!empty($value)) {
                        $data = $this->exportTableData($table, $column, $value);
                        if (!empty($data)) {
                            $sqlContent[] = "-- Tabla: {$table}";
                            $sqlContent = array_merge($sqlContent, $data);
                            $sqlContent[] = "";
                        }
                    }
                }
            }

            // Exportar reservas y datos relacionados
            $bookingIds = $this->getBookingIds();
            if (!empty($bookingIds)) {
                $this->line("📋 Exportando reservas y datos relacionados...");
                
                foreach ($this->tables['bookings'] as $table) {
                    $column = $table === 'bookings' ? 'school_id' : 'booking_id';
                    $value = $table === 'bookings' ? $this->schoolId : $bookingIds;
                    
                    if ($table === 'booking_user_extras') {
                        $bookingUserIds = $this->getBookingUserIds($bookingIds);
                        $column = 'booking_user_id';
                        $value = $bookingUserIds;
                    }
                    
                    if (!empty($value)) {
                        $data = $this->exportTableData($table, $column, $value);
                        if (!empty($data)) {
                            $sqlContent[] = "-- Tabla: {$table}";
                            $sqlContent = array_merge($sqlContent, $data);
                            $sqlContent[] = "";
                        }
                    }
                }
            }

            // Exportar datos de clientes relacionados
            $clientIds = $this->getClientIds();
            if (!empty($clientIds)) {
                $this->line("👥 Exportando datos de clientes relacionados...");
                
                foreach ($this->tables['clients'] as $table) {
                    $data = $this->exportTableData($table, 'client_id', $clientIds);
                    if (!empty($data)) {
                        $sqlContent[] = "-- Tabla: {$table}";
                        $sqlContent = array_merge($sqlContent, $data);
                        $sqlContent[] = "";
                    }
                }
            }

            // Exportar datos de grados/niveles relacionados
            $degreeIds = $this->getDegreeIds();
            if (!empty($degreeIds)) {
                $this->line("🎓 Exportando datos de grados/niveles relacionados...");
                
                foreach ($this->tables['degrees'] as $table) {
                    $data = $this->exportTableData($table, 'degree_id', $degreeIds);
                    if (!empty($data)) {
                        $sqlContent[] = "-- Tabla: {$table}";
                        $sqlContent = array_merge($sqlContent, $data);
                        $sqlContent[] = "";
                    }
                }
            }

            // Exportar datos de monitores relacionados
            $monitorIds = $this->getMonitorIds();
            if (!empty($monitorIds)) {
                $this->line("🎯 Exportando datos de monitores relacionados...");
                
                foreach ($this->tables['monitors'] as $table) {
                    $data = $this->exportTableData($table, 'monitor_id', $monitorIds);
                    if (!empty($data)) {
                        $sqlContent[] = "-- Tabla: {$table}";
                        $sqlContent = array_merge($sqlContent, $data);
                        $sqlContent[] = "";
                    }
                }
            }

            $sqlContent[] = "COMMIT;";
            $sqlContent[] = "SET foreign_key_checks = 1;";
            $sqlContent[] = "";
            $sqlContent[] = "-- =====================================================";
            $sqlContent[] = "-- FIN DE EXPORTACIÓN";
            $sqlContent[] = "-- =====================================================";

            // Escribir archivo
            file_put_contents($filepath, implode("\n", $sqlContent));

            $this->info("✅ Exportación completada");
            $this->info("📁 Archivo: {$filepath}");
            $this->info("📏 Tamaño: " . $this->formatBytes(filesize($filepath)));
            
            // Mostrar estadísticas
            $this->showExportStats();

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la exportación: " . $e->getMessage());
            return 1;
        }
    }

    private function importSchoolData()
    {
        $file = $this->option('file');
        if (!$file) {
            $this->error("❌ Error: Se requiere especificar el archivo con --file");
            return 1;
        }

        if (!file_exists($file)) {
            $this->error("❌ Error: Archivo no encontrado: {$file}");
            return 1;
        }

        $targetDb = $this->option('target-db') ?: config('database.default');
        
        $this->info("📥 Iniciando importación...");
        $this->info("📁 Archivo: {$file}");
        $this->info("🗄️  Base de datos destino: {$targetDb}");

        if ($this->option('dry-run')) {
            $this->warn("🚧 MODO DRY-RUN - No se ejecutarán cambios");
        }

        // Confirmar acción
        if (!$this->option('dry-run')) {
            if (!$this->confirm("⚠️  ¿Confirmas la importación a {$targetDb}?")) {
                $this->info("❌ Importación cancelada");
                return 0;
            }
        }

        try {
            $sqlContent = file_get_contents($file);
            
            // Si se asignó un nuevo ID, necesitamos encontrar el ID real del archivo
            $originalSchoolId = $this->argument('school_id');
            if ($this->schoolId != $originalSchoolId) {
                // Extraer el ID real del contenido SQL (de los comentarios)
                preg_match('/EXPORTACIÓN DE DATOS DE ESCUELA ID:\s*(\d+)/', $sqlContent, $matches);
                $realSchoolId = $matches[1] ?? $originalSchoolId;
                
                $this->info("🔄 Actualizando IDs en archivo SQL: {$realSchoolId} → {$this->schoolId}");
                $sqlContent = $this->updateSchoolIdsInSQL($sqlContent, $realSchoolId, $this->schoolId);
            }
            
            if (!$this->option('dry-run')) {
                // Crear backup antes de importar
                $this->createBackup($targetDb);
                
                // Importar usando Eloquent updateOrCreate en lugar de SQL directo
                $this->importWithEloquent($sqlContent, $targetDb);
                
                $this->info("✅ Importación completada exitosamente");
                
                // Validar datos importados
                $this->validateImportedData($targetDb);
            } else {
                $this->info("🔍 Archivo SQL válido y listo para importar");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Error durante la importación: " . $e->getMessage());
            return 1;
        }
    }

    private function validateSchoolData()
    {
        $this->info("🔍 Validando integridad de datos de la escuela...");

        $school = School::find($this->schoolId);
        
        // Validaciones básicas
        $stats = [
            'courses' => Course::where('school_id', $this->schoolId)->count(),
            'bookings' => Booking::where('school_id', $this->schoolId)->count(),
            'seasons' => Season::where('school_id', $this->schoolId)->count(),
            'users' => DB::table('school_users')->where('school_id', $this->schoolId)->count(),
            'clients' => DB::table('clients_schools')->where('school_id', $this->schoolId)->count(),
        ];

        $this->info("📊 Estadísticas de la escuela:");
        $this->table(
            ['Elemento', 'Cantidad'],
            [
                ['Escuela', $school->name],
                ['Cursos', $stats['courses']],
                ['Reservas', $stats['bookings']],
                ['Temporadas', $stats['seasons']],
                ['Usuarios asociados', $stats['users']],
                ['Clientes asociados', $stats['clients']],
            ]
        );

        // Validar integridad referencial
        $issues = [];
        
        // Verificar cursos huérfanos
        $orphanCourses = DB::table('course_dates')
            ->leftJoin('courses', 'course_dates.course_id', '=', 'courses.id')
            ->whereNull('courses.id')
            ->count();
        
        if ($orphanCourses > 0) {
            $issues[] = "⚠️  {$orphanCourses} fechas de curso sin curso padre";
        }

        // Verificar reservas huérfanas
        $orphanBookings = DB::table('booking_users')
            ->leftJoin('bookings', 'booking_users.booking_id', '=', 'bookings.id')
            ->whereNull('bookings.id')
            ->count();
        
        if ($orphanBookings > 0) {
            $issues[] = "⚠️  {$orphanBookings} usuarios de reserva sin reserva padre";
        }

        if (empty($issues)) {
            $this->info("✅ No se encontraron problemas de integridad");
        } else {
            $this->warn("⚠️  Problemas encontrados:");
            foreach ($issues as $issue) {
                $this->line("   {$issue}");
            }
        }

        return 0;
    }

    // Métodos auxiliares
    private function exportTableData($table, $column, $value)
    {
        try {
            if (is_array($value)) {
                $whereClause = "{$column} IN (" . implode(',', $value) . ")";
            } else {
                $whereClause = "{$column} = {$value}";
            }

            $rows = DB::table($table)->whereRaw($whereClause)->get();
            
            if ($rows->isEmpty()) {
                return [];
            }

            $inserts = [];
            $columns = array_keys((array) $rows->first());
            $columnsList = '`' . implode('`, `', $columns) . '`';

            foreach ($rows as $row) {
                $values = [];
                foreach ($columns as $col) {
                    $val = $row->$col;
                    if (is_null($val)) {
                        $values[] = 'NULL';
                    } elseif (is_string($val)) {
                        $values[] = "'" . addslashes($val) . "'";
                    } else {
                        $values[] = $val;
                    }
                }
                $inserts[] = "INSERT INTO `{$table}` ({$columnsList}) VALUES (" . implode(', ', $values) . ");";
            }

            return $inserts;

        } catch (\Exception $e) {
            $this->warn("⚠️  Error exportando tabla {$table}: " . $e->getMessage());
            return [];
        }
    }

    private function getCourseIds()
    {
        return Course::where('school_id', $this->schoolId)->pluck('id')->toArray();
    }

    private function getBookingIds()
    {
        return Booking::where('school_id', $this->schoolId)->pluck('id')->toArray();
    }

    private function getCourseGroupIds($courseIds)
    {
        if (empty($courseIds)) return [];
        return DB::table('course_groups')->whereIn('course_id', $courseIds)->pluck('id')->toArray();
    }

    private function getBookingUserIds($bookingIds)
    {
        if (empty($bookingIds)) return [];
        return DB::table('booking_users')->whereIn('booking_id', $bookingIds)->pluck('id')->toArray();
    }

    private function getClientIds()
    {
        return DB::table('clients_schools')->where('school_id', $this->schoolId)->pluck('client_id')->toArray();
    }

    private function getDegreeIds()
    {
        return DB::table('degrees')->where('school_id', $this->schoolId)->pluck('id')->toArray();
    }

    private function getMonitorIds()
    {
        // Obtener IDs únicos de monitores asociados a la escuela
        return DB::table('monitors_schools')
            ->where('school_id', $this->schoolId)
            ->distinct()
            ->pluck('monitor_id')
            ->toArray();
    }

    private function findAvailableSchoolId($targetDb = null)
    {
        // Buscar el siguiente ID disponible secuencial
        // Obtener el ID máximo actual + 1
        $connection = $targetDb ? DB::connection($targetDb) : DB::connection();
        $maxId = $connection->table('schools')->max('id');
        $nextId = $maxId ? $maxId + 1 : 1;
        
        // Verificar que realmente esté disponible (por si hay gaps)
        while ($connection->table('schools')->where('id', $nextId)->exists()) {
            $nextId++;
            
            // Evitar bucle infinito
            if ($nextId > 99999) {
                throw new \Exception("No se pudo encontrar un ID de escuela disponible");
            }
        }
        
        return $nextId;
    }

    private function updateSchoolIdsInSQL($sqlContent, $oldId, $newId)
    {
        $this->line("🔍 Reemplazando todas las referencias de ID {$oldId} por {$newId}...");
        
        // Método más robusto: reemplazar TODAS las ocurrencias de oldId que estén en contexto de base de datos
        // Patrones específicos en orden de importancia
        
        $patterns = [
            // 1. Comentarios y headers
            "/EXPORTACIÓN DE DATOS DE ESCUELA ID:\s*{$oldId}/i" => "EXPORTACIÓN DE DATOS DE ESCUELA ID: {$newId}",
            
            // 2. En tabla schools - VALUES (13, ...) - más específico
            "/(INSERT INTO `schools`[^V]*VALUES\s*\(\s*){$oldId}(\s*,)/i" => "\${1}{$newId}\${2}",
            
            // 3. school_id con diferentes formatos
            "/school_id\s*=\s*{$oldId}([^\d])/i" => "school_id = {$newId}\${1}",
            "/(`school_id`,\s*){$oldId}([,\)])/i" => "\${1}{$newId}\${2}",
            
            // 4. En contexto de INSERT VALUES - más agresivo pero seguro
            "/(\(\s*\d+,\s*\d+,\s*){$oldId}([,\s])/i" => "\${1}{$newId}\${2}",
            "/(\(\s*\d+,\s*){$oldId}([,\s])/i" => "\${1}{$newId}\${2}",
            
            // 5. En cualquier contexto VALUES entre comas o paréntesis
            "/,\s*{$oldId}\s*,/i" => ", {$newId},",
            "/\(\s*{$oldId}\s*,/i" => "({$newId},",
            "/,\s*{$oldId}\s*\)/i" => ", {$newId})",
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $before = substr_count($sqlContent, (string)$oldId);
            $sqlContent = preg_replace($pattern, $replacement, $sqlContent);
            $after = substr_count($sqlContent, (string)$oldId);
            
            if ($before != $after) {
                $this->line("  ✓ Patrón aplicado: " . ($before - $after) . " reemplazos");
            }
        }
        
        // Verificación final
        $remainingOccurrences = substr_count($sqlContent, (string)$oldId);
        if ($remainingOccurrences > 0) {
            $this->warn("⚠️  Aún quedan {$remainingOccurrences} ocurrencias de {$oldId} sin reemplazar");
        } else {
            $this->info("✅ Todas las referencias de {$oldId} han sido reemplazadas por {$newId}");
        }
        
        return $sqlContent;
    }

    private array $idMappings = [];
    
    private function importWithEloquent($sqlContent, $targetDb)
    {
        $this->info("🔄 Importando datos usando Eloquent updateOrCreate...");
        
        // Parsear el contenido SQL para extraer los INSERTs
        $insertStatements = $this->parseSqlInserts($sqlContent);
        
        DB::connection($targetDb)->transaction(function () use ($insertStatements) {
            // Ordenar tablas por dependencias - las principales primero
            $orderedTables = $this->getTableImportOrder($insertStatements);
            
            foreach ($orderedTables as $tableName) {
                if (!isset($insertStatements[$tableName])) continue;
                
                $records = $insertStatements[$tableName];
                $recordCount = count($records);
                $this->line("📊 Importando tabla: {$tableName} ({$recordCount} registros)");
                
                foreach ($records as $record) {
                    $this->importRecordWithEloquent($tableName, $record);
                }
            }
        });
    }
    
    private function parseSqlInserts($sqlContent)
    {
        $this->line("🔍 Parseando statements SQL...");
        $insertStatements = [];
        
        // Extraer todos los INSERT INTO statements usando una regex mejorada para multilinea
        preg_match_all('/INSERT INTO `(\w+)`\s*\(([^)]+)\)\s*VALUES\s*\((.*?)\);/is', $sqlContent, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $tableName = $match[1];
            $columns = array_map('trim', explode(',', str_replace('`', '', $match[2])));
            $valuesString = $match[3];
            
            // Parsear valores (handling complex cases like JSON)
            $values = $this->parseInsertValues($valuesString);
            
            // Debug información si hay mismatch
            if (count($columns) !== count($values)) {
                $this->warn("⚠️ Mismatch en $tableName: " . count($columns) . " columnas vs " . count($values) . " valores");
                $this->line("Columnas: " . json_encode(array_slice($columns, 0, 5)));
                $this->line("Valores: " . json_encode(array_slice($values, 0, 5)));
                continue;
            }
            
            if (!isset($insertStatements[$tableName])) {
                $insertStatements[$tableName] = [];
            }
            
            $insertStatements[$tableName][] = array_combine($columns, $values);
        }
        
        $this->line("✅ Parseados " . array_sum(array_map('count', $insertStatements)) . " registros de " . count($insertStatements) . " tablas");
        
        return $insertStatements;
    }
    
    private function parseInsertValues($valuesString)
    {
        $values = [];
        $current = '';
        $inQuotes = false;
        $quoteChar = '';
        $parenthesesLevel = 0;
        
        for ($i = 0; $i < strlen($valuesString); $i++) {
            $char = $valuesString[$i];
            
            if (!$inQuotes && ($char === '"' || $char === "'")) {
                $inQuotes = true;
                $quoteChar = $char;
                $current .= $char;
            } elseif ($inQuotes && $char === $quoteChar && ($i === 0 || $valuesString[$i-1] !== '\\')) {
                $inQuotes = false;
                $current .= $char;
            } elseif (!$inQuotes && $char === '(') {
                $parenthesesLevel++;
                $current .= $char;
            } elseif (!$inQuotes && $char === ')') {
                $parenthesesLevel--;
                $current .= $char;
            } elseif (!$inQuotes && $char === ',' && $parenthesesLevel === 0) {
                $values[] = $this->cleanValue(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }
        
        if ($current !== '') {
            $values[] = $this->cleanValue(trim($current));
        }
        
        return $values;
    }
    
    private function cleanValue($value)
    {
        $value = trim($value);
        
        // Handle NULL
        if (strtoupper($value) === 'NULL') {
            return null;
        }
        
        // Handle quoted strings
        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) || 
            (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            return substr($value, 1, -1);
        }
        
        // Handle numbers
        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }
        
        return $value;
    }
    
    private function importRecordWithEloquent($tableName, $record)
    {
        // Configurar campos únicos para cada tabla
        $uniqueFields = $this->getUniqueFieldsForTable($tableName);
        
        // Mapear foreign keys a nuevos IDs antes de insertar
        $record = $this->mapForeignKeysInRecord($tableName, $record);
        
        // Caso especial para tabla schools - siempre crear nueva con ID específico
        if ($tableName === 'schools') {
            // Para schools, mantener el ID y crear registro único
            $originalSchoolId = $record['id'];
            DB::table($tableName)->insert($record);
            // Para schools usamos el ID que especificamos (ya modificado por updateSchoolIdsInSQL)
            $this->idMappings['schools'][$originalSchoolId] = $record['id'];
            return;
        }
        
        // Remover el campo ID del registro para evitar conflictos en otras tablas
        $originalId = $record['id'] ?? null;
        $recordWithoutId = $record;
        unset($recordWithoutId['id']);
        
        if (empty($uniqueFields)) {
            // Si no hay campos únicos definidos, crear sin ID fijo
            $insertedId = DB::table($tableName)->insertGetId($recordWithoutId);
        } else {
            // Separar campos únicos de campos actualizables
            $uniqueData = array_intersect_key($recordWithoutId, array_flip($uniqueFields));
            $updateData = array_diff_key($recordWithoutId, $uniqueData);
            
            // Solo usar updateOrInsert si hay campos únicos válidos
            if (!empty($uniqueData) && !in_array('id', $uniqueFields)) {
                // updateOrInsert para registros identificables por otros campos
                DB::table($tableName)->updateOrInsert($uniqueData, $updateData);
                // Obtener el ID del registro insertado/actualizado
                $insertedId = DB::table($tableName)->where($uniqueData)->value('id');
            } else {
                // Para tablas donde ID es el único campo único, usar insert sin ID
                $insertedId = DB::table($tableName)->insertGetId($recordWithoutId);
            }
        }
        
        // Guardar mapeo de ID original → ID nuevo
        if ($originalId && isset($insertedId)) {
            $this->idMappings[$tableName][$originalId] = $insertedId;
        }
    }
    
    private function mapForeignKeysInRecord($tableName, $record)
    {
        // Definir mapeos de foreign keys por tabla
        $foreignKeyMappings = [
            // Relaciones con schools
            'seasons' => ['school_id' => 'schools'],
            'school_users' => ['school_id' => 'schools', 'user_id' => 'users'],
            'degrees' => ['school_id' => 'schools'],
            
            // Relaciones con users
            'clients' => ['user_id' => 'users'],
            'monitors' => ['user_id' => 'users'],
            
            // Relaciones con courses
            'courses' => ['school_id' => 'schools', 'season_id' => 'seasons'],
            'course_dates' => ['course_id' => 'courses'],
            'course_groups' => ['course_id' => 'courses', 'course_date_id' => 'course_dates'],
            'course_extras' => ['course_id' => 'courses'],
            'course_subgroups' => ['course_group_id' => 'course_groups'],
            
            // Relaciones con bookings (ajustado a esquema actual: client_main_id)
            'bookings' => ['school_id' => 'schools', 'client_main_id' => 'clients', 'user_id' => 'users'],
            'booking_users' => [
                'booking_id' => 'bookings',
                'client_id' => 'clients',
                'course_id' => 'courses',
                'monitor_id' => 'monitors',
                'course_group_id' => 'course_groups',
                'course_date_id' => 'course_dates',
                'degree_id' => 'degrees',
                'school_id' => 'schools',
            ],
            'booking_user_extras' => ['booking_user_id' => 'booking_users'],
            'booking_logs' => ['booking_id' => 'bookings', 'user_id' => 'users'],
            'booking_payment_notice_log' => ['booking_id' => 'bookings'],
            'vouchers_log' => ['booking_id' => 'bookings'],
            'payments' => ['booking_id' => 'bookings'],
            
            // Relaciones con clients y monitors  
            'clients_schools' => ['school_id' => 'schools', 'client_id' => 'clients'],
            'monitors_schools' => ['school_id' => 'schools', 'monitor_id' => 'monitors'],
            'clients_sports' => ['client_id' => 'clients'],
            'monitor_sports_degrees' => ['monitor_id' => 'monitors'],
        ];
        
        if (!isset($foreignKeyMappings[$tableName])) {
            return $record;
        }
        
        foreach ($foreignKeyMappings[$tableName] as $foreignKey => $referencedTable) {
            if (isset($record[$foreignKey]) && isset($this->idMappings[$referencedTable][$record[$foreignKey]])) {
                $oldValue = $record[$foreignKey];
                $newValue = $this->idMappings[$referencedTable][$record[$foreignKey]];
                $record[$foreignKey] = $newValue;
                $this->line("  🔄 Mapeado {$foreignKey}: {$oldValue} → {$newValue}");
            }
        }
        
        return $record;
    }
    
    private function getUniqueFieldsForTable($tableName)
    {
        // Definir campos únicos para identificar registros existentes (evitar usar 'id' como única clave)
        $uniqueFields = [
            'schools' => ['name', 'slug'], // Identificar por nombre/slug en lugar de ID
            'seasons' => ['school_id', 'name'],
            'courses' => ['school_id', 'name', 'season_id'],
            // Forzar inserción de bookings para evitar colisiones y mapear IDs correctamente
            'bookings' => [],
            'school_users' => ['school_id', 'user_id'],
            'school_sports' => ['school_id', 'sport_id'],
            'degrees' => ['school_id', 'name', 'sport_id'],
            'clients_schools' => ['client_id', 'school_id'],
            'monitors_schools' => ['monitor_id', 'school_id'],
            'vouchers' => ['code', 'school_id'],
            'payments' => ['booking_id', 'amount', 'payment_date'],
            'course_dates' => ['course_id', 'date', 'hour_start'],
            'course_groups' => ['course_id', 'name'],
            // Evitar duplicados de participantes por actividad
            'booking_users' => ['booking_id', 'client_id', 'course_id'],
            'monitor_sports_degrees' => ['monitor_id', 'school_id', 'sport_id', 'degree_id'],
        ];
        
        return $uniqueFields[$tableName] ?? []; // Vacío para que use insert sin ID
    }
    
    private function getTableImportOrder($insertStatements)
    {
        // Definir orden de importación por dependencias
        $order = [
            // 1. Tablas independientes primero
            'schools',
            'sports', 
            
            // 2. Tablas que dependen de schools
            'seasons',
            'school_users',
            'school_sports', 
            'school_colors',
            'school_salary_levels',
            'stations_schools',
            
            // 3. Tablas que dependen de seasons/sports
            'degrees',
            'courses',
            
            // 4. Tablas que dependen de courses
            'course_dates',
            'course_groups',
            'course_extras',
            
            // 5. Tablas que dependen de course_groups
            'course_subgroups',
            
            // 6. Reservas y relacionados
            'bookings',
            'booking_users',
            'booking_user_extras',
            'booking_logs',
            'booking_payment_notice_log',
            
            // 7. Clientes y monitores
            'clients_schools',
            'monitors_schools',
            
            // 8. Tablas relacionadas con clientes
            'clients_sports',
            'clients_utilizers',
            'client_observations',
            
            // 9. Tablas relacionadas con monitores
            'monitor_sports_degrees',
            'monitor_nwd',
            'monitor_observations',
            
            // 10. Otras tablas
            'vouchers',
            'vouchers_log',
            'payments',
            'tasks',
            'evaluations',
            'degrees_school_sport_goals',
            'mails',
            'email_log',
            'discounts_codes',
        ];
        
        // Filtrar solo las tablas que realmente están en los datos
        $availableTables = array_keys($insertStatements);
        $orderedAvailable = [];
        
        // Primero agregar tablas en orden de prioridad
        foreach ($order as $table) {
            if (in_array($table, $availableTables)) {
                $orderedAvailable[] = $table;
            }
        }
        
        // Luego agregar cualquier tabla que no esté en el orden definido
        foreach ($availableTables as $table) {
            if (!in_array($table, $orderedAvailable)) {
                $orderedAvailable[] = $table;
            }
        }
        
        return $orderedAvailable;
    }

    private function showExportStats()
    {
        $stats = [
            ['Cursos', count($this->getCourseIds())],
            ['Reservas', count($this->getBookingIds())],
            ['Temporadas', Season::where('school_id', $this->schoolId)->count()],
            ['Clientes asociados', count($this->getClientIds())],
            ['Monitores asociados', count($this->getMonitorIds())],
            ['Grados/Niveles', count($this->getDegreeIds())],
        ];

        // Estadísticas adicionales de tablas encontradas
        $additionalStats = [];
        $additionalTables = [
            'client_observations' => 'Observaciones de clientes',
            'discounts_codes' => 'Códigos de descuento', 
            'email_log' => 'Logs de email',
            'feature_flag_history' => 'Historial feature flags',
            'mails' => 'Emails enviados',
            'monitor_nwd' => 'NWD de monitores',
            'monitor_observations' => 'Observaciones monitores',
            'payments' => 'Pagos',
            'tasks' => 'Tareas',
            'vouchers' => 'Vouchers',
            'evaluations' => 'Evaluaciones',
            'school_users' => 'Usuarios asociados',
            'school_sports' => 'Deportes',
            'school_colors' => 'Colores escuela',
            'school_salary_levels' => 'Niveles salario',
            'stations_schools' => 'Estaciones asociadas'
        ];

        foreach ($additionalTables as $table => $description) {
            try {
                $count = DB::table($table)->where('school_id', $this->schoolId)->count();
                if ($count > 0) {
                    $additionalStats[] = [$description, $count];
                }
            } catch (\Exception $e) {
                // Skip if table doesn't exist
            }
        }

        // Estadísticas de relaciones indirectas
        $clientIds = $this->getClientIds();
        $degreeIds = $this->getDegreeIds();
        $monitorIds = $this->getMonitorIds();
        
        if (!empty($clientIds)) {
            try {
                $clientSports = DB::table('clients_sports')->whereIn('client_id', $clientIds)->count();
                if ($clientSports > 0) $additionalStats[] = ['Deportes de clientes', $clientSports];
                
                $clientUtilizers = DB::table('clients_utilizers')->whereIn('client_id', $clientIds)->count();
                if ($clientUtilizers > 0) $additionalStats[] = ['Utilizadores de clientes', $clientUtilizers];
            } catch (\Exception $e) {}
        }

        if (!empty($degreeIds)) {
            try {
                $degreeGoals = DB::table('degrees_school_sport_goals')->whereIn('degree_id', $degreeIds)->count();
                if ($degreeGoals > 0) $additionalStats[] = ['Objetivos por grado', $degreeGoals];
            } catch (\Exception $e) {}
        }

        if (!empty($monitorIds)) {
            try {
                $monitorDegrees = DB::table('monitor_sports_degrees')->whereIn('monitor_id', $monitorIds)->count();
                if ($monitorDegrees > 0) $additionalStats[] = ['Grados de monitores', $monitorDegrees];
            } catch (\Exception $e) {
                // Tabla no existe o error de estructura
            }
        }

        $this->newLine();
        $this->info("📈 Estadísticas de exportación:");
        $this->table(['Elemento', 'Cantidad'], array_merge($stats, $additionalStats));
    }

    private function createBackup($database)
    {
        $backupFile = storage_path("app/backups/backup_before_import_" . Carbon::now()->format('Y-m-d_H-i-s') . ".sql");
        
        if (!is_dir(dirname($backupFile))) {
            mkdir(dirname($backupFile), 0755, true);
        }

        $this->info("💾 Creando backup de seguridad...");
        
        // Crear backup solo de las tablas que se van a modificar
        $tables = array_merge($this->tables['direct'], $this->tables['courses'], $this->tables['bookings']);
        
        foreach ($tables as $table) {
            try {
                $data = $this->exportTableData($table, 'school_id', $this->schoolId);
                if (!empty($data)) {
                    file_put_contents($backupFile, "-- Tabla: {$table}\n" . implode("\n", $data) . "\n\n", FILE_APPEND);
                }
            } catch (\Exception $e) {
                // Continuar con otras tablas
            }
        }
        
        $this->info("✅ Backup creado: {$backupFile}");
    }

    private function validateImportedData($database)
    {
        $this->info("🔍 Validando datos importados...");
        
        $school = DB::connection($database)->table('schools')->find($this->schoolId);
        
        if ($school) {
            $this->info("✅ Escuela importada correctamente: {$school->name}");
        } else {
            $this->error("❌ Error: Escuela no encontrada después de la importación");
        }
    }

    private function migrateSchoolDataDirect()
    {
        $this->info("🚀 Iniciando migración directa de datos...");
        
        $targetDb = $this->option('target-db') ?? 'boukii_pro';
        $sourceDb = $this->option('source-db') ?? $this->getDefaultConnection();
        
        $this->info("📤 Base de datos origen: {$sourceDb}");
        $this->info("📥 Base de datos destino: {$targetDb}");
        
        // Confirmar la operación
        if (!$this->confirm("⚠️  ¿Confirmas la migración directa a {$targetDb}?", false)) {
            $this->info("❌ Migración cancelada");
            return 0;
        }

        try {
            // Crear backup de seguridad
            $this->info("💾 Creando backup de seguridad...");
            $this->createBackup($targetDb);
            
            // Migrar datos directamente
            $this->info("🔄 Migrando datos directamente entre bases de datos...");
            
            DB::beginTransaction();
            
            $this->importWithEloquentDirect($sourceDb, $targetDb);
            
            DB::commit();
            
            $this->info("✅ Migración directa completada exitosamente");
            
            // Validar datos importados
            $this->validateImportedData($targetDb);
            
            return 0;
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("❌ Error durante la migración directa: " . $e->getMessage());
            return 1;
        }
    }

    private function importWithEloquentDirect($sourceDb, $targetDb)
    {
        $this->line("🔄 Importando datos usando migración directa...");
        
        // Obtener school_id destino (existente) o generar uno nuevo
        $optionDstSchoolId = $this->option('dst-school-id');
        if ($optionDstSchoolId) {
            $exists = DB::connection($targetDb)->table('schools')->where('id', $optionDstSchoolId)->exists();
            if (!$exists) {
                throw new \Exception("Escuela destino ID {$optionDstSchoolId} no existe en {$targetDb}");
            }
            $targetSchoolId = (int) $optionDstSchoolId;
            $this->info("🎯 Usando escuela destino existente ID: {$targetSchoolId}");
        } else {
            $targetSchoolId = $this->findAvailableSchoolId($targetDb);
            $this->info("🆕 Nueva escuela tendrá ID: {$targetSchoolId}");
        }
        
        // Mapeo de IDs originales a nuevos IDs
        $this->idMappings = [
            'schools' => [$this->schoolId => $targetSchoolId],
            'users' => [],
            'clients' => [],
            'monitors' => [],
            'courses' => [],
            'bookings' => [],
            'seasons' => []
        ];
        
        // Migrar escuela primero (solo si no se especifica una existente)
        $school = DB::connection($sourceDb)->table('schools')->where('id', $this->schoolId)->first();
        if ($school && !$optionDstSchoolId) {
            $schoolData = (array) $school;
            $schoolData['id'] = $targetSchoolId;
            DB::connection($targetDb)->table('schools')->insert($schoolData);
            $this->line("✅ Escuela migrada con ID: {$targetSchoolId}");
        }
        
        // Migrar todas las tablas relacionadas en orden lógico de dependencias
        // 1) users -> 2) clients/monitors -> 3) direct (schools/seasons/etc) -> 4) degrees -> 5) courses -> 6) bookings
        $orderedCategories = ['users', 'clients', 'monitors', 'direct', 'degrees', 'courses', 'bookings'];
        
        foreach ($orderedCategories as $category) {
            if (!isset($this->tables[$category])) continue;
            
            foreach ($this->tables[$category] as $table) {
                if ($table === 'schools') continue; // Ya migrada
                
                $this->migrateTableDirect($table, $sourceDb, $targetDb, $category);
            }
        }
        
        $this->info("✅ Migración directa de todas las tablas completada");
    }

    private function migrateTableDirect($tableName, $sourceDb, $targetDb, $category)
    {
        try {
            $records = $this->getRecordsForTable($tableName, $sourceDb, $category);
            
            if (empty($records)) {
                return;
            }
            
            foreach ($records as $record) {
                $recordArray = (array) $record;
                
                // Para usuarios, clientes y monitores, crear mapeo de IDs
                if (in_array($tableName, ['users', 'clients', 'monitors'])) {
                    $originalId = $recordArray['id'];
                    unset($recordArray['id']); // Dejar que sea autoincremental
                    
                    // Aplicar mapeo de foreign keys antes de insertar
                    $recordArray = $this->mapForeignKeysInRecord($tableName, $recordArray);
                    
                    // Usar updateOrInsert con campos únicos
                    $uniqueFields = $this->getUniqueFields($tableName);
                    $uniqueData = array_intersect_key($recordArray, array_flip($uniqueFields));
                    
                    // Verificar si ya existe
                    $existing = DB::connection($targetDb)->table($tableName)->where($uniqueData)->first();
                    
                    if ($existing) {
                        $newId = $existing->id;
                    } else {
                        $newId = DB::connection($targetDb)->table($tableName)->insertGetId($recordArray);
                    }
                    
                    // Mapear el ID
                    $this->idMappings[$tableName][$originalId] = $newId;
                    $this->line("  🔄 Mapeado {$tableName}_id: {$originalId} → {$newId}");
                    
                } else {
                    // Para otras tablas, aplicar mapeo de foreign keys
                    $recordArray = $this->mapForeignKeysInRecord($tableName, $recordArray);
                    $originalId = $recordArray['id'] ?? null;
                    // Para tablas con autoincrement, remover el ID original
                    if (isset($recordArray['id']) && !in_array($tableName, ['schools'])) {
                        unset($recordArray['id']);
                    }
                    
                    // Usar updateOrCreate para evitar duplicados (calcular campos únicos DESPUÉS del mapeo)
                    $uniqueFields = $this->getUniqueFields($tableName);
                    $uniqueData = array_intersect_key($recordArray, array_flip($uniqueFields));
                    $newId = null;
                    if (!empty($uniqueData)) {
                        DB::connection($targetDb)->table($tableName)->updateOrInsert($uniqueData, $recordArray);
                        $newId = DB::connection($targetDb)->table($tableName)->where($uniqueData)->value('id');
                    } else {
                        // Si no hay campos únicos, insertar directamente
                        $newId = DB::connection($targetDb)->table($tableName)->insertGetId($recordArray);
                    }

                    // Registrar mapeo de IDs para tablas con referencias posteriores
                    if ($originalId && $newId) {
                        if (!isset($this->idMappings[$tableName])) {
                            $this->idMappings[$tableName] = [];
                        }
                        $this->idMappings[$tableName][$originalId] = $newId;
                        $this->line("  🔄 Mapeado {$tableName}_id: {$originalId} → {$newId}");
                    }
                }
            }
            
            $this->line("✅ Migrada tabla {$tableName}: " . count($records) . " registros");
            
        } catch (\Exception $e) {
            $this->warn("⚠️ Error migrando tabla {$tableName}: " . $e->getMessage());
        }
    }

    private function getRecordsForTable($tableName, $sourceDb, $category)
    {
        $column = 'school_id';
        $value = $this->schoolId;
        
        // Obtener registros según la categoría
        switch ($category) {
            case 'users':
                // Obtener todos los user_id relacionados con esta escuela
                $userIds = $this->getAllRelatedUserIds($sourceDb);
                if (empty($userIds)) return [];
                
                $column = 'id';
                $value = $userIds;
                break;
                
            case 'direct':
                if ($tableName === 'schools') {
                    $column = 'id';
                }
                break;
                
            case 'courses':
                if ($tableName === 'courses') {
                    $column = 'school_id';
                } else {
                    $courseIds = DB::connection($sourceDb)->table('courses')
                        ->where('school_id', $this->schoolId)->pluck('id')->toArray();
                    if (empty($courseIds)) return [];
                    
                    $column = 'course_id';
                    $value = $courseIds;
                    
                    if ($tableName === 'course_subgroups') {
                        $groupIds = DB::connection($sourceDb)->table('course_groups')
                            ->whereIn('course_id', $courseIds)->pluck('id')->toArray();
                        if (empty($groupIds)) return [];
                        
                        $column = 'course_group_id';
                        $value = $groupIds;
                    }
                }
                break;
                
            case 'bookings':
                if ($tableName === 'bookings') {
                    $column = 'school_id';
                } else {
                    $bookingIds = DB::connection($sourceDb)->table('bookings')
                        ->where('school_id', $this->schoolId)->pluck('id')->toArray();
                    if (empty($bookingIds)) return [];
                    
                    $column = 'booking_id';
                    $value = $bookingIds;
                    
                    if ($tableName === 'booking_user_extras') {
                        $bookingUserIds = DB::connection($sourceDb)->table('booking_users')
                            ->whereIn('booking_id', $bookingIds)->pluck('id')->toArray();
                        if (empty($bookingUserIds)) return [];
                        
                        $column = 'booking_user_id';
                        $value = $bookingUserIds;
                    }
                }
                break;
                
            case 'clients':
                if ($tableName === 'clients') {
                    // Obtener clientes relacionados con la escuela
                    $clientIds = DB::connection($sourceDb)->table('clients_schools')
                        ->where('school_id', $this->schoolId)->pluck('client_id')->toArray();
                    if (empty($clientIds)) return [];
                    
                    $column = 'id';
                    $value = $clientIds;
                } else {
                    // Para otras tablas de clients, usar client_id
                    $clientIds = DB::connection($sourceDb)->table('clients_schools')
                        ->where('school_id', $this->schoolId)->pluck('client_id')->toArray();
                    if (empty($clientIds)) return [];
                    
                    $column = 'client_id';
                    $value = $clientIds;
                }
                break;
                
            case 'monitors':
                if ($tableName === 'monitors') {
                    // Obtener monitores relacionados con la escuela
                    $monitorIds = DB::connection($sourceDb)->table('monitors_schools')
                        ->where('school_id', $this->schoolId)->pluck('monitor_id')->toArray();
                    if (empty($monitorIds)) return [];
                    
                    $column = 'id';
                    $value = $monitorIds;
                } else {
                    // Para otras tablas de monitors, usar monitor_id
                    $monitorIds = DB::connection($sourceDb)->table('monitors_schools')
                        ->where('school_id', $this->schoolId)->pluck('monitor_id')->toArray();
                    if (empty($monitorIds)) return [];
                    
                    $column = 'monitor_id';
                    $value = $monitorIds;
                }
                break;
        }
        
        // Ejecutar consulta
        if (is_array($value)) {
            return DB::connection($sourceDb)->table($tableName)->whereIn($column, $value)->get();
        } else {
            return DB::connection($sourceDb)->table($tableName)->where($column, $value)->get();
        }
    }

    private function getDefaultConnection()
    {
        return config('database.default');
    }

    private function getUniqueFields($tableName)
    {
        // Define unique fields for each table to use with updateOrInsert
        $uniqueFields = [
            'schools' => ['id'],
            'users' => ['email'], // Los usuarios son únicos por email
            'clients' => ['email'], // Los clientes también por email
            'monitors' => ['email'], // Los monitores también por email
            'seasons' => ['school_id', 'name'],
            'school_users' => ['school_id', 'user_id'],
            'school_sports' => ['school_id', 'sport_id'],
            'degrees' => ['school_id', 'sport_id', 'level'],
            'school_colors' => ['school_id', 'color'],
            'school_salary_levels' => ['school_id', 'level'],
            'courses' => ['school_id', 'name', 'season_id'],
            // Insertar bookings siempre para preservar mapping de IDs
            'bookings' => [],
            'clients_schools' => ['client_id', 'school_id'],
            'monitors_schools' => ['monitor_id', 'school_id'],
            // Evitar duplicados de participantes por actividad
            'booking_users' => ['booking_id', 'client_id', 'course_id'],
            'default' => ['id']
        ];

        return $uniqueFields[$tableName] ?? $uniqueFields['default'];
    }

    private function getAllRelatedUserIds($sourceDb)
    {
        $userIds = [];
        
        try {
            // 1. Usuarios directos de school_users
            $directUsers = DB::connection($sourceDb)
                ->table('school_users')
                ->where('school_id', $this->schoolId)
                ->pluck('user_id')
                ->toArray();
            $userIds = array_merge($userIds, $directUsers);
            
            // 2. Usuarios de clientes asociados a la escuela
            $clientUsers = DB::connection($sourceDb)
                ->table('clients')
                ->join('clients_schools', 'clients.id', '=', 'clients_schools.client_id')
                ->where('clients_schools.school_id', $this->schoolId)
                ->whereNotNull('clients.user_id')
                ->pluck('clients.user_id')
                ->toArray();
            $userIds = array_merge($userIds, $clientUsers);
            
            // 3. Usuarios de monitores asociados a la escuela
            $monitorUsers = DB::connection($sourceDb)
                ->table('monitors')
                ->join('monitors_schools', 'monitors.id', '=', 'monitors_schools.monitor_id')
                ->where('monitors_schools.school_id', $this->schoolId)
                ->whereNotNull('monitors.user_id')
                ->pluck('monitors.user_id')
                ->toArray();
            $userIds = array_merge($userIds, $monitorUsers);
            
        } catch (\Exception $e) {
            $this->warn("Error obteniendo user_ids relacionados: " . $e->getMessage());
        }
        
        // Remover duplicados y valores nulos
        $userIds = array_unique(array_filter($userIds));
        
        $this->info("🔍 Encontrados " . count($userIds) . " usuarios relacionados con la escuela");
        
        return $userIds;
    }

    private function formatBytes($size, $precision = 2)
    {
        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];
        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
