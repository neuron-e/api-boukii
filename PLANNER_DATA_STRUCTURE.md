# Estructura de Datos del Planner API - Documentación Completa

**Endpoint:** `GET /admin/getPlanner`
**Fecha:** 2025-11-05
**Versión:** Con Resource Classes aplicadas

---

## 📋 Estructura General de la Respuesta

```json
{
  "success": true,
  "data": {
    "MONITOR_ID_1": {
      "monitor": { Monitor Object },
      "bookings": { Grouped Bookings },
      "nwds": [ NWD Objects ]
    },
    "MONITOR_ID_2": { ... },
    "no_monitor": {
      "monitor": null,
      "bookings": { Grouped Bookings },
      "nwds": []
    }
  },
  "message": "Planner retrieved successfully"
}
```

---

## 1. MONITOR OBJECT

### Campos Incluidos (12 campos)

```json
{
  "id": 123,
  "first_name": "Juan",
  "last_name": "García",
  "email": "juan@example.com",
  "phone": "+34123456789",
  "image": "https://...",
  "language1_id": 1,
  "language2_id": 2,
  "language3_id": 3,
  "hasFullDayNwd": false,
  "sports": [
    {
      "id": 5,
      "name": "Esquí Alpino",
      "icon_selected": "...",
      "authorizedDegrees": [
        {
          "degree_id": 3
        }
      ]
    }
  ]
}
```

### Campos ELIMINADOS (no enviados)

❌ `created_at`
❌ `updated_at`
❌ `deleted_at`
❌ `dni`
❌ `address`
❌ `city`
❌ `postal_code`
❌ `birth_date`
❌ `iban`
❌ `notes`
❌ `sports[].icon` (solo `icon_selected`)
❌ `sports[].pivot`
❌ `sports[].created_at`
❌ Todos los campos completos de `degree` (solo se envía `degree_id`)

---

## 2. BOOKINGS OBJECT

Los bookings vienen **agrupados** según el tipo de curso y otras condiciones:

### Estructura de Agrupación

```json
"bookings": {
  "": [ Array de BookingUsers type 1 (colectivos) ],
  "course_id-course_date_id": [ Array de BookingUsers type 2/3 CON monitor ],
  "course_id-course_date_id-booking_id": [ Array de BookingUsers type 2/3 SIN monitor SIN group_id ],
  "course_id-course_date_id-booking_id-group_id": [ Array de BookingUsers type 2/3 SIN monitor CON group_id ],
  "course_id-course_date_id-subgroup_id": CourseSubgroup Object
}
```

### Lógica de Agrupación

#### Para Bookings CON Monitor (líneas 326-333):

```php
if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
    // Cursos Privados/Actividades
    return $booking->course_id . '-' . $booking->course_date_id;
}
// Cursos Colectivos (type 1): no devuelve nada (clave vacía "")
```

**Resultado:**
- **Cursos Privados (type 2) con monitor:** Clave `"123-456"`
- **Actividades (type 3) con monitor:** Clave `"123-456"`
- **Cursos Colectivos (type 1) con monitor:** Clave `""`

#### Para Bookings SIN Monitor (líneas 375-384):

```php
if ($booking->course->course_type == 2 || $booking->course->course_type == 3) {
    if ($booking->group_id) {
        // Con group_id (para intervalos)
        return $booking->course_id . '-' . $booking->course_date_id . '-' .
               $booking->booking_id . '-' . $booking->group_id;
    }
    // Sin group_id
    return $booking->course_id . '-' . $booking->course_date_id . '-' . $booking->booking_id;
}
// Cursos Colectivos: clave vacía ""
```

**Resultado:**
- **Cursos Privados (type 2) sin monitor CON intervalos:** Clave `"123-456-789-10"`
- **Cursos Privados (type 2) sin monitor SIN intervalos:** Clave `"123-456-789"`
- **Cursos Colectivos (type 1) sin monitor:** Clave `""`

---

## 3. BOOKING USER OBJECT (dentro de bookings)

### Campos Incluidos (16 campos base + relaciones)

```json
{
  "id": 456,
  "booking_id": 789,
  "client_id": 111,
  "course_id": 222,
  "course_date_id": 333,
  "course_subgroup_id": null,
  "monitor_id": 123,
  "group_id": 555,
  "date": "2025-11-05",
  "hour_start": "10:00:00",
  "hour_end": "12:00:00",
  "status": 1,
  "accepted": true,
  "degree_id": 3,
  "color": "green",
  "user_id": 999,  // Agregado dinámicamente

  // RELACIONES:
  "booking": {
    "id": 789,
    "user_id": 999,
    "paid": true,
    "user": {
      "id": 999,
      "first_name": "María",
      "last_name": "López"
    }
  },

  "client": {
    "id": 111,
    "first_name": "Pedro",
    "last_name": "Martínez",
    "birth_date": "2010-03-20",
    "language1_id": 1,
    "sports": [
      {
        "id": 5,
        "name": "Esquí"
      }
    ],
    "evaluations": [
      {
        "id": 1,
        "degree_id": 3,
        "degree": {
          "id": 3,
          "name": "Nivel 3",
          "annotation": "N3",
          "color": "#FF5733"
        },
        "evaluationFulfilledGoals": [ ... ]
      }
    ]
  },

  "course": {
    "id": 222,
    "name": "Curso Esquí Privado",
    "sport_id": 5,
    "course_type": 2,
    "max_participants": 1,
    "date_start": "2025-11-01",
    "date_end": "2025-11-30",
    "courseDates": [
      {
        "id": 333,
        "date": "2025-11-05",
        "hour_start": "10:00:00",
        "hour_end": "12:00:00"
      }
    ]
  }
}
```

### Campos ELIMINADOS de BookingUser

❌ `created_at`
❌ `updated_at`
❌ `deleted_at`
❌ `price`
❌ `currency`
❌ `attended`
❌ `notes`
❌ `notes_school`

### Campos ELIMINADOS de booking

❌ `school_id`
❌ `status`
❌ `created_at`
❌ `confirmation_code`
❌ `payment_method`
❌ `total_amount`
❌ `discount`
❌ `notes`

### Campos ELIMINADOS de booking.user

❌ `email`
❌ `phone`
❌ `created_at`
❌ `roles`
❌ `permissions`

### Campos ELIMINADOS de client

❌ `email`
❌ `phone`
❌ `address`
❌ `city`
❌ `country`
❌ `postal_code`
❌ `notes`
❌ `created_at`
❌ `language2_id` (solo se envía `language1_id`)

### Campos ELIMINADOS de course

❌ `school_id`
❌ `price`
❌ `description`
❌ `image`
❌ `active`
❌ `created_at`
❌ `duration`
❌ `level`
❌ `requirements`
❌ `is_flexible`
❌ `short_description`

### Campos ELIMINADOS de course.courseDates[]

❌ `created_at`
❌ `updated_at`
❌ `active`
❌ `course_id`
❌ `course_groups` (array completo)
❌ `booking_users_active` (array completo)

---

## 4. COURSE SUBGROUP OBJECT (en bookings)

Los subgrupos aparecen en el array de bookings con una nomenclatura específica:

**Clave:** `"course_id-course_date_id-subgroup_id"`

### Campos Incluidos

```json
{
  "id": 444,
  "course_group_id": 100,
  "course_date_id": 333,
  "course_id": 222,
  "monitor_id": 123,
  "subgroup_number": 2,
  "total_subgroups": 5,

  "courseGroup": {
    "id": 100,
    "course_id": 222,
    "course": {
      "id": 222,
      "name": "Curso Esquí Colectivo",
      "sport_id": 5,
      "course_type": 1,
      "max_participants": 8,
      "date_start": "2025-11-01",
      "date_end": "2025-11-30"
    }
  },

  "course": {
    "id": 222,
    "name": "Curso Esquí Colectivo",
    "sport_id": 5,
    "course_type": 1,
    "max_participants": 8,
    "date_start": "2025-11-01",
    "date_end": "2025-11-30",
    "courseDates": [
      {
        "id": 333,
        "date": "2025-11-05",
        "hour_start": "10:00:00",
        "hour_end": "12:00:00"
      }
    ]
  },

  "bookingUsers": [
    {
      "id": 456,
      "client_id": 111,
      "degree_id": 3,
      "status": 1,
      "booking": {
        "id": 789,
        "user": {
          "id": 999,
          "first_name": "María",
          "last_name": "López"
        }
      },
      "client": {
        "id": 111,
        "first_name": "Pedro",
        "last_name": "Martínez",
        "birth_date": "2010-03-20",
        "language1_id": 1,
        "sports": [ ... ],
        "evaluations": [ ... ]
      }
    }
  ]
}
```

---

## 5. NWD (NON-WORKING DAY) OBJECT

### Campos Incluidos (10 campos)

```json
{
  "id": 100,
  "monitor_id": 123,
  "start_date": "2025-11-05",
  "end_date": "2025-11-05",
  "start_time": "10:00:00",
  "hour_start": "10:00:00",
  "hour_end": "14:00:00",
  "full_day": false,
  "user_nwd_subtype_id": 1,
  "notes": "Dentista"
}
```

### Campos ELIMINADOS

❌ `school_id`
❌ `created_at`
❌ `updated_at`
❌ `deleted_at`

---

## 6. CONDICIONES DE QUERIES

### BookingUsers Incluidos

✅ `course_subgroup_id = null` (NO están en subgrupos)
✅ `status = 1` (activos)
✅ `booking.status != 2` (booking no cancelada)
✅ `school_id = X` (de la escuela solicitada)
✅ `date BETWEEN date_start AND date_end` (en rango de fechas)

### CourseSubgroups Incluidos

✅ `courseGroup.course.school_id = X`
✅ `courseGroup.course.active = 1`
✅ `courseDate.active = 1`
✅ `courseDate.date BETWEEN date_start AND date_end`
✅ `bookingUsers.status = 1`
✅ `bookingUsers` tienen `booking` asociado

### Monitores Incluidos

✅ `school_id = X`
✅ `active_school = 1` (solo cuando no se especifica monitor_id)
✅ Filtro por `languages` (opcional)

---

## 7. DIFERENCIAS CON VERSIÓN ANTERIOR (Sin Resource Classes)

### ⬇️ Reducción de Tamaño

| Entidad | Campos Antes | Campos Ahora | Reducción |
|---------|--------------|--------------|-----------|
| Monitor | 25+ | 12 | ~50% |
| BookingUser base | 20+ | 16 | ~20% |
| Booking | 15+ | 3 | ~80% |
| Client | 20+ | 5 | ~75% |
| Course | 25+ | 7 | ~72% |
| CourseDates | 10+ | 4 | ~60% |
| NWD | 15+ | 10 | ~33% |

**Reducción total estimada:** ~85-90%

---

## 8. TIPOS DE CURSO Y SU TRATAMIENTO

### Type 1: Cursos Colectivos

**Características:**
- Múltiples participantes
- Se organizan en grupos y subgrupos
- Los `CourseSubgroup` se traen por separado

**En bookings:**
- **Con monitor:** Se agrupan bajo clave `""` (vacía)
- **Sin monitor:** Se agrupan bajo clave `""` (vacía)
- Los subgrupos vienen con clave `"course_id-course_date_id-subgroup_id"`

### Type 2: Cursos Privados

**Características:**
- Usualmente 1 participante (max_participants = 1)
- NO tienen grupos ni subgrupos
- Se reservan como `BookingUser` directamente

**En bookings:**
- **Con monitor:** Clave `"course_id-course_date_id"`
- **Sin monitor SIN intervals:** Clave `"course_id-course_date_id-booking_id"`
- **Sin monitor CON intervals:** Clave `"course_id-course_date_id-booking_id-group_id"`

### Type 3: Actividades

**Características:**
- Similar a privados pero para actividades
- NO tienen grupos ni subgroups

**En bookings:**
- Misma lógica que Type 2

---

## 9. PROBLEMAS CONOCIDOS Y VERIFICACIONES NECESARIAS

### ⚠️ Problema Reportado

**"Los bookings de cursos type 2 sin grupos ni subgrupos no aparecen"**

### Verificaciones a Realizar

#### En Backend:

1. **Verificar que la query trae bookings type 2:**
   ```php
   $bookings = $bookingQuery->get();
   // Verificar: $bookings->where('course.course_type', 2)->count()
   ```

2. **Verificar que no se pierden en el groupBy:**
   ```php
   $monitorBookings = $bookings->where('monitor_id', $monitor->id)->groupBy(...);
   // Verificar claves resultantes
   ```

3. **Verificar que Resource class no los elimina:**
   ```php
   // Confirmar que BookingUserPlannerResource incluye todo
   ```

#### En Frontend:

1. **Verificar estructura esperada:**
   - ¿El frontend espera la clave `"course_id-course_date_id-booking_id"`?
   - ¿O espera solo `"course_id-course_date_id"`?

2. **Verificar que recorre todas las claves:**
   ```javascript
   Object.keys(bookings).forEach(key => {
     console.log('Clave:', key, 'Bookings:', bookings[key]);
   });
   ```

3. **Verificar filtros en frontend:**
   - ¿Hay algún filtro que excluya bookings sin `course_subgroup_id`?
   - ¿Hay filtro por `course_type`?

---

## 10. DEBUGGING - PASOS RECOMENDADOS

### Paso 1: Verificar Bookings en Query

Agregar temporalmente en `PlannerController.php` línea 273:

```php
$bookings = $bookingQuery->get();

// DEBUG: Verificar cursos privados
$type2Bookings = $bookings->filter(function ($b) {
    return $b->course && $b->course->course_type == 2;
});

\Log::info('Type 2 bookings count: ' . $type2Bookings->count());
\Log::info('Type 2 bookings:', $type2Bookings->pluck('id', 'course_id')->toArray());
```

### Paso 2: Verificar Agrupación

Agregar después de línea 326:

```php
$monitorBookings = $bookings->where('monitor_id', $monitor->id)->groupBy(...);

\Log::info('Monitor ' . $monitor->id . ' booking groups:', $monitorBookings->keys()->toArray());
```

### Paso 3: Verificar Respuesta Final

Agregar antes de línea 426:

```php
$type2Count = 0;
foreach ($groupedData as $monitorId => $data) {
    foreach ($data['bookings'] as $key => $group) {
        if (is_array($group) || $group instanceof \Illuminate\Support\Collection) {
            $type2Count += collect($group)->filter(function ($b) {
                return isset($b->course) && $b->course->course_type == 2;
            })->count();
        }
    }
}

\Log::info('Total type 2 bookings in final response: ' . $type2Count);
```

---

## 11. EJEMPLO DE RESPUESTA COMPLETA (SIMPLIFICADA)

```json
{
  "success": true,
  "data": {
    "123": {
      "monitor": {
        "id": 123,
        "first_name": "Juan",
        "last_name": "García",
        "email": "juan@example.com",
        "hasFullDayNwd": false,
        "sports": [...]
      },
      "bookings": {
        "": [
          // Cursos colectivos type 1 con este monitor
          { BookingUser type 1 }
        ],
        "222-333": [
          // Cursos privados type 2 en course 222, date 333
          { BookingUser type 2 },
          { BookingUser type 2 }
        ],
        "222-333-444": {
          // Subgrupo de curso colectivo
          CourseSubgroup
        }
      },
      "nwds": [
        { NWD object }
      ]
    },
    "no_monitor": {
      "monitor": null,
      "bookings": {
        "": [
          // Cursos colectivos sin monitor
        ],
        "222-333-789": [
          // Curso privado sin monitor, booking 789, sin group_id
          { BookingUser type 2 }
        ],
        "222-333-790-10": [
          // Curso privado sin monitor, booking 790, con group_id 10
          { BookingUser type 2 }
        ]
      },
      "nwds": []
    }
  },
  "message": "Planner retrieved successfully"
}
```

---

## 12. CAMPOS CRÍTICOS PARA EL FRONTEND

Si el frontend está dando error, verificar que estos campos EXISTEN:

### Para Timeline/Planner:

✅ `monitor.id`
✅ `monitor.first_name`, `last_name`
✅ `monitor.sports[].id`, `name`, `icon_selected`
✅ `bookingUser.id`
✅ `bookingUser.date`, `hour_start`, `hour_end`
✅ `bookingUser.course.id`, `name`, `course_type`
✅ `bookingUser.client.id`, `first_name`, `last_name`
✅ `bookingUser.degree_id`
✅ `subgroup.subgroup_number`, `total_subgroups`

### Para Modales:

✅ `bookingUser.course_date_id`
✅ `bookingUser.course.courseDates[]`
✅ `bookingUser.booking.user.first_name`, `last_name`
✅ `bookingUser.client.evaluations[].degree`

---

**Fin del documento**

**Próximo paso:** Ejecutar debugging en backend para confirmar que bookings type 2 se están trayendo y no se pierden en el proceso.
