# V5 API & Front — Plan de Implementación

Este documento establece una hoja de ruta detallada para construir la API V5 y el nuevo front con enfoque multi-tenant por escuela, trabajo por temporadas obligatorio, modularización progresiva, alto rendimiento, buenas prácticas (SOLID, clean code), buen manejo de errores y logging, internacionalización y observabilidad.

Fuentes revisadas:
- Código y rutas V5 actuales: `routes/api.php`, `routes/api_v5/*.php`, `app/Http/Controllers/Api/V5/*`, `app/Http/Controllers/V5/*`, `app/V5/*`.
- Documentación V5 existente: `docs/v5/auth.md`, `docs/v5/season.md`, `docs/v5/school.md`, `docs/v5/health-check.md`, `docs/v5/API_V5_ENDPOINTS.md`.

---

## Principios y Requisitos

- Multi-tenant por escuela: todo dato/operación scoped por `school_id` y, cuando aplique, por `season_id`.
- Temporadas obligatorias: toda funcionalidad operativa se ancla a una temporada activa (consulta, creación, edición, reporting), con excepciones controladas (configuración global).
- Modularización progresiva: extraer dominios clave como módulos V5 (Cursos, Actividades, Renting, Monitores) con APIs claras.
- Rendimiento primero: evitar N+1, cachés por tenant, índices, colas y particionamiento de trabajo.
- SOLID/Clean Architecture: capas separadas (Controller → Service → Repository → Model), DTOs/Resources para E/S.
- Seguridad: Sanctum con scopes, rate limits, autorización por políticas/abilities y middleware contextual.
- Observabilidad: logging estructurado, correlación, métricas, healthchecks y alertas.
- i18n: backend y frontend preparados para varios idiomas (mensajes, validaciones, UI, formatos).

---

## Arquitectura Propuesta (Backend)

- Capas:
  - Controllers delgados en `App/Http/Controllers/Api/V5`.
  - Services en `App/V5/Modules/<Modulo>/Services` (reglas de negocio, orquestación).
  - Repositories en `App/V5/Modules/<Modulo>/Repositories` (consultas y scoping tenant/season).
  - Models Eloquent compartidos en `App/V5/Modules/<Modulo>/Models` o reutilizando `App/Models` cuando encaje.
  - Resources/Transformers en `App/Http/Resources/API/V5`.
- Contexto:
  - Middleware `school.context.middleware` e `context.middleware` para inyectar `context_school_id` y `context_season_id` desde el token y headers.
  - Repositories aplican siempre filtros por `school_id` y, cuando proceda, `season_id`.
  - Estándar de cabeceras: `X-School-ID`, `X-Season-ID` como fallback para casos no autenticados o especiales (documentado en `V5_CONTEXT_HEADERS.md`).
- Autorización:
  - Políticas de recurso y abilities granuladas (e.g., `season.view`, `seasons.manage`, `course.read`, `course.manage`).
  - ContextPermissionMiddleware para unificar chequeos multi-nivel (global, school, season).
- Errores y Respuestas:
  - Problem Details (RFC 7807) en errores, `successResponse` para éxito consistente.
  - Códigos de error estables por dominio (e.g., `SEASON_NOT_FOUND`, `COURSE_IMMUTABLE`).
- Logging/Métricas:
  - Logger V5 con `correlation_id`, `tenant_id`, `season_id`, `user_id` en contexto.
  - Endpoints de monitoring (`/api/v5/monitoring/*`) y métricas de performance (latencias, error rate).
- Versionado/API Doc:
  - OpenAPI (l5-swagger) actualizado; rutas bajo `/api/v5`. Deprecación controlada V4 con feature flags por escuela.

---

## Tenancy & Temporadas

- Datos: garantizar columnas `school_id` y, según módulo, `season_id`.
- Migraciones:
  - Auditoría de tablas; agregar índices compuestos (`school_id, season_id`) donde aplique.
  - Backfill seguro de `season_id` para datos históricos o marcar `is_historical`.
- Global Scopes opcionales en modelos de lectura intensiva; preferible scoping en Repository para control fino.
- Contexto de Token: `context_data` en tokens Sanctum con `school_id` y `season_id` para evitar param duplicado.

---

## Módulos Clave (Fase 1)

1) Cursos (Courses)
- Entidades: Curso, Grupo/Subgrupo, Fechas (CourseDate), Precios, Capacidad.
- Operaciones: CRUD, publicación, inscripción, gestión de aforos, calendarios.
- API: `/api/v5/courses` con scoping school/season; filtros (estado, rango fechas, capacidad, etiquetas).
- Métricas: plazas ocupadas/libres, cancelaciones, no-shows.

2) Actividades (Activities)
- Similar a Cursos pero con tipologías y reglas de reserva más flexibles (one-off, series).
- API: `/api/v5/activities`.

3) Renting
- Entidades: Equipos/Material, Tarifas, Slots/Reservas, Depósitos/Daños.
- API: `/api/v5/renting` (inventario, disponibilidad, reservas, devolución).

4) Monitores (Staff)
- Entidades: Monitor, Habilidades, Titulaciones, Disponibilidad, Asignaciones a cursos/actividades.
- API: `/api/v5/monitors` (CRUD, scheduling, asignaciones).

5) Clientes (Clients)
- Revisión del módulo actual para usar contexto de token y permisos, exportaciones.
- API existente `/api/v5/clients` consolidada con permisos `client.*`.

6) Planificador/Calendario
- Dos modos:
  - Planificador avanzado (drag & drop, asignación de monitores, recursos, conflictos).
  - Calendario básico de reservas (visualización + CRUD simple).
- API de calendario agnóstica (`/api/v5/calendar`) con vistas por día/semana/mes; fuentes: Cursos, Actividades, Renting.

---

## Performance & Escalabilidad

- Eloquent: eager loading por defecto, `select` explícitos, evitar N+1 (Larastan/Pint + detector N+1 en dev).
- Consultas: índices por `school_id`, `season_id`, `date`, `status` y FK relevantes.
- Cache:
  - Por tenant/season (`cache key`: `v5:{school}:{season}:{namespace}`), TTLs sensibles.
  - Cache de catálogos y feature flags.
- Jobs/Queues: tareas pesadas a colas (reporting, exportaciones, notificaciones, recalculo de capacidad).
- Paginación cursor en listados grandes.
- HTTP:
  - Rate limits por módulo, compresión, HTTP caching (ETag/Last-Modified) para listados estáticos.

---

## Seguridad

- Autenticación: Sanctum con tokens de corto/medio plazo; scopes/abilities por módulo.
- Autorización: políticas y middleware por contexto (global/school/season).
- Validación: FormRequests con mensajes i18n; sanitización de logs (PII).
- Auditoría: actividad crítica registrada (cambios de flags, estados de temporada, finanzas).

---

## Manejo de Errores & Logging

- Estructura de errores uniforme (Problem Details): `type`, `title`, `status`, `detail`, `instance`, `meta`.
- Logs estructurados JSON con contexto (correlación, tenant, season, user, ip, ua).
- Alertas: reglas en MonitoringService (degradación latencia, p95, tasa de errores).
- Trazabilidad: correlación desde frontend → backend mediante header `X-Correlation-ID`.

---

## Internacionalización (i18n)

- Backend: recursos, validaciones y mensajes en `resources/lang/{locale}`; `Accept-Language` para negociación básica.
- Front: catálogo i18n, formatos locales (fecha/número), preparación RTL si fuese necesario.

---

## Testing & Calidad

- Tests: Unit (Services, Repositories), Feature (Endpoints), Contract (OpenAPI), E2E críticos.
- Fixtures/Factories y bases de datos por entorno (in-memory/dedicada test).
- Estilo: `pint` y `phpstan`/`larastan` (nivel razonable), CS fixer front.
- CI: pipelines con linters, tests, cobertura mínima por módulo y reportes de calidad.

---

## Plan de Trabajo (Hitos)

1) Fundaciones (Semana 1-2)
- Middleware de contexto consolidado (school/season) + actualización de controllers existentes.
- Estructura de módulos base y carpetas (`Courses`, `Activities`, `Renting`, `Monitors`, `Clients`).
- Normalización de `ProblemDetails` y `V5Logger` (correlación global).
- Documentación OpenAPI esqueleto por módulos.

2) Temporadas (Semana 2-3)
- Revisar/ajustar `SeasonController` y servicios para permisos `season.view` vs `seasons.manage`.
- Migraciones e índices necesarios (school_id/season_id en tablas afectadas).

3) Cursos + Calendario Básico (Semana 3-5)
- CRUD cursos, fechas, capacidad, publicación; endpoints listados con filtros.
- Calendario básico: agregación de eventos (cursos) por school/season.
- Tests feature y recursos API.

4) Actividades (Semana 5-6)
- CRUD actividades, reglas de reserva, eventos en calendario.

5) Renting (Semana 6-7)
- Inventario, disponibilidad, reservas y devoluciones; integración en calendario.

6) Monitores (Semana 7-8)
- CRUD, disponibilidades, asignaciones; validaciones de conflictos y métricas.

7) Planificador Avanzado (Semana 8-10)
- Endpoints de scheduling (asignación, validación de conflictos, sugerencias) + UI drag & drop.

8) Performance & Observabilidad (Continuo)
- Cache selectiva, colas para tareas pesadas, métricas/alertas y tuning de índices.

9) Seguridad & i18n (Continuo)
- Revisión de políticas, rate limits, hardening; catálogo i18n de mensajes.

10) Migración y Despliegue Progresivo
- Flags por escuela para activar módulos V5.
- Guías de migración de datos (V4 → V5) por dominio.
- Plan de rollback y canary per school.

---

## Front (Nuevo Diseño)

- Base:
  - Design System (tokens, tipografía, colores), librería de componentes (botones, formularios, tablas, modales).
  - Layouts responsivos, navegación clara por school/season.
  - Estado global: `AuthContext`, `SchoolSeasonContext`, `FeatureFlags`, `Notifications`.
- Rutas y Flujos:
  - Autenticación V5 (check-user → select-school → select-season → app).
  - Módulos: Cursos, Actividades, Renting, Monitores, Clientes; Calendario/Planificador.
  - i18n: selector de idioma, carga lazy de catálogos.
- Rendimiento:
  - Code splitting por ruta/módulo, memoización, virtualización de listas.
  - Caché cliente y SW opcional (si aplica) para datos estáticos.
- Accesibilidad (a11y):
  - Navegación teclado, ARIA attrs, contraste, foco visible.
- Observabilidad:
  - Telemetría al backend `/api/v5/telemetry`, correlación de requests.

---

## Entregables y Documentación

- OpenAPI por módulo (autogenerado + manual) en `docs/api` y Swagger UI.
- README por módulo (use-cases, endpoints, permisos, ejemplos).
- Guía de estilos y estándares (nombres, recursos, errores).
- Playbooks de incidentes y paneles de monitoring (Dashboards por escuela/módulo).

---

## Riesgos y Mitigaciones

- Desalineación rutas/controladores (ej. Feature Flags):
  - Acción: unificar firmas, probar con tests de contrato.
- Permisos demasiado estrictos para lectura:
  - Acción: separar `*.view` de `*.manage` y ajustar middleware.
- Carga histórica sin season_id:
  - Acción: migraciones con `NULLABLE` + proceso de asignación y marcaje `is_historical`.
- Performance en listados grandes:
  - Acción: índices, filtros server-side, paginación cursor y cache por tenant.

---

## Siguientes Pasos Inmediatos

1) Alinear rutas Feature Flags con controlador y parámetros.
2) Ajustar permisos de `Seasons` para lectura con `season.view`.
3) Scaffold de módulos `Courses`, `Activities`, `Renting`, `Monitors` (carpetas, servicios, repos, rutas esqueleto, tests vacíos).
4) Definir esquema OpenAPI base por cada módulo.
5) Establecer convenciones ProblemDetails y logging (correlation headers) en un paquete común V5.

