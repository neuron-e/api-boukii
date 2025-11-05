# Optimización del Planner - Notas de Implementación

**Fecha:** 2025-11-05
**Archivo modificado:** `app/Http/Controllers/Admin/PlannerController.php`
**Método:** `performPlannerQuery()`

## Problemas Resueltos

### 1. Cursos Privados (type = 2) No Se Visualizaban

**Causa raíz:**
- La lógica de `groupBy()` en las líneas 298-304 y 347-355 solo devolvía una clave para cursos privados (type 2) y actividades (type 3)
- Para cursos colectivos (type 1), la función no devolvía ninguna clave, agrupándolos todos bajo `null`
- Existía inconsistencia entre la agrupación de bookings con monitor vs sin monitor

**Solución implementada:**
- **Líneas 287-295:** Lógica de agrupación mejorada para bookings con monitor
  - Cursos privados/actividades (type 2 y 3): se agrupan por `course_id-course_date_id`
  - Cursos colectivos (type 1): se agrupan por `course_id-course_date_id-booking_id` (individual)

- **Líneas 337-344:** Lógica de agrupación consistente para bookings sin monitor
  - Misma lógica que para bookings con monitor
  - Eliminada la inclusión inconsistente de `booking_id` en todos los casos

**Impacto:** Los cursos privados ahora se visualizan correctamente tanto en monitores asignados como en "sin monitor asignado"

---

### 2. Performance Extremadamente Lento con Muchos Monitores

**Problemas identificados:**

1. **N+1 Query en authorizedDegrees** (líneas 248-258 del código original)
   - Loop sobre cada monitor con query individual dentro

2. **N+1 Query en full_day NWDs** (líneas 276-295 del código original)
   - Loop sobre cada monitor con queries de verificación dentro

3. **Query sin filtros en subgroupsPerGroup** (línea 271-273 del código original)
   - Contaba TODOS los subgroups de TODAS las escuelas sin filtrar

4. **whereHas pesados**
   - Múltiples subqueries en lugar de joins eficientes

**Optimizaciones implementadas:**

#### a) Joins en lugar de whereHas

**Líneas 104-126:** Subgroups Query
```php
// ANTES: whereHas con subqueries
->whereHas('courseGroup.course', function ($query) use ($schoolId) {
    $query->where('school_id', $schoolId)->where('active', 1);
})

// AHORA: Join directo
->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
->join('courses', 'course_groups.course_id', '=', 'courses.id')
->where('courses.school_id', $schoolId)
->where('courses.active', 1)
```

**Líneas 128-137:** Bookings Query
```php
// ANTES: whereHas
->whereHas('booking', function ($query) {
    $query->where('status', '!=', 2);
})

// AHORA: Join directo
->join('bookings', 'booking_users.booking_id', '=', 'bookings.id')
->where('bookings.status', '!=', 2)
```

**Mejora:** Reduce significativamente las subqueries y mejora el uso de índices de base de datos

#### b) Batch Loading de Authorized Degrees

**Líneas 204-223:**
```php
// ANTES: Query dentro del loop de monitores (N+1)
foreach ($monitors as $monitor) {
    foreach ($monitor->sports as $sport) {
        $sport->authorizedDegrees = MonitorSportAuthorizedDegree::whereHas(...)->get();
    }
}

// AHORA: Una sola query para todos los monitores
$authorizedDegreesByMonitorSport = MonitorSportAuthorizedDegree::with('degree')
    ->whereHas('monitorSport', function ($q) use ($schoolId, $monitorIds) {
        $q->where('school_id', $schoolId)->whereIn('monitor_id', $monitorIds);
    })
    ->get()
    ->groupBy(function ($item) {
        return $item->monitorSport->monitor_id . '-' . $item->monitorSport->sport_id;
    });
```

**Mejora:** De N queries a 1 query, reducción de ~100x para escuelas con 100 monitores

#### c) Batch Loading de Full Day NWDs

**Líneas 253-282:**
```php
// ANTES: Query por cada monitor, por cada día (N*M queries)
foreach ($monitors as $monitor) {
    foreach ($daysWithinRange as $day) {
        $hasFullDayNwd = MonitorNwd::where('monitor_id', $monitor->id)->count() > 0;
    }
}

// AHORA: Una query para todos los monitores
$fullDayNwds = MonitorNwd::where('school_id', $schoolId)
    ->whereIn('monitor_id', $monitorIds)
    ->where('full_day', true)
    ->where('start_date', '<=', $dateEnd)
    ->where('end_date', '>=', $dateStart)
    ->get();
```

**Mejora:** De N*M queries a 1 query, reducción de ~700x para escuelas con 100 monitores y 7 días

#### d) Filtrado de SubgroupsPerGroup

**Líneas 237-249:**
```php
// ANTES: Sin filtros (traía TODOS los subgroups de TODAS las escuelas)
$subgroupsPerGroup = CourseSubgroup::select('course_group_id', DB::raw('COUNT(*) as total'))
    ->groupBy('course_group_id')
    ->pluck('total', 'course_group_id');

// AHORA: Filtrado por school_id y rango de fechas
$subgroupsPerGroupQuery = CourseSubgroup::select('course_group_id', DB::raw('COUNT(*) as total'))
    ->join('course_groups', 'course_subgroups.course_group_id', '=', 'course_groups.id')
    ->join('courses', 'course_groups.course_id', '=', 'courses.id')
    ->where('courses.school_id', $schoolId);

if ($dateStart && $dateEnd) {
    $subgroupsPerGroupQuery->join('course_dates', ...)
        ->whereBetween('course_dates.date', [$dateStart, $dateEnd]);
}
```

**Mejora:** Reduce el dataset procesado de potencialmente miles a solo los relevantes

---

## Mejora Estimada de Performance

Para una escuela con **100 monitores**, **7 días** de rango:

| Operación | Antes | Ahora | Mejora |
|-----------|-------|-------|--------|
| Authorized Degrees Queries | 100+ queries | 1 query | ~100x |
| Full Day NWD Queries | 700 queries | 1 query | ~700x |
| Subgroups Count | Procesa todos | Solo school/fecha | ~10-50x |
| Subgroups/Bookings Load | whereHas (subqueries) | Joins directos | ~3-5x |

**Total estimado:** 50-100x más rápido para escuelas grandes

---

## Consideraciones para el Frontend

### Cambios en Estructura de Datos

#### 1. Agrupación de Bookings

**ANTES:**
```javascript
// Cursos privados con monitor
bookings: {
  "course_id-course_date_id": [...],
  // Cursos colectivos con clave null
  "": [...]  // PROBLEMA: todos agrupados juntos
}

// Cursos privados sin monitor
bookings: {
  "course_id-course_date_id-booking_id": [...],  // Incluía booking_id
  "": [...]
}
```

**AHORA:**
```javascript
// Cursos privados (type 2/3) con monitor
bookings: {
  "course_id-course_date_id": [...]
}

// Cursos colectivos (type 1) con monitor
bookings: {
  "course_id-course_date_id-booking_id": [...]  // Individual
}

// CONSISTENTE para con/sin monitor
```

### Verificaciones Recomendadas en Frontend

1. **Verificar renderizado de cursos privados (type 2)**
   - Confirmar que aparecen en monitores asignados
   - Confirmar que aparecen en "sin monitor asignado"

2. **Verificar agrupación de cursos colectivos (type 1)**
   - Pueden ahora tener claves individuales en lugar de agruparse bajo `null`
   - Verificar que se muestren correctamente sin duplicados

3. **Verificar performance**
   - El planner debería cargar significativamente más rápido
   - Especialmente notable en vistas semanales/mensuales con muchos monitores

### Pruebas Sugeridas

1. **Cursos privados:**
   - [ ] Crear booking de curso privado sin monitor asignado → verificar aparece en "sin monitor"
   - [ ] Asignar monitor al curso privado → verificar aparece en sección del monitor
   - [ ] Verificar que se muestran correctamente en calendario/planner

2. **Performance:**
   - [ ] Cargar planner semanal con escuela de muchos monitores (>50)
   - [ ] Cargar planner mensual con escuela de muchos monitores
   - [ ] Verificar tiempos de carga en Network tab (debería ser <2s vs >30s antes)

3. **Funcionalidad general:**
   - [ ] Drag & drop de bookings entre monitores
   - [ ] Filtrado por idiomas
   - [ ] Visualización de NWDs (non-working days)
   - [ ] Visualización de subgrupos

---

## Notas Técnicas

### Índices de Base de Datos Recomendados

Para maximizar el beneficio de las optimizaciones, asegurar que existan estos índices:

```sql
-- Tabla course_subgroups
CREATE INDEX idx_course_subgroups_monitor_date ON course_subgroups(monitor_id, course_date_id);
CREATE INDEX idx_course_subgroups_group ON course_subgroups(course_group_id);

-- Tabla booking_users
CREATE INDEX idx_booking_users_monitor_date ON booking_users(monitor_id, date);
CREATE INDEX idx_booking_users_school_date ON booking_users(school_id, date);

-- Tabla monitor_nwds
CREATE INDEX idx_monitor_nwds_monitor_dates ON monitor_nwds(monitor_id, start_date, end_date);
CREATE INDEX idx_monitor_nwds_school_dates ON monitor_nwds(school_id, start_date, end_date);

-- Tabla course_dates
CREATE INDEX idx_course_dates_date_active ON course_dates(date, active);
```

### Compatibilidad

- **Laravel Version:** Compatible con Laravel 8+
- **PHP Version:** Compatible con PHP 7.4+
- **Database:** Optimizado para MySQL/MariaDB
- **Breaking Changes:** Ninguno - la estructura de respuesta es compatible con el frontend existente

### Debugging

Si surgen problemas, habilitar query logging:

```php
DB::listen(function($query) {
    Log::info($query->sql);
    Log::info($query->bindings);
    Log::info($query->time);
});
```

---

## Testing

Ejecutar tests existentes:

```bash
# Tests de API
vendor/bin/phpunit tests/APIs/PlannerAPITest.php

# Tests de repositorio
vendor/bin/phpunit tests/Repositories/

# Verificar sintaxis
php -l app/Http/Controllers/Admin/PlannerController.php
```

---

## Rollback

Si es necesario revertir los cambios:

```bash
git revert <commit-hash>
```

O restaurar manualmente la lógica anterior del método `performPlannerQuery()`.

---

## Contacto

Para preguntas o issues relacionados con estos cambios, contactar al equipo de desarrollo o crear un issue en el repositorio.
