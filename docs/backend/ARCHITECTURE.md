# Boukii V5 API Overview - ACTUALIZADO 2025

## 🎯 Arquitectura Multi-Tenant

El sistema V5 de Boukii implementa una arquitectura multi-tenant robusta con:

- **Multi-School**: Cada usuario puede pertenecer a múltiples escuelas
- **Multi-Season**: Cada escuela puede tener múltiples temporadas activas
- **Context-Based Security**: Todas las requests requieren contexto de escuela y temporada

## 🔐 Sistema de Autenticación

### Flujo de Login Completo

```text
login → school selection → season selection → dashboard
```

1. **Check User** – Validar credenciales y obtener escuelas disponibles
2. **Select School** – Seleccionar escuela (auto si solo hay una)
3. **Select Season** – Seleccionar temporada (auto si hay una activa por fecha)
4. **Dashboard Access** – Acceso completo con contexto establecido

> Todos estos pasos retornan el campo `user.role` para indicar el rol principal del usuario.

### Endpoints de Autenticación

| Endpoint | Método | Propósito | Requiere Auth |
|----------|--------|-----------|---------------|
| `/api/v5/auth/check-user` | POST | Validar credenciales y obtener escuelas | ❌ |
| `/api/v5/auth/select-school` | POST | Seleccionar escuela específica | ✅ (temp token) |
| `/api/v5/auth/select-season` | POST | Seleccionar temporada específica | ✅ (school token) |
| `/api/v5/auth/initial-login` | POST | Login directo con parámetros completos | ❌ |
| `/api/v5/auth/logout` | POST | Cerrar sesión y revocar token | ✅ |
| `/api/v5/auth/me` | GET | Información del usuario actual | ✅ |

## 🛡️ Sistema de Middleware

### 1. ContextMiddleware
**Archivo**: `app/Http/Middleware/V5/ContextMiddleware.php`
**Alias**: `context.middleware`

**Funciones**:
- Valida autenticación con guard `api_v5`
- Extrae `school_id` y `season_id` del token o headers
- Verifica acceso del usuario a la escuela y temporada
- Inyecta contexto en el request
- Añade headers de respuesta

### 2. ContextPermissionMiddleware
**Archivo**: `app/Http/Middleware/V5/ContextPermissionMiddleware.php`
**Alias**: `role.permission.middleware`

**Sistema de Permisos en 3 Niveles**:
- **Global**: `global.admin`, `global.support`
- **School**: `school.admin`, `school.manager`, `school.staff`
- **Season**: `season.admin`, `season.manager`, `season.view`, `season.bookings`

## 🗂️ Estructura de Rutas

### Rutas Públicas (Sin Auth)
```php
Route::prefix('auth')->group(function () {
    Route::post('check-user', [AuthController::class, 'checkUser']);
    Route::post('initial-login', [AuthController::class, 'initialLogin']);
});
```

### Rutas Autenticadas (Solo Token)
```php
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/select-school', [AuthController::class, 'selectSchool']);
    Route::post('auth/select-season', [AuthController::class, 'selectSeason']);
});
```

### Rutas con Context Completo
```php
Route::middleware(['auth:sanctum', 'context.middleware'])->group(function () {
    Route::middleware(['role.permission.middleware:season.admin'])
        ->prefix('seasons')->group(function () {
        Route::get('/', [SeasonController::class, 'index']);
        Route::post('/', [SeasonController::class, 'store']);
    });
    
    Route::middleware(['role.permission.middleware:season.analytics'])
        ->prefix('dashboard')->group(function () {
        Route::get('stats', [DashboardV5Controller::class, 'stats']);
    });
});
```

## 🎪 Tokens y Context Data

La columna `personal_access_tokens.context_data` almacena un objeto JSON con las claves `school_id` y `season_id`, por lo que no debe crearse una tabla separada para el contexto.

### Estructura del Token
```php
'context_data' => json_encode([
    'school_id' => int,
    'school_slug' => string,
    'season_id' => int|null,
    'season_name' => string|null,
    'login_at' => ISO8601_timestamp,
    'is_temporary' => bool
])
```

### Headers Requeridos

**Para endpoints de gestión de temporadas (listar, crear):**
```http
Authorization: Bearer {token}
X-School-ID: {school_id}
Content-Type: application/json
```

**Para endpoints que operan sobre una temporada específica:**
```http
Authorization: Bearer {token}
X-School-ID: {school_id}
X-Season-ID: {season_id}
Content-Type: application/json
```

**Nota:** Los headers `X-School-ID` y `X-Season-ID` son añadidos automáticamente por el `AuthV5Interceptor` cuando se usa el `ApiV5Service` en el frontend. El contexto se obtiene del token o de los headers explícitos.

## 🧪 Testing

### Usuarios de Prueba
| Email | Password | Escuelas | Rol |
|-------|----------|----------|-----|
| `admin@boukii-v5.com` | `password` | Todas | admin |
| `multi@boukii-v5.com` | `password` | School ID 2 | admin |

### Ejecutar Tests
```bash
# Tests unitarios
php artisan test tests/Unit/V5/

# Tests de feature
php artisan test tests/Feature/V5/

# Test del flujo completo
php artisan test tests/Feature/V5/AuthFlowIntegrationTest.php
```

### Ejecutar Seeds
```bash
php artisan db:seed --class=V5TestUsersSeeder
php artisan db:seed --class=V5TestSeasonsSeeder
```

## 🚨 Error Codes

- **401**: `UNAUTHORIZED` - Token inválido
- **403**: `FORBIDDEN` - Sin permisos
- **403**: `SCHOOL_ACCESS_DENIED` - Sin acceso a escuela
- **403**: `SEASON_ACCESS_DENIED` - Sin acceso a temporada
- **400**: `MISSING_CONTEXT` - Context faltante

## 🔄 Estado Actual

### ✅ Completado
- [x] Sistema de autenticación multi-tenant
- [x] Middleware de contexto y permisos
- [x] Tests unitarios y de integración
- [x] Seeds de datos de prueba
- [x] Documentación actualizada

### 📊 Debug Endpoints
- `POST /api/v5/debug-raw-token` - Info del token
- `POST /api/v5/debug-token` - Info con contexto

---

**Documentación actualizada:** Enero 2025  
**Versión:** V5.1.0  
**Estado:** ✅ **PRODUCCIÓN READY**
