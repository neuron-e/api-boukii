# Boukii V5 — Plan de Convivencia y Migración de Datos (sin pérdida)

## Objetivos y Principios
- No pérdida de datos: nunca borrar tablas/filas existentes en producción.
- Convivencia: operar V4 y V5 en paralelo durante la transición.
- Migración gradual: por escuela/módulo, con validación y rollback.
- Cambios de esquema aditivos e idempotentes: crear/añadir, nunca destruir.

## Arquitectura de Datos (Dual-DB)
- `mysql` (actual): BD operativa con tablas históricas (p. ej., `users`, `schools`, etc.) y nuevas tablas V5 (`v5_*`, `school_season_settings`, `user_season_roles`, …).
- `old` (legacy): BD con un dump de la versión anterior para lectura y migración.
  - Config actual: `config/database.php` define la conexión `old` con `database => 'boukii_last'` (ajustar si procede).

## Preparación y Salvaguardas
- Backup completo antes de tocar nada:
  - `mysqldump -u <user> -p boukii_pro > backup_boukii_pro_$(date +%F).sql`
  - (Opcional) `mysqldump -u <user> -p boukii_legacy > backup_boukii_legacy_$(date +%F).sql`
- Pausar jobs/cron que modifiquen datos durante la migración.
- Verificar conexión a DB (`php artisan tinker` y `DB::connection()->getPdo();`).

## Restaurar BD Legacy (Lectura)
1) Crear BD legacy y restaurar dump de la versión anterior:
   - Crear BD `boukii_last` (o la que definamos como legacy).
   - Importar dump: `mysql -u <user> -p boukii_last < dump_legacy.sql`.
2) Asegurar la conexión `old`:
   - Opción A (rápida): crear la BD con el nombre esperado en `config/database.php` (`boukii_last`).
   - Opción B (recomendada): parametrizar `config/database.php` para leer `env('OLD_DB_DATABASE')` y usar `.env` (requiere pequeño cambio de config).

## Migraciones de Esquema V5 (Aditivas)
- Estado: he dejado las migraciones V5 idempotentes y no destructivas. No tocan `users`, `schools`, etc.
- Ejecutar:
  - `php artisan migrate`
- Detalles relevantes:
  - Se asegura `personal_access_tokens` (migración `ensure_personal_access_tokens_table_exists`).
  - Las tablas V5 se crean sólo si no existen.
  - FKs potencialmente conflictivas en renting están deshabilitadas por defecto; se pueden activar con `V5_ADD_FKS=true` (ver más abajo).

## Migración de Datos Legacy → V5
- Herramienta integrada:
  - Ayuda: `php artisan boukii:migrate-v5 --help`
  - Simulación: `php artisan boukii:migrate-v5 --dry-run`
  - Migrar por escuela: `php artisan boukii:migrate-v5 --school_id=<id> --backup`
  - Migración completa: `php artisan boukii:migrate-v5 --backup`
- Recomendación: migrar por lotes (pocas escuelas) y validar tras cada lote.

## Validación Funcional (Smoke)
- Autenticación V5:
  - `POST /api/v5/auth/check-user`
  - `POST /api/v5/auth/initial-login`
  - `POST /api/v5/auth/select-season`
  - `GET  /api/v5/auth/me` (verifica contexto y permisos)
- Logs cliente (rate limit y 202): `POST /api/v5/logs`.
- Módulos (al menos una lectura por módulo con datos migrados): Renting, Monitor, Activity, Seasons.

## Cutover por Módulos y Feature Flags
- Activar rutas/funciones V5 por escuela y/o módulo.
- Mantener lecturas críticas apuntando a legacy (`old`) hasta completar la migración del módulo.
- Monitoreo: activar `v5.logging` (middleware) fuera de testing y revisar `storage/logs/v5/*`.

## Rollback y Recuperación
- Si un lote falla:
  - Restaurar desde backup de `boukii_pro` (dump previo).
  - `php artisan boukii:migrate-v5 --rollback` (si aplica para pasos lógicos del comando).
- Los cambios de esquema V5 son aditivos; no requieren revertir para volver a operar V4.

## Checklists
- Antes de migrar:
  - [ ] Backup `boukii_pro` generado y verificado (tamaño > 0, importable).
  - [ ] BD legacy restaurada en `boukii_last` (o nombre configurado).
  - [ ] `php artisan migrate:status` en verde (o sólo pendientes V5 permitidas).
- Después de migrar cada lote:
  - [ ] Usuarios pueden loguear vía endpoints V5 (`/auth/*`).
  - [ ] Datos clave por módulo visibles; conteos consistentes vs legacy.
  - [ ] Logs y métricas sin errores.

## Flags/Entorno
- `V5_ADD_FKS`: si `true`, intentará añadir FKs en renting; por defecto `false` (evita errores en entornos con datos).
- `APP_ENV=testing`: desactiva logging V5 y activitylog para pruebas (no afecta producción).

## Comandos Útiles
- Esquema/migraciones:
  - `php artisan migrate`
  - `php artisan migrate:status`
- Migración V5:
  - `php artisan boukii:migrate-v5 --dry-run`
  - `php artisan boukii:migrate-v5 --school_id=<id> --backup`
- Verificación rápida:
  - `php artisan tinker --execute="Schema::hasTable('personal_access_tokens') ? 'ok' : 'missing'"`

## Riesgos y Mitigaciones
- FK/constraints: deshabilitadas por defecto en tablas nuevas susceptibles; activar sólo cuando el modelo de datos esté estabilizado.
- Triggers de `seasons`: sólo se crean si existe la tabla; no rompen entornos sin ella.
- Pérdida de datos: evitada por diseño (aditivo). Mantener backups y no ejecutar comandos destructivos.

## Cronograma Sugerido (resumen)
1) Día 1 (mañana): Backups + restaurar legacy (`boukii_last`) + `migrate` aditivo.
2) Día 1 (tarde): `boukii:migrate-v5 --dry-run` y migración por 1–2 escuelas; smoke tests.
3) Día 2: Migración por lotes restantes + validaciones; activar features V5 por escuela.
4) Día 3: Afinar FKs/índices donde aplique, monitorizar rendimiento y errores.

---

Si preferís trabajar con una sola BD en lugar de coexistir con legacy, se puede restaurar la BD antigua directamente sobre `boukii_pro` y correr sólo migraciones aditivas V5; es más arriesgado (colisiones y menor control de rollback). La estrategia recomendada es la de coexistencia y migración gradual descrita arriba.

