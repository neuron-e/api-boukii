# API V5 — Endpoints y Revisión Rápida

Este documento resume los endpoints bajo el prefijo `/api/v5`, su propósito, middleware aplicado y observaciones detectadas durante la revisión.

Fuentes principales:
- Definición de rutas: `routes/api.php` y `routes/api_v5/*.php`
- Controladores: `app/Http/Controllers/Api/V5/*`, `app/Http/Controllers/V5/*`
- Módulos V5: `app/V5/Modules/*`

---

## Auth

- POST `/api/v5/auth/check-user`
  - Público. Verifica credenciales; devuelve escuelas disponibles y `temp_token`.
- POST `/api/v5/auth/initial-login`
  - Público. Login inicial con contexto de escuela, sin temporada; devuelve token.
- POST `/api/v5/auth/select-school`
  - `auth:sanctum`. Selecciona escuela, crea token completo y, si existe, fija temporada actual.
- POST `/api/v5/auth/select-season`
  - `auth:sanctum`. Selecciona/crea temporada y completa login.
- POST `/api/v5/auth/login`
  - Público. Login completo con escuela y temporada; devuelve token con contexto.
- GET `/api/v5/auth/me`
  - `auth:sanctum`. Información del usuario autenticado + contexto del token.
- POST `/api/v5/auth/logout`
  - `auth:sanctum`. Revoca el token actual.

Controlador: `App/Http/Controllers/Api/V5/AuthController.php`.

---

## Me (escuelas del usuario)

- GET `/api/v5/me/schools`
  - `auth:sanctum`, `throttle:context`. Lista escuelas visibles al usuario.

Controlador: `App/Http/Controllers/Api/V5/MeSchoolController.php`.

---

## Context (contexto actual)

- GET `/api/v5/context`
  - `auth:sanctum`, `throttle:context`. Devuelve `school_id` y `season_id` actuales.
- POST `/api/v5/context/school`
  - `auth:sanctum`, `throttle:context`. Cambia la escuela actual.

Controlador: `App/Http/Controllers/Api/V5/ContextController.php`.

---

## Schools

- GET `/api/v5/schools`
  - `auth:sanctum`. Listado paginado con filtros de búsqueda/ordenación.
- GET `/api/v5/schools/{school}`
  - `auth:sanctum`. Detalle de escuela (model binding).

Controlador: `App/Http/Controllers/Api/V5/SchoolController.php`.

---

## Seasons

- GET `/api/v5/seasons`
- POST `/api/v5/seasons`
- GET `/api/v5/seasons/current`
- GET `/api/v5/seasons/{season}`
- PUT|PATCH `/api/v5/seasons/{season}`
- DELETE `/api/v5/seasons/{season}`
- POST `/api/v5/seasons/{season}/activate`
- POST `/api/v5/seasons/{season}/deactivate`
- POST `/api/v5/seasons/{season}/close`
- POST `/api/v5/seasons/{season}/reopen`

Middleware: `auth:sanctum`, `school.context.middleware`, `role.permission.middleware:seasons.manage`.

Controlador: `App/Http/Controllers/Api/V5/SeasonController.php`.

---

## Clients

- GET `/api/v5/clients`
- POST `/api/v5/clients`
- GET `/api/v5/clients/{client}`
- PATCH `/api/v5/clients/{client}`
- DELETE `/api/v5/clients/{client}`
- Subrecursos (no implementado; 501):
  - POST `/api/v5/clients/{client}/utilizadores`
  - PATCH `/api/v5/clients/{client}/utilizadores/{utilizador}`
  - DELETE `/api/v5/clients/{client}/utilizadores/{utilizador}`
  - POST `/api/v5/clients/{client}/sports`
  - PATCH `/api/v5/clients/{client}/sports/{sport}`
  - DELETE `/api/v5/clients/{client}/sports/{sport}`

Middleware: `auth:sanctum`.

Notas: El controlador actualmente espera `school_id` en el request (no desde el contexto del token).

Controlador: `app/V5/Modules/Client/Controllers/ClientController.php`.

---

## Logs y Telemetría

- POST `/api/v5/logs`
- POST `/api/v5/telemetry` (alias)

Middleware: `throttle:logging` (no requiere auth por diseño). Se realiza sanitización de PII y limitación/truncado de contexto.

Controlador: `App/Http/Controllers/Api/V5/ClientLogController.php`.

---

## Feature Flags

Rutas definidas en `routes/api.php` dentro de `/api/v5/feature-flags` con `auth:sanctum` y políticas:

- GET `/api/v5/feature-flags/school/{schoolId}` (listar flags)
- POST `/api/v5/feature-flags/school/{schoolId}` (actualizar; `can:manage-feature-flags`)
- GET `/api/v5/feature-flags/school/{schoolId}/history` (historial; `can:view-feature-flag-history`)
- GET `/api/v5/feature-flags/stats` (estadísticas; `can:view-system-stats`)
- POST `/api/v5/feature-flags/gradual-rollout` (rollout gradual; `can:manage-rollouts`)

Controlador: `App/Http/Controllers/V5/FeatureFlagController.php`.

Observación: El controlador expone métodos `getFlags`, `updateFlags`, `getHistory`, `clearCache` que esperan `school_id` en el body/query; las rutas usan `{schoolId}` en la URL y hacen referencia a métodos no presentes (`getFlagsForSchool`, `getFlagHistory` como nombres exactos). Se recomienda alinear nombres y origen de parámetros.

---

## Monitoring

- GET `/api/v5/monitoring/health`
  - Público. Healthcheck con estado del sistema.
- GET `/api/v5/monitoring/system-stats`
  - `auth:sanctum`, `can:view-monitoring`. Estadísticas del sistema (cache 60s).
- GET `/api/v5/monitoring/performance-comparison`
  - `auth:sanctum`, `can:view-monitoring`. Comparativa v4 vs v5 (filtros opcionales: `school_id`, `module`, `hours`).
- GET `/api/v5/monitoring/alerts`
  - `auth:sanctum`, `can:view-monitoring`. Alertas recientes (con filtros).
- POST `/api/v5/monitoring/performance`
  - `auth:sanctum`, `can:view-monitoring`. Registra métrica de performance.
- POST `/api/v5/monitoring/migration-error`
  - `auth:sanctum`, `can:view-monitoring`. Registra error de migración.
- GET `/api/v5/monitoring/school/{schoolId}`
  - `auth:sanctum`, `can:view-monitoring`. Métricas por colegio.
- DELETE `/api/v5/monitoring/cache`
  - `auth:sanctum`, `can:manage-system`. Limpia cache (solo no-producción).

Controlador: `App/Http/Controllers/V5/MonitoringController.php`.

---

## Renting

- GET `/api/v5/renting` — Lista de equipos alquilados (por booking) filtrable por `type` y `status` (rented/returned/outstanding).
- POST `/api/v5/renting` — Crea un equipo asociado a una reserva (`booking_id`) en el contexto actual.
- GET `/api/v5/renting/{id}` — Detalle de equipo.
- PATCH `/api/v5/renting/{id}` — Actualiza equipo.
- DELETE `/api/v5/renting/{id}` — Elimina equipo.

Middleware: `auth:sanctum`, `context.middleware`, `role.permission.middleware:season.equipment`.

Controlador: `App/V5/Modules/Renting/Controllers/RentingController.php`.

---

## Renting Categories

- GET `/api/v5/renting/categories` — Lista paginada; `?tree=true` devuelve árbol completo.
- POST `/api/v5/renting/categories` — Crea categoría/subcategoría.
- GET `/api/v5/renting/categories/{id}` — Detalle de categoría.
- PATCH `/api/v5/renting/categories/{id}` — Actualiza categoría.
- DELETE `/api/v5/renting/categories/{id}` — Elimina categoría.

Middleware: `auth:sanctum`, `school.context.middleware`, `role.permission.middleware:school.settings`.

Controlador: `App/V5/Modules/Renting/Controllers/CategoryController.php`.

---

## Renting Items (Inventario)

- GET `/api/v5/renting/items` — Lista de ítems de inventario (filtros: `category_id`, `active`, `search`).
- POST `/api/v5/renting/items` — Crea ítem.
- GET `/api/v5/renting/items/{id}` — Detalle de ítem.
- PATCH `/api/v5/renting/items/{id}` — Actualiza ítem.
- DELETE `/api/v5/renting/items/{id}` — Elimina ítem.

Middleware: `auth:sanctum`, `school.context.middleware`, `role.permission.middleware:season.equipment`.

Controlador: `App/V5/Modules/Renting/Controllers/ItemController.php`.

---

## Middleware y Límites

- Grupo `/api/v5`: `api`, `throttle:api`.
- Autenticación: `auth:sanctum` en la mayoría de módulos (excepto endpoints públicos como `health` y algunos de auth).
- Throttling específico: `throttle:logging` para logs; `throttle:context` para context/me.
- Permisos granulares: `can:*` en feature-flags y monitoring; `role.permission.middleware:seasons.manage` para seasons.

---

## Observaciones y Recomendaciones

1) Feature Flags — desalineación rutas/controlador
- Problema: Rutas con `{schoolId}` + nombres de métodos no existentes; controlador espera `school_id` en el body/query y no define `getUsageStats()` ni `enableGradualRollout()`.
- Acción sugerida: Unificar firma y nombres (o ajustar rutas a `getFlags`, `updateFlags`, `getHistory`) y agregar endpoints faltantes en el controlador.

2) Clients — contexto de escuela
- Problema: `ClientController` toma `school_id` desde el request en lugar del contexto del token.
- Acción sugerida: Resolver `school_id` desde el token (con fallback a header, como en `ClientLogController`) y añadir políticas/abilities (`can:clients.manage`).

3) Consistencia de binding/IDs
- Mixto entre model binding (schools) e ID + service (seasons). Opcional alinear estilos para claridad.

4) Logs públicos
- Correcto por diseño para intake de frontend/clientes. Mantener rate limit y sanitización (ya existente: emails/teléfonos, truncado de contextos) y revisar tamaño de payloads.

---

## Referencias

- Rutas: `routes/api.php`, `routes/api_v5/*.php`
- Controladores: `app/Http/Controllers/Api/V5/*`, `app/Http/Controllers/V5/*`
- Servicio de feature flags: `app/Services/FeatureFlagService.php`
- Servicio de monitoring: `app/Services/V5MonitoringService.php`
