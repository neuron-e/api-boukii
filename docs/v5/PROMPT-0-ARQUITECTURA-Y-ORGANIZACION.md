# Boukii V5 — Prompt 0: Arquitectura y Organización

Objetivo: cerrar una estructura de carpetas coherente (API y Front), fijar el modelo de usuarios/roles/permisos por escuela, seasons y stations, y dejar el flujo de autenticación/contexto 100% definido sin romper lo existente.

## 1) Estructura de Proyecto

- API Laravel (v5):
  - `routes/api_v5/*.php`: rutas por dominio (`auth.php`, `schools.php`, `seasons.php`, ...). Prefijo: `/api/v5` (definido en `routes/api.php`).
  - Controladores: `App\Http\Controllers\Api\V5\{Dominio}\*Controller.php`.
  - Requests: `App\Http\Requests\API\V5\{Dominio}\*Request.php`.
  - Resources: `App\Http\Resources\API\V5\{Dominio}\*Resource.php`.
  - Dominio/Servicios/Repos: `App\V5\Modules\{Dominio}\(Services|Repositories|*)`.
  - Modelos V5: `App\V5\Models\*` (preferido para módulos nuevos). Modelos legacy permanecen en `App\Models`.
  - Middleware V5: `App\Http\Middleware\V5\*` (contexto, permisos, etc.).
  - Utilidades V5: `App\V5\(Services|Validation|Logging|Integrations)`.

- Front Angular (nuevo):
  - `front/src/app/features/{feature}/...` (pages standalone + servicios por feature).
  - `front/src/app/core` (guards, interceptors, api client, i18n, layout).
  - Convención de rutas: lazy modules por área (admin, dashboard, seasons, users, permissions, etc.).
  - Guards clave: `auth.guard`, `school-selection.guard`, `season-selection.guard`, `permission.guard`.

Notas de convergencia: existen duplicidades de Requests V5 en `app/V5/Requests` y `app/Http/Requests/API/V5`. Estándar único: `app/Http/Requests/API/V5`. Las clases en `app/V5/Requests` quedan deprecadas y se migrarán gradualmente (por ejemplo, `App\V5\Requests\Season\CreateSeasonRequest` y `UpdateSeasonRequest`).

## 2) Modelo de Datos (resumen)

- Usuario (`users`): usa Spatie `HasRoles` para roles globales y utilidades; campo `type` legacy soportado (admin/monitor/cliente).
- Escuelas (`schools`) y pivot `school_users` para membresía del usuario en cada escuela.
- Seasons (`seasons`): pertenecen a una escuela. Una activa por escuela (en paralelo puede haber históricas).
- Roles por Season: `user_season_roles` (user_id, season_id, role) para rol efectivo del usuario dentro de una temporada concreta.
- Stations (`stations`) + pivot `stations_schools` para asociarlas a escuelas. Permisos relacionados a nivel escuela; no forman parte del “contexto activo” obligatorio.

Relaciones clave:
- User —< user_season_roles >— Season —< School
- User —< school_users >— School
- School —< Seasons; School —< Stations (vía pivot)

Decisión: mantenemos el enfoque actual (membresía por escuela + rol por season) y NO activamos “teams” de Spatie por ahora. Evaluar migración a teams cuando el dominio esté estabilizado.

## 3) Autenticación y Contexto Activo

Flujo 3 pasos (implementado):
1) `POST /api/v5/auth/check-user` → valida credenciales y devuelve escuelas accesibles y `temp_token` (Sanctum con ability `temp-access`).
2) `POST /api/v5/auth/select-school` (auth:sanctum con temp token) → fija `context_data.school_id` en el token y devuelve seasons disponibles (y puede auto-seleccionar si solo hay una activa).
3) `POST /api/v5/auth/select-season` → fija `context_data.season_id` y emite token de acceso “completo” con abilities adecuadas.

Contexto:
- Persistencia en token (campo `context_data`): `{ school_id, season_id }`.
- Middlewares:
  - `school.context.middleware`: exige escuela válida y acceso del usuario.
  - `context.middleware`: exige escuela + season válidas, y pertenencia/rol del usuario en la season.
  - `role.permission.middleware`: verifica permisos efectivos (global/escolar/season) por ruta.

Headers estándar (frontend → backend en requests autenticadas):
- `X-School-ID`: opcional si ya está en `context_data` del token.
- `X-Season-ID`: opcional si ya está en `context_data` del token.

Operaciones de contexto (API):
- `GET /api/v5/context` → devuelve `{ school_id, season_id }` del token.
- `POST /api/v5/context/school` → cambia escuela activa y resetea season.
- (Nuevo) `POST /api/v5/context/season` → cambia la season activa manteniendo escuela.

## 4) Permisos y Roles

Capas de permiso (en `ContextPermissionMiddleware`):
- Global: `superadmin` y permisos globales.
- Escuela: `school.admin|manager|staff|view|settings|users|billing`.
- Season: `season.admin|manager|view|bookings|clients|monitors|courses|analytics|equipment`.
- Recursos: `booking.*`, `client.*`, `monitor.*`, `course.*`, etc.

Reglas actuales:
- Superadmin tiene acceso total.
- Dueño de la escuela: acceso total a su escuela.
- Miembro de escuela hereda permisos por rol escolar y por rol de season (si aplica).
- `user_season_roles` determina el rol efectivo en una season; Spatie roles se usan como catálogo y para permisos comunes.

Decisiones:
- Mantener el middleware de contexto y permisos como autoridad única en rutas V5.
- Spatie Permission se usa para catálogo de roles/permissions y checks globales; las decisiones con contexto se delegan al middleware/servicios V5.

## 5) Convenciones por Módulo (API)

- Rutas en `routes/api_v5/{modulo}.php` con `prefix` y `name` claros.
- Controlador en `App\Http\Controllers\Api\V5\{Modulo}Controller` consumiendo servicios de `App\V5\Modules\{Modulo}`.
- Requests en `App\Http\Requests\API\V5\{Modulo}`.
- Resources en `App\Http\Resources\API\V5\{Modulo}`.
- Servicios/Repos aislados en `App\V5\Modules\{Modulo}`.

## 6) Convenciones Front (Angular)

- Features: `src/app/features/{modulo}` con páginas standalone (OnPush + Signals) y `services/` HTTP por módulo.
- Guards: `auth.guard`, `school-selection.guard`, `season-selection.guard` deben proteger todas las rutas dentro del AppShell.
- Interceptor de contexto: inyecta `X-School-ID` y `X-Season-ID` si existen en el estado local (persistido en `localStorage`).
- Tipado: DTOs por módulo y `types/` compartidos cuando aplique.

## 7) Tareas de Alineación (sin romper nada)

Backend:
- Unificar Requests en `app/Http/Requests/API/V5/*` (dejar las de `app/V5/Requests` como deprecated hasta refactor final).
- Añadir endpoint para cambiar season en `routes/api_v5/context.php` (usa `ContextService::setSeason`).
- Estandarizar uso de `App\V5\Models\*` en módulos V5 nuevos y mantener `App\Models\*` para legacy.

Frontend:
- Confirmar guards en rutas (auth + school + season) en todas las secciones del panel.
- Verificar que el interceptor de contexto propaga los headers tras login/select.
- Consolidar servicios por módulo bajo `features/{modulo}/services`.

Checklist de salida Prompt 0:
- [ ] Docs aceptadas (este archivo) y alineadas con el equipo.
- [ ] `ContextService` con `setSchool` y `setSeason`.
- [ ] Endpoint `POST /api/v5/context/season` disponible.
- [ ] Guards activos en front y flujos de login operativos E2E.
