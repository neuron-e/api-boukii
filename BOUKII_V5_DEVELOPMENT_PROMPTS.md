# BOUKII V5 - Prompts de Desarrollo Específicos

## 🎯 METODOLOGÍA DE DESARROLLO

### Workflow por Pantalla:
1. **Prompt Backend** → Crear APIs y controladores
2. **Prompt Frontend** → Crear componente Angular 
3. **Test Compilación** → Verificar que compila sin errores
4. **Test Local** → Probar funcionalidad básica
5. **✅ Aprobar** → Continuar siguiente pantalla
6. **🔧 Refinar** → Corregir errores si los hay

### Estándares de Calidad:
- ✅ Compilación sin errores TypeScript/PHP
- ✅ Linting passes (ESLint/PHP CS)
- ✅ Funcionalidad básica operativa
- ✅ UI responsiva y profesional
- ✅ Integración backend-frontend cohesionada

---

## 📋 FASE 1: INFRAESTRUCTURA BASE

### PROMPT 1.1: Auth System V5 - Backend
```
Necesito implementar el sistema de autenticación V5 para Boukii que soporte multi-school y multi-season context.

REQUERIMIENTOS BACKEND:
1. Actualizar AuthController V5 para soportar el flow: Login → Select School → Select Season
2. Middleware 'context.required' que valide X-School-ID y X-Season-ID headers
3. Endpoints:
   - POST /auth/check-user (email, password) → retorna schools disponibles
   - POST /auth/select-school (school_id) → retorna seasons disponibles  
   - POST /auth/select-season (season_id) → completa login con contexto completo
   - GET /auth/me → información usuario actual con contexto

ESTRUCTURA RESPONSE:
```json
{
  "success": true,
  "data": {
    "user": {...},
    "token": "jwt_token",
    "schools": [...],
    "current_school": {...},
    "current_season": {...},
    "permissions": [...]
  }
}
```

VALIDACIONES:
- Usuario debe tener acceso a la escuela seleccionada
- Season debe estar activa o usuario debe tener permisos admin
- Tokens JWT con claims de school_id y season_id
- Rate limiting en endpoints de auth

ARCHIVOS A MODIFICAR/CREAR:
- app/Http/Controllers/V5/AuthController.php
- app/Http/Middleware/V5/ContextRequired.php
- routes/api_v5/auth.php
- Actualizar User model con relaciones schools

Genera el código completo funcional siguiendo las mejores prácticas Laravel. Asegúrate de que sea compatible con el AuthV5Service del frontend que ya existe.
```

### PROMPT 1.2: Auth System V5 - Frontend
```
Actualiza el sistema de autenticación del frontend Angular V5 para que funcione perfectamente con el backend multi-context.

REQUERIMIENTOS FRONTEND:
1. Actualizar AuthV5Service para manejar el nuevo flow de 3 pasos
2. Crear/actualizar páginas:
   - LoginPageComponent 
   - SchoolSelectionPageComponent
   - SeasonSelectionPageComponent
3. Actualizar guards y interceptors para el nuevo contexto
4. Integrar con el layout de auth existente

FLUJO COMPLETO:
1. Usuario ingresa credenciales → AuthV5Service.checkUser()
2. Si múltiples escuelas → navigate('/select-school')
3. Si múltiples seasons → navigate('/select-season') 
4. Contexto completo → navigate('/dashboard')

FUNCIONALIDADES:
- Auto-selección si solo hay 1 school/season
- Persistencia del contexto en localStorage
- Headers automáticos X-School-ID, X-Season-ID en todas las requests
- Manejo de errores y redirecciones apropiadas
- Interfaz limpia y profesional con Material UI

ARCHIVOS A MODIFICAR:
- src/app/core/services/auth-v5.service.ts (ya existe, actualizar)
- src/app/features/auth/pages/login.page.ts
- src/app/features/school-selection/select-school.page.ts  
- src/app/features/seasons/select-season.page.ts
- src/app/core/interceptors/context.interceptor.ts
- src/app/app.routes.ts

INTERFAZ:
- Usar los layouts existentes auth-layout
- Estilos consistent es con variables CSS light/dark
- Loading states y error handling
- Responsive design

Implementa todo el sistema de auth funcionando end-to-end. El AuthV5Service ya tiene la estructura base, adáptalo para que funcione con la nueva API.
```

---

## 📊 FASE 2: DASHBOARD MEJORADO

### PROMPT 2.1: Dashboard V5 - Backend
```
Completa la implementación del dashboard V5 con datos reales y funcionalidad de estaciones meteorológicas.

CONTEXTO: Ya tenemos DashboardController creado, necesito completarlo y mejorarlo.

REQUERIMIENTOS:
1. Mejorar endpoints existentes:
   - GET /dashboard/stats → KPIs reales de la base de datos
   - GET /dashboard/weather → AccuWeather integration con estaciones
   - GET /dashboard/weather-stations → estaciones configuradas para la escuela
   - GET /dashboard/recent-activity → actividades recientes reales

2. Nuevos endpoints necesarios:
   - GET /dashboard/quick-actions → acciones rápidas basadas en rol usuario
   - GET /dashboard/alerts → alertas y notificaciones importantes
   - GET /dashboard/performance-metrics → métricas comparativas mes anterior

DATOS REALES REQUERIDOS:
- Reservas hoy vs ayer vs mismo día semana pasada
- Ingresos del día/semana/mes vs períodos anteriores  
- Monitores activos vs disponibles vs ocupados
- Cursos iniciando hoy, esta semana
- Pagos pendientes, cancelaciones recientes
- Ocupación promedio monitores

INTEGRACIÓN ACCUWEATHER:Haz pruebas de que 
- Usar modelo Station existente con coordinates
- Implementar cache inteligente (30min para weather, 1 día para estaciones)
- Fallback a datos vacíos si AccuWeather falla
- Support para múltiples estaciones por escuela

ARCHIVOS A MODIFICAR:
- app/Http/Controllers/V5/DashboardController.php (ya existe, completar)
- routes/api_v5/dashboard.php (ya existe, añadir nuevas rutas)

Genera código production-ready con manejo de errores, cache optimization y documentación en código.
```

### PROMPT 2.2: Dashboard V5 - Frontend  
```
Actualiza completamente el dashboard frontend para mostrar datos reales y funcionalidad profesional.

REQUERIMIENTOS:
1. Mejorar DashboardPageComponent existente:
   - Integrar con todas las APIs del backend
   - Selector de estación meteorológica funcional
   - Métricas en tiempo real con comparativas
   - Quick actions basadas en rol del usuario
   - Alertas y notificaciones importantes

2. Crear componentes hijo:
   - WeatherStationSelectorComponent 
   - MetricsCardsComponent
   - RecentActivityComponent
   - QuickActionsComponent
   - PerformanceChartsComponent

3. Funcionalidades avanzadas:
   - Auto-refresh cada 5 minutos
   - Personalización métricas por rol
   - Click-through hacia pantallas específicas
   - Loading states professional es
   - Error handling con retry

INTERFAZ PROFESIONAL:
- Cards con hover effects y animations
- Charts con Chart.js o similar
- Color coding para métricas (green/yellow/red)
- Responsive grid layout
- Dark/light mode support completo

INTEGRACIÓN DATOS REALES:
- No más datos mock - solo datos reales de la API
- Handle gracefully cuando no hay datos
- Show loading skeletons mientras carga
- Error states informativos

ARCHIVOS A MODIFICAR:
- src/app/features/dashboard/dashboard.page.ts (existe)
- src/app/features/dashboard/dashboard.page.html (existe)
- src/app/features/dashboard/dashboard.page.scss (existe) 
- src/app/features/dashboard/services/dashboard.service.ts (existe)

CREAR NUEVOS:
- src/app/features/dashboard/components/weather-selector.component.ts
- src/app/features/dashboard/components/metrics-cards.component.ts
- src/app/features/dashboard/components/activity-feed.component.ts

Implementa un dashboard profesional, visualmente atractivo y 100% funcional con datos reales. Prioriza la UX y la información útil para el usuario.
```

---

## 📅 FASE 3: PLANIFICADOR (SCHEDULER)

### PROMPT 3.1: Scheduler V5 - Backend
```
Implementa el sistema completo del planificador (scheduler) V5 con funcionalidad avanzada de drag & drop y gestión calendario.

CONTEXTO: El planificador es una vista calendario que muestra monitores y sus reservas/cursos, permitiendo mover reservas entre monitores con validaciones.

REQUERIMIENTOS BACKEND:
1. Crear SchedulerController con endpoints:
   - GET /scheduler/view → datos calendario por día/semana/mes
   - GET /scheduler/monitors-availability → disponibilidad monitores con validaciones
   - POST /scheduler/move-booking → mover reserva con validaciones automáticas
   - POST /scheduler/create-block → crear bloqueos monitores  
   - GET /scheduler/filters → filtros disponibles (monitores, deportes, tipos)

2. Validaciones inteligentes para mover reservas:
   - Monitor disponible en nuevo horario
   - Monitor tiene nivel adecuado para el curso/deporte
   - Monitor habla idioma requerido
   - No hay conflictos con otros bookings
   - Warnings si hay problemas menores (nivel sugerido vs asignado)

3. Estructura response optimizada:
```json
{
  "date_range": {"start": "2024-01-01", "end": "2024-01-07"},
  "monitors": [
    {
      "id": 1, "name": "Monitor Name", "sports": [...], "levels": [...],
      "schedule": [
        {
          "time_slot": "09:00", "duration": 120,
          "booking": {...}, "course": {...}, "availability": "available|busy|blocked"
        }
      ]
    }
  ],
  "view_config": {"time_start": "08:00", "time_end": "18:00", "interval": 30}
}
```

MODELOS A USAR:
- Monitor, Booking, Course, MonitorAvailability
- Nuevos: ScheduleBlock, MonitorSchedule

Implementa sistema robusto con validaciones complejas y performance optimizado para calendarios grandes.
```

### PROMPT 3.2: Scheduler V5 - Frontend
```
Crea el componente planificador (scheduler) con funcionalidad completa de calendario y drag & drop.

REQUERIMIENTOS:
1. SchedulerPageComponent principal:
   - Vista día/semana/mes switcheable
   - Filtros avanzados (monitor, cliente, deporte, tipo, estado)
   - Drag & drop entre monitores con validaciones visuales
   - Modal detalle reserva/curso al hacer click
   - Creación rápida bloqueos y eventos

2. Componentes hijo necesarios:
   - CalendarViewComponent (día/semana/mes)
   - MonitorRowComponent (fila de cada monitor)
   - BookingCardComponent (tarjeta arrastrable)
   - FilterPanelComponent (filtros laterales)
   - BookingDetailModalComponent (modal información)
   - CreateBlockModalComponent (crear bloqueos)

3. Funcionalidades avanzadas:
   - Drag & drop con CDK Angular
   - Validaciones visuales (colores, warnings)
   - Loading states durante operaciones
   - Real-time updates (WebSocket futuro)
   - Shortcuts de teclado (crear, navegar)
   - Export/print calendar view

INTERFAZ PROFESIONAL:
- Inspiración Google Calendar/Outlook
- Color coding por deporte, tipo, estado
- Hover effects y smooth animations
- Responsive (colapsa en móvil a vista lista)
- Dark/light theme support

INTEGRACIONES:
- Navegación directa desde dashboard metrics
- Links hacia detalle reserva/curso
- Botón "crear reserva" pre-llena datos contexto

ARCHIVOS A CREAR:
- src/app/features/scheduler/scheduler.page.ts
- src/app/features/scheduler/scheduler.page.html
- src/app/features/scheduler/components/*.ts
- src/app/features/scheduler/services/scheduler.service.ts

Crea un planificador profesional, intuitivo y potente que sea el corazón operativo del sistema.
```

---

## 🎫 FASE 4: RESERVAS (JOYA DE LA CORONA)

### PROMPT 4.1: Bookings V5 - Backend
```
Implementa el sistema COMPLETO de reservas V5 - la funcionalidad más crítica del sistema.

REQUERIMIENTOS BACKEND:
1. BookingController V5 completo:
   - GET /bookings → listado con filtros avanzados, paginación, búsqueda
   - GET /bookings/{id} → detalle completo con todas las relaciones
   - POST /bookings → crear reserva con validaciones exhaustivas
   - PUT /bookings/{id} → editar reserva con recálculos automáticos
   - DELETE /bookings/{id} → cancelar con políticas reembolso
   - POST /bookings/batch → crear múltiples reservas relacionadas

2. Funcionalidades críticas:
   - Generación automática grupos/subgrupos para cursos colectivos
   - Multi-participantes por reserva (cliente + utilizadores)
   - Integración completa PayRexx para pagos
   - Cálculo automático precios con extras y descuentos
   - Gestión estados: pending → confirmed → in_progress → completed → cancelled
   - Emails automáticos por cambios de estado

3. APIs adicionales necesarias:
   - GET /bookings/search → búsqueda avanzada full-text
   - POST /bookings/{id}/payment → procesar pago
   - POST /bookings/{id}/cancel → cancelar con reembolso
   - GET /bookings/{id}/related → reservas relacionadas
   - PUT /bookings/{id}/participants → gestionar participantes
   - GET /bookings/availability → check disponibilidad

4. Validaciones complejas:
   - Monitor disponible y con nivel apropiado
   - Cliente no tiene conflictos horarios
   - Capacidad curso no excedida  
   - Extras disponibles en fechas solicitadas
   - Políticas cancelación según timing

MODELOS A OPTIMIZAR:
- Booking, BookingUser, BookingExtra, BookingPayment
- Course, CourseGroup, CourseSubgroup
- PaymentLog, RefundRequest

ESTRUCTURA RESPONSE DETALLADA:
```json
{
  "booking": {
    "id": 123, "status": "confirmed", "total_price": 150.00,
    "course": {...}, "monitor": {...}, "client": {...},
    "participants": [...], "extras": [...], "payments": [...],
    "schedule": [...], "cancellation_policy": {...}
  },
  "related_bookings": [...],
  "available_actions": ["edit", "cancel", "add_participant"]
}
```

Implementa el sistema más robusto y completo posible - esta es la funcionalidad core del negocio.
```

### PROMPT 4.2: Bookings V5 - Frontend
```
Crea el sistema COMPLETO de gestión de reservas frontend - debe ser intuitivo, potente y libre de errores.

REQUERIMIENTOS:
1. BookingsListPageComponent:
   - Tabla/cards con datos esenciales y acciones
   - Filtros múltiples: estado, cliente, curso, fecha, tipo, pago
   - Búsqueda instantánea (debounce 300ms)  
   - Ordenamiento por columnas
   - Paginación con virtual scrolling
   - Export a Excel/PDF
   - Acciones batch (cancelar múltiples, enviar emails)

2. BookingFormPageComponent (Crear/Editar):
   - Wizard multi-step: Cliente → Curso → Participantes → Extras → Pago
   - Validaciones tiempo real
   - Preview precio en tiempo real
   - Gestión participantes dinámica
   - Integración calendario para fechas
   - Auto-save borrador

3. BookingDetailPageComponent:
   - Vista completa con tabs: Info, Participantes, Pagos, Timeline  
   - Timeline actividad/cambios
   - Edición inline campos sencillos
   - Gestión pagos integrada
   - Acciones contextuales por estado

4. Componentes auxiliares críticos:
   - ClientSelectorComponent con búsqueda async
   - CourseSelectorComponent con calendario disponibilidad
   - ParticipantManagerComponent con validaciones
   - ExtrasPickerComponent con precio preview
   - PaymentFormComponent integración PayRexx
   - CancellationModalComponent con políticas

FUNCIONALIDADES AVANZADAS:
- Auto-complete inteligente con historial usuario
- Sugerencias automáticas (similar reservas anteriores)
- Warnings visuales conflictos/problemas
- Real-time validation feedback
- Mobile-responsive complete
- Keyboard shortcuts power users

FLUJO CREAR RESERVA:
1. Seleccionar cliente (search/create nuevo)
2. Elegir participantes (del cliente seleccionado)
3. Seleccionar curso (calendar view disponibilidad)
4. Configurar detalles (fechas, extras)
5. Review final con preview precio
6. Procesar pago (si requerido)
7. Confirmación con opciones siguiente acción

ERROR HANDLING:
- Validaciones visuales inmediatas  
- Rollback automático si falla pago
- Recovery mode si se pierde conexión
- Mensajes error user-friendly

ARCHIVOS A CREAR:
- src/app/features/bookings/pages/ (list, form, detail)
- src/app/features/bookings/components/ (todos los auxiliares)
- src/app/features/bookings/services/booking.service.ts
- src/app/features/bookings/models/booking.interfaces.ts

Esta es la JOYA DE LA CORONA - debe ser perfecta, intuitiva y completamente libre de bugs. La experiencia del usuario aquí define el éxito del producto.
```

---

## 🏂 FASE 5: CURSOS (JOYA DE LA CORONA #2)

### PROMPT 5.1: Courses V5 - Backend  
```
Implementa el sistema COMPLETO de cursos V5 - funcionalidad crítica para la gestión académica.

REQUERIMIENTOS BACKEND:
1. CourseController V5 completo:
   - GET /courses → listado con filtros, estados, deportes
   - GET /courses/{id} → detalle completo con grupos, participantes, estadísticas
   - POST /courses → crear curso con configuración automática grupos
   - PUT /courses/{id} → editar con recálculo grupos y precios
   - DELETE /courses/{id} → archivar curso (soft delete)
   - GET /courses/{id}/bookings → reservas del curso con participantes

2. Gestión grupos automática:
   - Creación automática grupos por nivel
   - Cálculo capacidad y distribución participantes
   - Auto-asignación monitores por deporte y nivel
   - Balanceado cargas entre grupos
   - Splitting automático si grupo excede capacidad

3. APIs específicas cursos:
   - POST /courses/{id}/groups → gestionar grupos manualmente
   - GET /courses/{id}/statistics → estadísticas participación, revenue
   - POST /courses/{id}/notify → enviar emails masivos participantes
   - GET /courses/templates → plantillas curso pre-configuradas
   - POST /courses/{id}/clone → duplicar curso para nueva temporada

4. Tipos de curso soportados:
   - **Privado**: 1-1 o familia, horario flexible
   - **Colectivo**: Grupos fijos, niveles definidos
   - **Flexible**: Reservas individuales dentro periodo curso

5. Funcionalidades avanzadas:
   - Integración DeepL para traducciones automáticas
   - Generación QR codes para acceso rápido
   - Sistema evaluaciones participantes
   - Control asistencia integrado
   - Gestión extras per-curso

MODELOS COMPLEJOS:
- Course, CourseGroup, CourseSubgroup, CourseDate
- CourseTranslation, CourseExtra, CourseEvaluation
- CourseTemplate, CourseStatistic

VALIDACIONES CRÍTICAS:
- Fechas coherentes con tipo curso
- Monitores disponibles en horarios
- Capacidades grupos no excedidas
- Niveles monitores match requerimientos curso
- Precios coherentes con configuración escuela

ESTRUCTURA RESPONSE:
```json
{
  "course": {
    "id": 456, "name": "Ski Beginner Week", "type": "collective",
    "dates": [...], "groups": [...], "monitors": [...],
    "statistics": {"bookings": 25, "revenue": 3750, "capacity_used": 0.85},
    "settings": {"max_participants": 30, "levels": [...], "extras": [...]}
  },
  "bookings": [...], "evaluations": [...], "translations": [...]
}
```

Implementa sistema robusto que maneje la complejidad de diferentes tipos de curso y automations inteligentes.
```

### PROMPT 5.2: Courses V5 - Frontend
```
Crea el sistema COMPLETO de gestión de cursos - debe ser intuitivo para operaciones complejas.

REQUERIMIENTOS:
1. CoursesListPageComponent:
   - Vista table/cards con información esencial
   - Filtros: estado, deporte, tipo, monitor, fechas
   - Estadísticas rápidas por curso (reservas, revenue, ocupación)
   - Acciones rápidas: editar, clonar, archivar, ver reservas
   - Calendario miniatura con cursos del día

2. CourseFormPageComponent (Crear/Editar):
   - Wizard: Tipo → Detalles → Fechas → Grupos → Extras → Traduciones
   - Preview en tiempo real configuración grupos
   - Calendario visual selección fechas
   - Auto-configuración inteligente basada en plantillas
   - Validaciones complejas con feedback visual

3. CourseDetailPageComponent:
   - Tabs: Overview, Grupos, Participantes, Estadísticas, Configuración
   - Gestión grupos con drag-drop participantes
   - Gráficos estadísticas (ocupación, revenue, evaluaciones)
   - Timeline actividad curso
   - Panel acciones por tipo curso

4. Componentes especializados:
   - CourseCalendarComponent para selección fechas
   - GroupManagerComponent para gestión automática grupos
   - ParticipantAssignmentComponent con drag-drop
   - CourseStatsComponent con charts
   - TranslationManagerComponent con DeepL integration
   - EvaluationPanelComponent para reviews

FUNCIONALIDADES COMPLEJAS:
- Auto-suggestion configuración basada en cursos similares
- Preview impacto cambios en grupos/precios
- Bulk operations participantes
- Integration con planificador (ver curso en calendario)
- Export participantes/estadísticas

FLUJO CREAR CURSO:
1. Seleccionar tipo curso (templates disponibles)
2. Configurar detalles básicos (nombre, deporte, nivel)
3. Definir fechas/horarios (calendar picker)
4. Configurar grupos automáticos (preview capacidades)
5. Añadir extras opcionales
6. Generar traducciones automáticas
7. Review final y activación

INTEGRACIONES:
- Link directo crear reserva para este curso
- Integration con scheduler para ver planning
- Export data para reportes externos
- QR code generation para acceso mobile

INTERFAZ AVANZADA:
- Wizard progress indicator
- Collapsible sections información densa
- Color coding por tipo curso/estado
- Responsive layout complex forms
- Auto-save drafts importantes

ARCHIVOS A CREAR:
- src/app/features/courses/pages/ (list, form, detail)
- src/app/features/courses/components/ (especializados)
- src/app/features/courses/services/course.service.ts
- src/app/features/courses/models/course.interfaces.ts

Este módulo debe manejar elegantemente la complejidad de gestión académica siendo intuitivo para el usuario final.
```

---

## 🎁 FASE 6: BONOS Y CÓDIGOS (REDISEÑADO)

### PROMPT 6.1: Vouchers V5 - Backend
```
Rediseña completamente el sistema de bonos/vouchers para soportar múltiples tipos modernos.

CONTEXTO: Actual sistema solo tiene "bonos de compra" básicos. Necesitamos sistema moderno con gift cards, cupones descuento, códigos promocionales, etc.

REQUERIMIENTOS BACKEND:
1. VoucherController V5 con tipos múltiples:
   - GET /vouchers → listado todos los tipos con filtros
   - POST /vouchers/purchase-voucher → bonos compra tradicionales
   - POST /vouchers/gift-card → gift cards con códigos únicos  
   - POST /vouchers/discount-coupon → cupones descuento porcentual/fijo
   - POST /vouchers/promo-code → códigos promocionales campañas
   - GET /vouchers/{code}/validate → validar cualquier tipo código

2. Tipos de voucher soportados:
   - **Purchase Voucher**: Crédito específico cliente (traditional)
   - **Gift Card**: Transferible, código único, válido para cualquier cliente
   - **Discount Coupon**: % o cantidad fija descuento  
   - **Promo Code**: Campaña marketing, usos limitados, fecha caducidad
   - **Loyalty Reward**: Sistema puntos/recompensas (futuro)

3. Funcionalidades avanzadas:
   - Generación códigos únicos (UUID + custom format)
   - Múltiples restricciones: fechas, productos, clientes, usos
   - Stack/combine coupons rules
   - Usage tracking y analytics
   - Auto-expiration y notificaciones
   - Integration PayRexx para compra gift cards

4. Nuevos modelos:
```php
// VoucherType, Voucher, VoucherUsage, VoucherRestriction
// VoucherCampaign, VoucherAnalytics
```

VALIDACIONES:
- Códigos únicos en sistema
- Restricciones producto/fecha/cliente válidas
- Límites uso no excedidos
- Estados coherentes (active/expired/used)
- Prevent abuse/fraud patterns

APIs AUXILIARES:
- GET /vouchers/campaigns → campañas activas
- GET /vouchers/analytics → estadísticas uso
- POST /vouchers/bulk-create → creación masiva códigos
- PUT /vouchers/{id}/status → activar/desactivar

Implementa sistema flexible, extensible y anti-fraud que soporte growth marketing avanzado.
```

### PROMPT 6.2: Vouchers V5 - Frontend
```
Crea interfaz moderna para gestión completa vouchers/códigos/cupones.

REQUERIMIENTOS:
1. VouchersPageComponent principal:
   - Tabs por tipo: Purchase, Gift Cards, Discounts, Promo Codes
   - Dashboard mini con stats: active, used, revenue generated
   - Quick actions: create, bulk upload, campaign management
   - Advanced filters y search

2. Componentes especializados por tipo:
   - PurchaseVoucherComponent (clásicos)
   - GiftCardComponent (con generator códigos únicos)
   - DiscountCouponComponent (% vs fixed amount)
   - PromoCodeComponent (campañas marketing)

3. VoucherFormComponent universal:
   - Dynamic form basado en tipo seleccionado
   - Code generator con preview
   - Restrictions configurables (dates, products, clients)
   - Usage limits y settings
   - Bulk creation mode

4. Funcionalidades modernas:
   - QR code generation para códigos
   - Email templates para envío gift cards
   - Analytics dashboard con charts
   - Validation tool para test códigos
   - Integration shopping cart preview

INTERFAZ PROFESIONAL:
- Card-based layout con preview códigos
- Color coding por tipo y estado
- Progress bars para usage limits
- Copy-to-clipboard functionality
- Mobile-responsive complete

FLUJOS PRINCIPALES:
**Gift Card Creation**:
1. Seleccionar valor y diseño
2. Generar código único
3. Configurar restricciones
4. Preview email template
5. Send o generate PDF

**Promo Campaign**:
1. Definir campaña (nombre, fechas)
2. Configurar descuento
3. Set usage limits
4. Generate múltiples códigos
5. Export para marketing team

INTEGRACIONES:
- Link create voucher desde booking flow
- Integration con email marketing
- Analytics dashboard main
- Bulk import/export tools

ARCHIVOS A CREAR:
- src/app/features/vouchers/vouchers.page.ts
- src/app/features/vouchers/components/ (por tipo)
- src/app/features/vouchers/services/voucher.service.ts
- src/app/features/vouchers/models/voucher.interfaces.ts

Crea sistema moderno que soporte growth hacking y marketing campaigns avanzadas.
```

---

## 📧 FASE 7: COMUNICACIONES MEJORADAS

### PROMPT 7.1: Communications V5 - Backend
```
Mejora sistema comunicaciones para soporte emails masivos profesionales y segmentación avanzada.

REQUERIMIENTOS BACKEND:
1. CommunicationController V5:
   - GET /communications/templates → plantillas disponibles (WYSIWYG)
   - POST /communications/send-campaign → envío masivo segmentado
   - GET /communications/segments → segmentos disponibles
   - GET /communications/history → historial con métricas
   - GET /communications/analytics → métricas apertura/click

2. Segmentación avanzada:
   - Por curso/deporte/nivel
   - Por fecha reserva/actividad
   - Por gasto total cliente
   - Por monitor asignado
   - Custom queries builder

3. Plantillas WYSIWYG:
   - Editor rico con variables dinámicas
   - Preview personalizado per-recipient
   - Responsive email templates
   - Brand customization per-school
   - A/B testing support

4. Funcionalidades profesionales:
   - Queue system para envíos masivos
   - Bounce handling automático
   - Unsubscribe management
   - GDPR compliance tools
   - Integration external providers (SendGrid/Mailgun)

MODELOS:
- CommunicationTemplate, Campaign, CampaignRecipient
- EmailMetric, UnsubscribeRequest, BounceLog

MÉTRICAS TRACKING:
- Sent/Delivered/Bounced/Opened/Clicked
- Best time to send analysis
- Template performance comparison
- Segment response rates

APIs AUXILIARES:
- GET /communications/variables → variables disponibles templates
- POST /communications/test-send → envío test
- GET /communications/unsubscribes → gestión unsubscribes
- POST /communications/segments/custom → crear segmentos custom

Implementa sistema email marketing profesional con métricas detalladas.
```

### PROMPT 7.2: Communications V5 - Frontend
```
Crea interfaz completa para email marketing campaigns y comunicaciones masivas.

REQUERIMIENTOS:
1. CommunicationsPageComponent:
   - Dashboard campaigns con métricas principales
   - Lista campaigns activas/programadas/completadas
   - Quick create campaign button
   - Analytics overview con charts

2. CampaignBuilderComponent:
   - Step wizard: Audience → Template → Schedule → Review
   - Segment builder con drag-drop conditions
   - WYSIWYG template editor
   - Preview personalizado con sample recipients
   - A/B test configuration

3. TemplateManagerComponent:
   - Library templates con preview thumbnails
   - Template editor con variables dinámicas  
   - Responsive preview (desktop/mobile)
   - Brand assets integration
   - Save/clone/share templates

4. AnalyticsComponent:
   - Charts performance campaigns
   - Heatmaps click tracking
   - Best practices recommendations
   - Comparative analysis campaigns
   - Export reports functionality

FUNCIONALIDADES AVANZADAS:
- Real-time preview mientras editas
- Auto-save drafts campaigns
- Collaboration features (comments, approval)
- Scheduled sending con timezone handling
- Integration calendar para timing optimal

SEGMENTACIÓN VISUAL:
- Drag-drop query builder
- Preview audience size tiempo real
- Suggested segments based historial
- Custom SQL queries para power users
- Save segments para reutilización

INTERFAZ PROFESIONAL:
- Inspiración Mailchimp/Constant Contact
- Email preview accurate (real rendering)
- Progress indicators envíos masivos
- Mobile editing optimizado
- Dark mode compatible

FLUJO CREAR CAMPAIGN:
1. Define audience (segments/filters)
2. Choose/create template  
3. Customize content y variables
4. Preview y test send
5. Schedule o send immediate
6. Monitor performance real-time

ARCHIVOS A CREAR:
- src/app/features/communications/communications.page.ts
- src/app/features/communications/components/ (builder, editor, analytics)
- src/app/features/communications/services/communications.service.ts

Crea herramienta email marketing profesional que competir con external tools.
```

---

## 💬 FASE 8: CHAT SYSTEM (NUEVO)

### PROMPT 8.1: Chat V5 - Backend
```
Implementa sistema chat completo para comunicación interna escuela-monitores con WhatsApp integration.

REQUERIMIENTOS BACKEND:
1. ChatController V5:
   - GET /chat/conversations → lista conversaciones usuario
   - POST /chat/conversations → crear nueva conversación
   - GET /chat/{conversation}/messages → mensajes paginados
   - POST /chat/{conversation}/send → enviar mensaje
   - PUT /chat/{conversation}/read → marcar como leído

2. WhatsApp Business Integration:
   - Webhook endpoint para mensajes entrantes
   - Send message via WhatsApp API
   - Template messages automáticos
   - Media sharing (images, documents)
   - Status delivery tracking

3. WebSocket real-time:
   - Real-time message delivery
   - Online status tracking
   - Typing indicators
   - Message read receipts
   - Push notifications mobile

4. Funcionalidades avanzadas:
   - File attachments support
   - Message search full-text
   - Conversation archiving
   - Auto-responses patterns
   - Integration con booking notifications

MODELOS:
- Conversation, Message, MessageAttachment
- WhatsAppLog, NotificationQueue

NOTIFICACIONES AUTOMÁTICAS:
- Nueva reserva creada → notify monitor
- Cambio horario → notify affected parties  
- Pago recibido → confirm to client
- Curso starting soon → reminder participants

APIs AUXILIARES:
- GET /chat/online-users → usuarios conectados
- POST /chat/notifications/toggle → preferences
- GET /chat/templates → mensajes template
- POST /chat/broadcast → mensaje masivo

Implementa chat profesional con WhatsApp integration y notifications inteligentes.
```

### PROMPT 8.2: Chat V5 - Frontend
```
Crea interfaz chat moderna con real-time messaging y WhatsApp integration.

REQUERIMIENTOS:
1. ChatPageComponent:
   - Layout 3 columnas: Conversations, Messages, Info Panel
   - Conversation list con search y filters
   - Message thread con infinite scroll
   - Composing area con rich features

2. Real-time WebSocket:
   - Auto-connect/reconnect WebSocket
   - Real-time message updates
   - Typing indicators
   - Online status usuarios
   - Push notifications browser

3. Funcionalidades modernas:
   - File drag-drop attachment
   - Emoji picker
   - Message replies y threading  
   - Search within conversation
   - Message reactions básicas

4. WhatsApp Integration:
   - Visual indicator WhatsApp vs internal
   - Send via WhatsApp button
   - Template message selector
   - Media preview integration
   - Delivery status indicators

INTERFAZ PROFESIONAL:
- Inspiración WhatsApp Web/Slack
- Smooth animations message entry
- Mobile-responsive complete
- Dark mode optimizado chat
- Keyboard shortcuts power users

NOTIFICACIONES:
- Browser notifications nuevos mensajes
- Sound notifications configurables
- Desktop badge counts
- Integration sistema notificaciones general

COMPONENTES:
- ConversationListComponent
- MessageThreadComponent  
- MessageComposerComponent
- FileAttachmentComponent
- EmojiPickerComponent

FUNCIONALIDADES AVANZADAS:
- Auto-save message drafts
- Rich text formatting básico
- Integration con user profiles
- Quick replies templates
- Archive/mute conversations

ARCHIVOS A CREAR:
- src/app/features/chat/chat.page.ts
- src/app/features/chat/components/ (conversation, message, composer)
- src/app/features/chat/services/chat.service.ts
- src/app/features/chat/services/websocket.service.ts

Crea experiencia chat moderna comparable apps comerciales con business features integradas.
```

---

## 📊 FASE 9: ANALYTICS REDISEÑADAS

### PROMPT 9.1: Analytics V5 - Backend
```
Rediseña completamente sistema estadísticas con analytics profesionales y business intelligence.

REQUERIMIENTOS BACKEND:
1. AnalyticsController V5:
   - GET /analytics/dashboard → KPIs principales con comparativas
   - GET /analytics/revenue → análisis ingresos detallado
   - GET /analytics/monitors → performance y earnings monitores
   - GET /analytics/clients → behavior y lifetime value
   - GET /analytics/courses → performance cursos y optimización

2. Revenue Analytics:
   - Ingresos por día/semana/mes/año con trends
   - Breakdown por método pago (cash, card, online, vouchers)
   - Análisis estacionalidad y forecasting básico
   - ROI campaigns marketing
   - Average transaction value trends

3. Monitor Analytics:
   - Horas trabajadas y earnings per monitor
   - Utilization rates y availability patterns
   - Client satisfaction ratings
   - Performance metrics por deporte/nivel
   - Salary optimization suggestions

4. Business Intelligence:
   - Client retention rates y churn analysis
   - Course popularity y profitability
   - Peak hours analysis para scheduling
   - Seasonal trends y capacity planning
   - Competitive benchmarking internal

QUERIES COMPLEJAS:
- Cohort analysis clientes
- RFM segmentation automática
- Predictive analytics basic (trends)
- Cross-sell/up-sell opportunities
- Operational efficiency metrics

MODELOS ANALYTICS:
- AnalyticSnapshot, RevenueMetric, ClientMetric
- MonitorPerformance, CourseAnalytic

APIs ESPECIALIZADAS:
- GET /analytics/export → export data Excel/PDF
- GET /analytics/forecasting → predictions basic
- GET /analytics/benchmarks → industry comparisons
- GET /analytics/cohorts → cohort analysis

Cache inteligente para queries pesadas y optimización performance.
```

### PROMPT 9.2: Analytics V5 - Frontend  
```
Crea dashboard analytics profesional con visualizaciones potentes y insights accionables.

REQUERIMIENTOS:
1. AnalyticsPageComponent:
   - Dashboard principal con KPIs destacados
   - Tabs especializados: Revenue, Monitors, Clients, Courses
   - Date range selector global
   - Export/share functionality
   - Drill-down capability

2. Visualizaciones con Chart.js/D3:
   - Revenue line charts con múltiples metrics
   - Bar charts performance monitors
   - Pie charts distribution métodos pago
   - Heatmaps occupancy patterns
   - Funnel charts conversion rates

3. RevenueAnalyticsComponent:
   - Revenue trends con comparativas período anterior
   - Payment methods breakdown
   - Seasonal analysis con forecasting visual
   - Campaign ROI analysis
   - Top-performing courses revenue

4. MonitorAnalyticsComponent:
   - Individual monitor performance cards
   - Utilization vs earnings scatter plots
   - Client satisfaction ratings
   - Schedule optimization recommendations
   - Earnings projections

FUNCIONALIDADES AVANZADAS:
- Interactive charts con drill-down
- Real-time updates métricas críticas
- Custom date ranges y comparisons
- Bookmark favorite views
- Scheduled report emails

INSIGHTS INTELIGENTES:
- Auto-generated recommendations
- Anomaly detection visual alerts
- Performance vs targets tracking
- Trend predictions visuales
- Opportunity highlighting

INTERFAZ PROFESIONAL:
- Inspiración Google Analytics/Tableau
- Responsive charts y tables
- Print-friendly layouts
- Custom color schemes
- Loading skeletons sophisticados

COMPONENTES ESPECIALIZADOS:
- KPICardComponent con trends
- RevenueChartComponent interactive
- MonitorPerformanceGridComponent
- ClientRetentionComponent
- ExportManagerComponent

ARCHIVOS A CREAR:
- src/app/features/analytics/analytics.page.ts
- src/app/features/analytics/components/ (charts, kpis, exports)
- src/app/features/analytics/services/analytics.service.ts
- src/app/features/analytics/models/analytics.interfaces.ts

Crea herramienta BI que proporcione insights accionables para decision-making estratégico.
```

---

## 🛷 FASE 10: RENTING MODULE (NUEVO)

### PROMPT 10.1: Renting V5 - Backend
```
Implementa módulo completamente nuevo de alquiler material deportivo integrado con reservas.

REQUERIMIENTOS BACKEND:
1. RentingController V5:
   - GET /renting/items → catálogo material con availability
   - GET /renting/categories → categorías y subcategorías
   - POST /renting/book → reservar material (standalone o con curso)
   - GET /renting/availability → check disponibilidad fechas específicas
   - GET /renting/bookings → gestionar reservas material

2. Inventory Management:
   - Stock tracking tiempo real
   - Size/model variants per item
   - Condition tracking (new/good/worn/repair)
   - Maintenance schedules y history
   - Seasonal availability rules

3. Pricing Engine:
   - Flexible pricing: hourly/daily/weekly rates
   - Seasonal pricing adjustments
   - Bundle discounts (ski+boots+helmet)
   - Insurance options per item
   - Damage deposit management

4. Integration con Bookings:
   - Add material to existing course bookings
   - Automatic suggestions based course type
   - Combined checkout experience
   - Unified cancellation policies
   - Package deals course+material

MODELOS NUEVOS:
```php
RentalItem, RentalCategory, RentalBooking, RentalInventory
RentalPrice, RentalBundle, RentalDamageReport
```

FUNCIONALIDADES AVANZADAS:
- QR codes para inventory tracking
- Damage assessment workflow
- Return inspection checklists
- Lost item procedures
- Analytics rental performance

VALIDACIONES:
- Availability conflicts
- Size/age appropriateness checks
- Insurance requirements validation
- Return date compliance
- Damage deposit calculations

APIs AUXILIARES:
- GET /renting/bundles → package deals
- POST /renting/{id}/return → process return
- PUT /renting/{id}/damage → report damage
- GET /renting/analytics → rental metrics

Implementa sistema rental profesional con inventory management robusto.
```

### PROMPT 10.2: Renting V5 - Frontend
```
Crea interfaz completa para gestión material alquiler con UX moderna e integration booking flow.

REQUERIMIENTOS:
1. RentingPageComponent:
   - Catalog view con filtros (categoria, tamaño, disponibilidad)
   - Availability calendar per item
   - Quick book functionality
   - Integration search con booking flow
   - Inventory management admin view

2. RentalCatalogComponent:
   - Grid/list view material
   - High-quality images con zoom
   - Specifications y size charts
   - Availability indicators real-time
   - Price calculator interactive

3. BookingIntegrationComponent:
   - "Add Equipment" button en booking flow
   - Smart suggestions based course type
   - Bundle configurator visual
   - Combined pricing preview
   - Unified checkout experience

4. InventoryManagementComponent (Admin):
   - Stock levels con alerts
   - Condition tracking workflow
   - Maintenance scheduling
   - Damage reporting system
   - Analytics dashboard rental

FUNCIONALIDADES UX:
- Visual size selector con measurements
- Availability heatmap calendar
- Wishlist/favorites functionality
- Recently viewed items
- Mobile-optimized catalog

INTEGRATION BOOKINGS:
- Seamless transition booking→equipment
- Package pricing calculations
- Combined confirmation emails
- Unified cancellation flow
- Cross-sell recommendations

ADMIN FEATURES:
- Bulk inventory updates
- QR code generation/scanning
- Return processing workflow
- Damage assessment forms
- Performance analytics

COMPONENTES ESPECIALIZADOS:
- ItemCatalogComponent con filters
- AvailabilityCalendarComponent
- SizeSelectionComponent
- BundleBuilderComponent
- ReturnProcessComponent

INTERFAZ MODERNA:
- E-commerce best practices
- High-performance image loading
- Smooth animations transitions
- Mobile-first responsive
- Accessibility compliant

ARCHIVOS A CREAR:
- src/app/features/renting/renting.page.ts
- src/app/features/renting/components/ (catalog, booking, inventory)
- src/app/features/renting/services/renting.service.ts
- src/app/features/renting/models/rental.interfaces.ts

Crea experiencia rental moderna que se integre perfectamente con booking flow principal.
```

---

## ⚙️ FASE 11: SETTINGS & CONFIGURATION

### PROMPT 11.1: Settings V5 - Backend
```
Consolida y mejora sistema configuraciones con interfaz unificada y management avanzado.

REQUERIMIENTOS BACKEND:
1. SettingsController V5 unificado:
   - GET /settings/school → configuración general escuela
   - GET /settings/sports → deportes, niveles, pricing
   - GET /settings/seasons → gestión temporadas
   - GET /settings/booking-page → configuración página reservas
   - GET /settings/email-templates → templates automáticos
   - GET /settings/payment → PayRexx y métodos pago
   - GET /settings/modules → módulos contratados y config

2. Sports & Levels Management:
   - CRUD deportes con niveles personalizables
   - Pricing matrices flexible courses
   - Monitor qualifications per sport/level
   - Seasonal adjustments automáticos
   - Integration booking validation

3. Email Templates System:
   - WYSIWYG editor para cada template
   - Variables dinámicas contexto-aware  
   - Multi-language support
   - A/B testing templates
   - Trigger conditions customizable

4. Booking Page Customization:
   - Theme selector (light/dark/custom)
   - Banner management con upload
   - Sponsor logos y links
   - Custom CSS injection
   - Preview functionality live

CONFIGURACIONES AVANZADAS:
- Multi-currency support
- Tax configuration per región
- Cancellation policies flexible
- Insurance options management
- GDPR compliance settings

APIs ESPECIALIZADAS:
- POST /settings/import → import configuration
- GET /settings/export → export settings backup
- PUT /settings/bulk-update → update múltiples configs
- GET /settings/audit → changelog settings

Implementa sistema configuración robusto y user-friendly para administración completa.
```

### PROMPT 11.2: Settings V5 - Frontend
```
Crea interfaz unificada settings con UX intuitiva para configuraciones complejas.

REQUERIMIENTOS:
1. SettingsPageComponent con navigation:
   - Sidebar navigation categorizadas
   - Breadcrumb navigation
   - Auto-save indicators
   - Changes preview functionality
   - Bulk import/export tools

2. Categorías Settings:
   - **General**: School info, contact, branding
   - **Sports**: Deportes, niveles, pricing matrices  
   - **Seasons**: Gestión temporadas y fechas
   - **Booking Page**: Customization página reservas
   - **Communications**: Email templates y config
   - **Payments**: PayRexx, currencies, taxes
   - **Modules**: Activar/configurar módulos contratados

3. Componentes especializados:
   - SportsLevelsManagerComponent
   - PricingMatrixComponent
   - EmailTemplateEditorComponent
   - BookingPagePreviewComponent
   - SeasonManagerComponent
   - ModuleConfigComponent

4. UX Avanzada:
   - Live preview changes
   - Undo/redo functionality
   - Validation real-time
   - Conditional fields logic
   - Keyboard shortcuts

FUNCIONALIDADES CRÍTICAS:
- Auto-save con conflict detection
- Bulk operations settings
- Settings versioning/rollback
- Export/import configuration
- Audit trail changes

BOOKING PAGE CUSTOMIZATION:
- Visual theme editor
- Drag-drop banner placement
- Real-time preview iframe
- Mobile/desktop preview modes
- Custom CSS editor con validation

EMAIL TEMPLATE EDITOR:
- WYSIWYG editor rico
- Variable insertion helper
- Preview con sample data
- Multi-language tabs
- Template gallery

ARCHIVOS A CREAR:
- src/app/features/settings/settings.page.ts
- src/app/features/settings/components/ (por categoría)
- src/app/features/settings/services/settings.service.ts
- src/app/shared/components/wysiwyg-editor.component.ts

Crea interfaz settings profesional que haga management complejo accesible y intuitivo.
```

---

## 🔧 FASE 12: POLISH & INTEGRATION

### PROMPT 12.1: System Integration & Polish
```
Integra todos los módulos, añade funcionalidades cross-cutting y polish final.

REQUERIMIENTOS INTEGRACIÓN:
1. Navigation & Layout:
   - Sidebar navigation con módulos contratados
   - Breadcrumb system inteligente
   - Global search cross-modules
   - Quick actions context-aware
   - Mobile responsive complete

2. Notification System:
   - Toast notifications consistentes
   - Push notifications browser
   - Email notifications automáticas
   - Mobile app notifications (future)
   - Notification center unified

3. Cross-module Features:
   - Global search (bookings, clients, courses)
   - Recent activity timeline
   - Quick create shortcuts
   - Favorites/bookmarks system
   - Context-sensitive help

4. Performance Optimizations:
   - Lazy loading modules
   - Image optimization
   - Caching strategies
   - Bundle optimization
   - Progressive loading

FUNCIONALIDADES FALTANTES:
- Bulk operations cross-modules
- Advanced export/import
- System health monitoring  
- User activity tracking
- Error boundary handling

TESTING PRIORITIES:
- Auth flow complete
- Context switching
- Permission enforcement
- Module access control
- Data consistency

Implementa integración completa con polish profesional y testing exhaustivo.
```

---

## 🚀 PLAN DE EJECUCIÓN

### Orden de Ejecución Recomendado:
1. **FASE 1**: Auth System (crítico - base todo)
2. **FASE 2**: Dashboard mejorado (visibilidad inmediata)  
3. **FASE 3**: Planificador (funcionalidad core)
4. **FASE 4**: Reservas (joya corona #1)
5. **FASE 5**: Cursos (joya corona #2)
6. **FASE 6-11**: Módulos restantes paralelo
7. **FASE 12**: Integration & Polish

### Testing por Fase:
- ✅ Compilación TypeScript/PHP
- ✅ Linting clean
- ✅ Funcionalidad básica
- ✅ Integración backend-frontend  
- ✅ UI responsive y profesional

### Criterios Aprobación:
- Zero compilation errors
- Clean linting (ESLint/PHP CS)
- Funcionalidad demonstrable
- UI profesional y responsive
- Backend-frontend cohesion

---

**¿READY TO START?** 🚀

Confirma si este plan cumple tus expectativas o necesitas ajustes específicos antes de comenzar con **PROMPT 1.1: Auth System V5 - Backend**.
