# BOUKII V5 - Documentación Técnica Completa

## 1. ARQUITECTURA GENERAL

### 1.1 Estructura del Sistema
```
Boukii Platform
├── Laravel API Backend (/api-boukii)
│   ├── V4 Legacy Routes (/routes/api/*.php)
│   ├── V5 Modern Routes (/routes/api_v5/*.php)
│   └── Shared Models & Database
├── Angular V5 Admin Panel (/front)
├── Angular Legacy Panel (/front-legacy)
├── Angular Booking Page (separate repo, slug-based)
└── Ionic Mobile App (monitors)
```

### 1.2 Contexto Multi-Tenant
- **Usuario**: Puede pertenecer a múltiples escuelas
- **Escuela**: Tiene múltiples temporadas (seasons) y estaciones (stations)
- **Temporada**: Contexto temporal para reservas/cursos
- **Estación**: Contexto geográfico para meteorología y ubicación

### 1.3 Flujo de Autenticación V5
```
Login → Select School (if multiple) → Select Season (if multiple) → Dashboard
```

### 1.4 Roles y Permisos
- **Superadmin**: Acceso total al sistema
- **Admin**: Gestión completa de su escuela
- **Manager**: Operaciones diarias
- **Monitor**: App móvil + vista limitada panel
- **Client**: Solo página de reservas

**Permisos por contexto**: Escuela + Temporada

## 2. BACKEND - LARAVEL API

### 2.1 Estructura de Rutas
```php
// V5 Routes Structure
/api/v5/auth/*           - Autenticación multi-school/season
/api/v5/dashboard/*      - Dashboard con métricas reales
/api/v5/scheduler/*      - Planificador calendario
/api/v5/bookings/*       - CRUD Reservas
/api/v5/courses/*        - CRUD Cursos
/api/v5/vouchers/*       - Bonos/Códigos/Cupones
/api/v5/communications/* - Emails masivos
/api/v5/chat/*           - Sistema de chat
/api/v5/analytics/*      - Estadísticas avanzadas
/api/v5/monitors/*       - CRUD Monitores
/api/v5/clients/*        - CRUD Clientes
/api/v5/users/*          - Administradores
/api/v5/settings/*       - Configuraciones
/api/v5/renting/*        - Módulo rental material
```

### 2.2 Modelos Principales
```php
// Core Models
User, School, Season, Station

// Business Models  
Course, Booking, BookingUser, Monitor, Client
CourseGroup, CourseSubgroup, CourseDate

// Configuration
Setting, Module, Permission, Role

// New V5 Models
VoucherType, CommunicationTemplate, ChatMessage
RentalItem, RentalCategory, RentalBooking
```

### 2.3 Middleware V5
```php
'auth:sanctum'      - Autenticación JWT
'context.required'  - School + Season context
'permission:xyz'    - Verificación permisos
'module.access:xyz' - Acceso a módulos contratados
```

### 2.4 Controladores V5 Pattern
```php
// Estructura estándar para todos los controladores
class XController extends Controller
{
    public function index(Request $request): JsonResponse
    public function show($id): JsonResponse  
    public function store(Request $request): JsonResponse
    public function update(Request $request, $id): JsonResponse
    public function destroy($id): JsonResponse
    
    // Context helpers
    private function getSchoolContext(): int
    private function getSeasonContext(): int
    private function validatePermissions(string $permission): bool
}
```

## 3. FRONTEND - ANGULAR V5

### 3.1 Arquitectura Angular
```
/front
├── /src/app
│   ├── /core
│   │   ├── /services (auth, api, logging)
│   │   ├── /guards (auth, permissions)
│   │   └── /interceptors (token, context)
│   ├── /shared
│   │   ├── /components (ui components)
│   │   ├── /pipes (formatters)
│   │   └── /directives
│   ├── /features
│   │   ├── /auth (login/school/season selection)
│   │   ├── /dashboard
│   │   ├── /scheduler
│   │   ├── /bookings ⭐
│   │   ├── /courses ⭐
│   │   ├── /vouchers
│   │   ├── /communications
│   │   ├── /chat (new)
│   │   ├── /analytics
│   │   ├── /monitors
│   │   ├── /clients
│   │   ├── /users
│   │   ├── /settings
│   │   └── /renting (new)
│   └── /ui
│       ├── /layouts (auth, app)
│       └── /themes (light/dark variables)
```

### 3.2 Servicios Core
```typescript
// AuthV5Service - Gestión completa autenticación
// ApiService - HTTP client con contexto automático
// PermissionsService - Validación permisos
// ThemeService - Light/Dark mode
// NotificationService - Toasts y alerts
// WebSocketService - Real-time updates
```

### 3.3 Guards y Resolvers
```typescript
AuthGuard - Usuario autenticado
SchoolGuard - Escuela seleccionada  
SeasonGuard - Temporada seleccionada
PermissionGuard - Permisos específicos
ModuleGuard - Módulo contratado
```

### 3.4 Componentes Pattern
```typescript
// Estructura estándar para páginas
@Component({
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, ...],
  template: `<!-- HTML con signals -->`,
  styles: [`/* SCSS con variables CSS */`]
})
export class XListPageComponent implements OnInit {
  // Signals para estado reactivo
  items = signal<X[]>([]);
  loading = signal(false);
  filters = signal<XFilters>({});
  
  // Services inyectados
  private xService = inject(XService);
  private router = inject(Router);
  
  ngOnInit() {
    this.loadItems();
  }
  
  // Métodos CRUD estándar
  loadItems() {}
  createItem() {}
  editItem(id: number) {}
  deleteItem(id: number) {}
}
```

## 4. PANTALLAS PRINCIPALES DETALLADAS

### 4.1 🏠 Dashboard
**Funcionalidad**:
- Métricas del día (reservas, cursos, ingresos)
- Condiciones meteorológicas (AccuWeather + estaciones)
- Resumen actividad reciente
- Accesos rápidos

**APIs**:
- `GET /dashboard/stats` - KPIs principales
- `GET /dashboard/weather?station_id=X` - Datos meteorológicos
- `GET /dashboard/weather-stations` - Estaciones disponibles
- `GET /dashboard/recent-activity` - Actividad reciente
- `GET /dashboard/alerts` - Alertas y notificaciones
- `GET /dashboard/quick-actions` - Acciones rápidas por rol/contexto
- `GET /dashboard/performance-metrics` - Métricas comparativas (mes actual vs anterior) + tendencia 7 días
- `GET /dashboard/revenue-chart` - Series de ingresos (objetivo/admin/online)
- `GET /dashboard/bookings-by-type` - Reservas por día/segmento

### 4.2 📅 Planificador ⭐
**Funcionalidad**:
- Vista calendario (día/semana/mes) de monitores
- Drag & drop reservas entre monitores
- Validaciones automáticas (nivel, idioma, disponibilidad)
- Creación bloqueos y indisponibilidades
- Filtros avanzados (monitor, cliente, deporte, tipo)

**APIs**:
- `GET /scheduler/view?date=X&view=day|week|month` - Datos calendario
- `POST /scheduler/move-booking` - Mover reserva
- `GET /scheduler/monitors-availability` - Disponibilidad monitores
- `POST /scheduler/block` - Crear bloqueo

### 4.3 🎫 Reservas ⭐⭐ (Joya de la Corona)
**Funcionalidad**:
- CRUD completo reservas
- Búsquedas avanzadas y filtros múltiples
- Gestión pagos (PayRexx integration)
- Cancelaciones y reembolsos
- Generación automática grupos/subgrupos
- Multi-participantes por reserva

**APIs**:
- `GET /bookings` - Listado con filtros
- `GET /bookings/{id}` - Detalle reserva
- `POST /bookings` - Crear reserva
- `PUT /bookings/{id}` - Editar reserva
- `DELETE /bookings/{id}` - Cancelar reserva
- `POST /bookings/{id}/payment` - Procesar pago

### 4.4 🏂 Cursos ⭐⭐ (Joya de la Corona)
**Funcionalidad**:
- CRUD cursos colectivos/privados/flexibles
- Gestión fechas múltiples
- Configuración grupos y niveles automática
- Gestión extras opcionales
- Traducciones automáticas (DeepL)
- Estadísticas participación

**APIs**:
- `GET /courses` - Listado con filtros
- `GET /courses/{id}` - Detalle curso
- `POST /courses` - Crear curso
- `PUT /courses/{id}` - Editar curso
- `GET /courses/{id}/bookings` - Reservas del curso
- `POST /courses/{id}/groups` - Gestionar grupos

### 4.5 🎁 Bonos y Códigos (Mejorado)
**Funcionalidad**:
- Bonos de compra tradicionales
- Bonos regalo con códigos
- Cupones descuento
- Códigos promocionales
- Validez temporal y usos limitados

**APIs**:
- `GET /vouchers` - Listado todos los tipos
- `POST /vouchers/purchase` - Bonos compra
- `POST /vouchers/gift` - Bonos regalo  
- `POST /vouchers/coupon` - Cupones descuento
- `GET /vouchers/{code}/validate` - Validar código

### 4.6 📧 Comunicaciones
**Funcionalidad**:
- Emails masivos por segmentos
- Plantillas personalizables (WYSIWYG)
- Historial envíos
- Métricas entrega/apertura

**APIs**:
- `GET /communications/templates` - Plantillas
- `POST /communications/send` - Envío masivo
- `GET /communications/history` - Historial

### 4.7 💬 Chat (Nuevo)
**Funcionalidad**:
- Chat interno escuela-monitores
- Notificaciones tiempo real
- Integración WhatsApp Business API
- Historial conversaciones

**APIs**:
- `GET /chat/conversations` - Lista conversaciones
- `POST /chat/send` - Enviar mensaje
- WebSocket para tiempo real

### 4.8 📊 Estadísticas (Rediseñado)
**Funcionalidad**:
- Dashboard ingresos por período
- Análisis métodos pago
- Horas monitores y salarios
- Comparativas temporadas
- Exportación reportes

**APIs**:
- `GET /analytics/revenue` - Análisis ingresos
- `GET /analytics/monitors-hours` - Horas trabajadas
- `GET /analytics/payment-methods` - Métodos pago
- `GET /analytics/seasonal-comparison` - Comparativas

### 4.9 👥 Monitores
**Funcionalidad**:
- CRUD monitores con deportes/niveles
- Gestión salarios por temporada
- Disponibilidad y bloqueos
- Evaluaciones clientes

**APIs**:
- `GET /monitors` - Listado
- `POST /monitors` - Crear
- `GET /monitors/{id}/schedule` - Horarios
- `PUT /monitors/{id}/salary` - Actualizar salario

### 4.10 👤 Clientes  
**Funcionalidad**:
- CRUD clientes con utilizadores
- Progreso en deportes
- Historial reservas
- Evaluaciones recibidas

**APIs**:
- `GET /clients` - Listado
- `GET /clients/{id}/progress` - Progreso deportes
- `GET /clients/{id}/bookings` - Historial reservas

### 4.11 👨‍💼 Administradores
**Funcionalidad**:
- Gestión usuarios sistema
- Asignación roles por escuela/temporada
- Permisos granulares
- Auditoría acciones

**APIs**:
- `GET /users` - Lista usuarios
- `POST /users/{id}/roles` - Asignar roles
- `GET /users/{id}/permissions` - Permisos usuario

### 4.12 ⚙️ Ajustes
**Funcionalidad**:
- Configuración escuela
- Gestión temporadas/estaciones
- Deportes y niveles
- Precios cursos flexibles
- Página reservas (tema, banners)
- Configuración emails

**APIs**:
- `GET /settings/school` - Config escuela
- `GET /settings/sports` - Deportes/niveles
- `PUT /settings/booking-page` - Config página reservas

### 4.13 🛷 Renting (Nuevo Módulo)
**Funcionalidad**:
- CRUD material alquiler
- Categorías y disponibilidad
- Precios por período
- Integración con reservas cursos
- Control stock tiempo real

**APIs**:
- `GET /renting/items` - Material disponible
- `GET /renting/availability` - Disponibilidad fechas
- `POST /renting/book` - Reservar material
- `GET /renting/categories` - Categorías

## 5. INTEGRACIÓN DE SISTEMAS

### 5.1 AccuWeather API
- Estaciones configurables por escuela
- Cache 30 minutos para optimizar calls
- Fallback a datos locales si API falla

### 5.2 PayRexx Payment Gateway
- Integración completa pagos
- Webhooks para confirmaciones
- Reembolsos automáticos

### 5.3 DeepL Translation
- Traducciones automáticas cursos
- Soporte múltiples idiomas
- Cache traducciones frecuentes

### 5.4 WhatsApp Business
- Chat integrado panel
- Notificaciones automáticas
- Plantillas mensajes aprobadas

## 6. MIGRACIÓN V4 → V5

### 6.1 Estrategia de Coexistencia
1. **Fase 1**: V5 en paralelo, sin afectar V4
2. **Fase 2**: Migración gradual por escuela
3. **Fase 3**: Desactivación V4 cuando V5 esté completa

### 6.2 Migración de Datos
```php
// Commands de migración
php artisan migrate:v5:users
php artisan migrate:v5:schools  
php artisan migrate:v5:courses
php artisan migrate:v5:bookings
php artisan migrate:v5:settings
```

### 6.3 Compatibility Layer
- Middlewares para mantener APIs V4 funcionando
- Mappers automáticos V4 → V5 models
- Feature flags para activar/desactivar V5 por escuela

## 7. CONSIDERACIONES TÉCNICAS

### 7.1 Performance
- Redis cache para datos frecuentes
- Database indexing optimizado
- Lazy loading en frontend
- Image optimization automática

### 7.2 Seguridad
- JWT tokens con refresh
- Rate limiting por usuario
- Input validation exhaustiva
- CORS configurado correctamente

### 7.3 Monitoring
- Logs estructurados (Laravel Log)
- Error tracking (opcional Sentry)
- Performance metrics
- User activity tracking

### 7.4 Escalabilidad
- Database sharding por escuela
- CDN para assets estáticos
- Load balancing ready
- Microservices preparado

## 8. DESPLIEGUE Y CONFIGURACIÓN

### 8.1 Environment Variables
```env
# V5 Specific
V5_ENABLED=true
V5_MIGRATION_MODE=gradual
V5_DEBUG_MODE=false

# API Keys
ACCUWEATHER_API_KEY=xxx
PAYREXX_API_KEY=xxx  
DEEPL_API_KEY=xxx
WHATSAPP_TOKEN=xxx
```

### 8.2 Server Requirements
- PHP 8.1+
- Node.js 18+  
- MySQL 8.0+
- Redis 6.0+
- SSL Certificate

## 9. PRÓXIMOS PASOS

1. ✅ Arquitectura base V5
2. 🔄 Sistema autenticación multi-context
3. 📋 Pantallas básicas funcionales
4. 🎨 Sistema de estilos global
5. 🔧 Funcionalidades core
6. 🧪 Testing integración
7. 🚀 Despliegue gradual

---

**Fecha**: $(date)
**Versión**: 5.0.0-alpha
**Estado**: En desarrollo activo
