# Guía de Prompts para Renovación Visual - Boukii V5

> **Prompts específicos para rediseñar todas las pantallas con el nuevo sistema de diseño profesional**

---

## 📋 Índice de Prompts

1. [Dashboard Principal](#dashboard-principal)
2. [Gestión de Cursos](#gestión-de-cursos)
3. [Reservas y Bookings](#reservas-y-bookings)
4. [Monitores](#monitores)
5. [Temporadas](#temporadas)
6. [Escuelas](#escuelas)
7. [Estadísticas](#estadísticas)
8. [Comunicaciones](#comunicaciones)
9. [Chat](#chat)
10. [Configuración](#configuración)
11. [Usuarios y Roles](#usuarios-y-roles)
12. [Vouchers](#vouchers)

---

## 🏠 Dashboard Principal

### **Prompt para Dashboard**
```
Por favor, rediseña el dashboard principal de Boukii siguiendo el sistema de diseño profesional establecido. 

CONTEXTO:
- Es el dashboard principal de administración de una escuela de esquí/snow
- Debe mostrar métricas clave, actividad reciente y accesos rápidos
- Los usuarios son administradores y personal de la escuela

ELEMENTOS A INCLUIR:
1. **Header profesional** con título "Panel de Control", icono de dashboard y subtitle descriptivo
2. **Métricas principales** en tarjetas con iconos:
   - Reservas del día
   - Clientes activos
   - Cursos programados
   - Ingresos del período
3. **Gráficos visuales** (mockear datos si es necesario):
   - Gráfico de reservas por semana
   - Distribución por tipo de curso
4. **Actividad reciente** con lista de eventos importantes
5. **Accesos rápidos** a funciones principales
6. **Widget de tiempo/condiciones** (opcional)

ESTILO A SEGUIR:
- Usar la paleta de colores azul corporativo (#3b82f6, #2563eb)
- Header con gradiente de fondo sutil
- Tarjetas con sombras y hover effects
- Iconos Heroicons consistentes
- Responsive design para móviles
- Gradientes para elementos principales

ESTRUCTURA TÉCNICA:
- Archivo: dashboard.page.ts / dashboard.page.html / dashboard.page.scss  
- Usar standalone components
- Incluir interfaces TypeScript para datos
- Implementar trackBy para listas
- Añadir loading states y empty states

Crea un dashboard moderno y profesional que dé una impresión seria y confiable.
```

---

## 📚 Gestión de Cursos

### **Prompt para Lista de Cursos**
```
Rediseña la pantalla de gestión de cursos con el sistema de diseño profesional de Boukii.

CONTEXTO:
- Lista de cursos de esquí/snowboard ofrecidos por la escuela
- Administradores pueden crear, editar, ver detalles y gestionar cursos
- Cada curso tiene nivel, tipo, precio, duración, monitor asignado

ELEMENTOS A INCLUIR:
1. **Header profesional**:
   - Título: "Gestión de Cursos" con icono de libro/educación
   - Subtitle: "Administra los cursos y programas de formación"
   - Botón primario: "Nuevo Curso"
   - Botón secundario: "Exportar Lista"

2. **Sistema de búsqueda y filtros**:
   - Búsqueda por nombre de curso
   - Filtros: Tipo (Esquí/Snow), Nivel (Principiante/Intermedio/Avanzado), Estado
   - Toggle vista tarjetas/tabla

3. **Vista de tarjetas** (cada curso):
   - Imagen/icono del tipo de curso
   - Nombre del curso prominente
   - Nivel con badge colorido
   - Precio por persona
   - Duración en horas/días
   - Monitor asignado
   - Estado (Activo/Inactivo)
   - Botones: "Ver Detalles", "Editar", "Reservas"

4. **Vista de tabla** con columnas:
   - Curso (nombre + tipo)
   - Nivel
   - Precio
   - Monitor
   - Capacidad
   - Reservas actuales
   - Estado
   - Acciones

5. **Estados especiales**:
   - Empty state cuando no hay cursos
   - Loading skeleton
   - Error states

COLORES ESPECÍFICOS:
- Azul corporativo para acciones principales
- Verde para cursos activos
- Naranja para cursos en pausa
- Badges de nivel con colores diferenciados

Sigue exactamente el patrón establecido en el sistema de diseño, como los módulos de renting y clientes ya implementados.
```

### **Prompt para Formulario de Curso**
```
Crea un formulario profesional para crear/editar cursos siguiendo el sistema de diseño de Boukii.

ELEMENTOS DEL FORMULARIO:
1. **Modal overlay** con animación de entrada
2. **Header del modal**:
   - Título: "Nuevo Curso" / "Editar Curso"
   - Icono de educación
   - Botón de cerrar
3. **Secciones del formulario**:
   - Información básica (nombre, descripción, tipo)
   - Configuración (nivel, duración, precio, capacidad)
   - Monitor asignado (selector)
   - Programación (fechas, horarios)
   - Notas adicionales
4. **Validaciones** en tiempo real
5. **Botones**: Cancelar (secundario), Guardar (primario)

CAMPOS ESPECÍFICOS:
- Nombre del curso (requerido)
- Tipo: Esquí/Snowboard/Ambos
- Nivel: Principiante/Intermedio/Avanzado/Experto
- Duración en horas
- Precio por persona
- Capacidad máxima
- Monitor asignado (dropdown)
- Fecha inicio/fin
- Horarios
- Descripción
- Requisitos previos
- Material incluido
- Estado activo/inactivo

Usa los mismos patrones de formulario que el módulo de equipamiento ya implementado.
```

---

## 📅 Reservas y Bookings

### **Prompt para Gestión de Reservas**
```
Rediseña el sistema de gestión de reservas con el diseño profesional de Boukii.

CONTEXTO:
- Centro de control para todas las reservas de cursos y equipamiento
- Personal necesita ver, confirmar, modificar y cancelar reservas
- Vista unificada de calendario y lista de reservas

ELEMENTOS PRINCIPALES:
1. **Header profesional**:
   - Título: "Gestión de Reservas"
   - Subtitle: "Centro de control de todas las reservas"
   - Acciones: "Nueva Reserva", "Vista Calendario", "Exportar"

2. **Panel de estadísticas rápidas**:
   - Reservas de hoy
   - Pendientes de confirmación
   - Cancelaciones
   - Ingresos del día

3. **Sistema de filtros avanzado**:
   - Rango de fechas
   - Estado (Confirmada/Pendiente/Cancelada)
   - Tipo (Curso/Equipamiento)
   - Cliente
   - Monitor

4. **Doble vista**:
   
   **Vista Lista** (tarjetas de reserva):
   - Avatar/iniciales del cliente
   - Nombre del cliente
   - Curso/equipamiento reservado
   - Fecha y hora
   - Estado con badge colorido
   - Precio total
   - Botones de acción: Ver, Editar, Confirmar, Cancelar
   
   **Vista Calendario** (integrada):
   - Calendario mensual/semanal
   - Reservas como eventos coloridos
   - Click para ver detalles
   - Drag & drop para reprogramar

5. **Estados de reserva** con colores:
   - Confirmada: Verde
   - Pendiente: Naranja
   - Cancelada: Rojo
   - Completada: Azul

FUNCIONALIDADES ESPECIALES:
- Modal de detalles de reserva completo
- Formulario de nueva reserva paso a paso
- Sistema de confirmación por email
- Notas internas
- Historial de cambios

Implementa el mismo nivel de profesionalidad que los módulos ya terminados.
```

---

## 🎿 Monitores

### **Prompt para Gestión de Monitores**
```
Rediseña la gestión de monitores siguiendo el sistema de diseño profesional establecido.

CONTEXTO:
- Base de datos de monitores/instructores de la escuela
- Gestión de horarios, especialidades, certificaciones y rendimiento

HEADER:
- Título: "Gestión de Monitores"
- Subtitle: "Administra el equipo de instructores profesionales"
- Acciones: "Nuevo Monitor", "Importar Monitores", "Exportar Lista"

TARJETAS DE MONITOR:
1. **Avatar profesional** con iniciales o foto
2. **Información principal**:
   - Nombre completo
   - Especialidades (Esquí/Snow/Ambos) con badges
   - Nivel de certificación
   - Años de experiencia
3. **Estadísticas**:
   - Cursos asignados este mes
   - Valoración media (estrellas)
   - Horas trabajadas
4. **Estado**:
   - Disponible/No disponible
   - Activo/Inactivo
5. **Acciones**:
   - Ver perfil completo
   - Asignar curso
   - Ver horarios
   - Editar información

VISTA TABLA:
- Monitor (foto + nombre + especialidades)
- Certificaciones
- Experiencia
- Cursos activos
- Disponibilidad
- Valoración
- Estado
- Acciones

FILTROS:
- Búsqueda por nombre
- Especialidad
- Nivel de certificación
- Estado de disponibilidad
- Ordenación por valoración, experiencia, etc.

COLORES ESPECÍFICOS:
- Verde para monitores disponibles
- Naranja para ocupados
- Rojo para no disponibles
- Azul para certificaciones altas

Incluye modal de perfil completo del monitor con pestañas para: Información personal, Certificaciones, Horarios, Historial de cursos, Valoraciones de clientes.
```

---

## 🏔️ Temporadas

### **Prompt para Gestión de Temporadas**
```
Crea una interfaz profesional para gestionar las temporadas de la escuela de esquí.

CONTEXTO:
- Las temporadas definen períodos de actividad (Invierno, Verano)
- Cada temporada tiene fechas, precios, cursos disponibles y configuraciones específicas

ESTRUCTURA:
1. **Header**:
   - Título: "Gestión de Temporadas"
   - Subtitle: "Configuración de períodos y tarifas estacionales"
   - Botón: "Nueva Temporada"

2. **Timeline visual** de temporadas:
   - Línea temporal horizontal
   - Temporadas como bloques coloridos
   - Temporada actual destacada
   - Fechas de inicio/fin visibles

3. **Tarjetas de temporada**:
   - Nombre de la temporada (ej: "Invierno 2024-2025")
   - Fechas de inicio y fin
   - Estado: Activa/Futura/Finalizada
   - Estadísticas:
     - Cursos ofrecidos
     - Reservas totales
     - Ingresos generados
   - Imagen/icono representativo
   - Botones: "Configurar", "Ver Estadísticas", "Duplicar"

4. **Formulario de temporada**:
   - Información básica (nombre, fechas)
   - Configuración de precios
   - Cursos disponibles
   - Configuraciones especiales
   - Descuentos y promociones

ESTADOS VISUALES:
- Temporada actual: Verde con gradiente
- Temporada futura: Azul
- Temporada finalizada: Gris
- Temporada en preparación: Naranja

FUNCIONALIDADES:
- Comparar temporadas
- Copiar configuración entre temporadas
- Activar/desactivar temporadas
- Configuración de precios estacionales
- Vista de calendario integrada
```

---

## 🏫 Escuelas

### **Prompt para Gestión de Escuelas**
```
Diseña la interfaz de gestión de escuelas para administradores multi-escuela.

CONTEXTO:
- Algunos usuarios administran múltiples escuelas/centros
- Necesitan vista consolidada y gestión individual de cada escuela

ESTRUCTURA:
1. **Header**:
   - Título: "Red de Escuelas"
   - Subtitle: "Gestión centralizada de múltiples centros"
   - Acciones: "Nueva Escuela", "Vista Consolidada"

2. **Mapa interactivo** (opcional):
   - Ubicación de cada escuela
   - Marcadores con información básica
   - Click para acceder a gestión individual

3. **Grid de escuelas**:
   - **Tarjeta por escuela**:
     - Logo/imagen de la escuela
     - Nombre y ubicación
     - Estado operacional
     - Estadísticas principales:
       - Monitores activos
       - Cursos disponibles
       - Reservas del mes
       - Ingresos mensuales
     - Indicadores de rendimiento
     - Botón "Administrar Escuela"

4. **Vista consolidada**:
   - Estadísticas combinadas
   - Comparativas entre escuelas
   - Ranking de rendimiento
   - Alertas y notificaciones

FORMULARIO DE ESCUELA:
- Información básica (nombre, dirección, contacto)
- Configuración operacional
- Personal asignado
- Equipamiento disponible
- Tarifas y precios
- Certificaciones y licencias

DASHBOARD INDIVIDUAL:
Al hacer click en "Administrar Escuela", mostrar dashboard específico con toda la gestión local (cursos, monitores, reservas, etc.)
```

---

## 📊 Estadísticas

### **Prompt para Dashboard de Estadísticas**
```
Crea un dashboard avanzado de estadísticas y analytics para Boukii.

CONTEXTO:
- Dashboard ejecutivo con métricas clave del negocio
- Gráficos interactivos y reportes detallados
- Comparativas temporales y análisis de tendencias

ESTRUCTURA:
1. **Header ejecutivo**:
   - Título: "Analytics & Estadísticas"
   - Subtitle: "Insights del rendimiento del negocio"
   - Selector de período
   - Botón "Generar Reporte"

2. **KPIs principales** (tarjetas destacadas):
   - Ingresos totales (con % crecimiento)
   - Número de reservas (con comparativa)
   - Tasa de ocupación
   - Satisfacción promedio
   - Nuevos clientes
   - Retención de clientes

3. **Gráficos principales**:
   - **Ingresos por tiempo**: Línea temporal
   - **Reservas por tipo de curso**: Gráfico circular
   - **Ocupación por monitor**: Barras horizontales
   - **Satisfacción por mes**: Gráfico de área
   - **Tendencias estacionales**: Gráfico combinado

4. **Tablas de rendimiento**:
   - Top monitores (por valoración/reservas)
   - Cursos más populares
   - Clientes más activos
   - Análisis de precios vs demanda

5. **Filtros avanzados**:
   - Rango de fechas personalizado
   - Por escuela (si aplica)
   - Por tipo de curso
   - Por monitor
   - Por nivel de curso

VISUALIZACIÓN:
- Usar bibliotecas como Chart.js o D3.js (mockear si es necesario)
- Colores consistentes con el sistema de diseño
- Tooltips informativos
- Exportación a PDF/Excel
- Vista responsive para móviles

MÉTRICAS ESPECÍFICAS:
- Conversión de consultas a reservas
- Valor promedio por cliente
- Tiempo promedio de anticipación de reservas
- Cancelaciones por período
- Rentabilidad por curso
```

---

## 💬 Comunicaciones

### **Prompt para Centro de Comunicaciones**
```
Rediseña el módulo de comunicaciones para gestión de mensajes y notificaciones.

CONTEXTO:
- Centro de comunicaciones internas y con clientes
- Envío de emails masivos, SMS, y notificaciones push
- Templates de mensajes y programación de envíos

ESTRUCTURA:
1. **Header**:
   - Título: "Centro de Comunicaciones"
   - Subtitle: "Gestiona mensajes y notificaciones"
   - Acciones: "Nuevo Mensaje", "Plantillas", "Programar Envío"

2. **Panel de estadísticas**:
   - Mensajes enviados hoy
   - Tasa de apertura
   - Clientes contactados
   - Mensajes programados

3. **Tabs principales**:
   
   **Bandeja de entrada**:
   - Lista de mensajes recibidos
   - Estados: Leído/No leído
   - Filtros por tipo y fecha
   - Respuesta rápida

   **Mensajes enviados**:
   - Historial de envíos
   - Estadísticas de entrega
   - Estados de lectura
   - Reenviar/editar

   **Plantillas**:
   - Templates prediseñados
   - Categorías: Confirmación, Recordatorio, Promocional, Informativo
   - Editor de plantillas
   - Vista previa

   **Programados**:
   - Mensajes en cola
   - Calendario de envíos
   - Editar/cancelar envíos

4. **Composer de mensajes**:
   - Selector de destinatarios (individual/grupal)
   - Tipo de mensaje (Email/SMS/Push)
   - Editor WYSIWYG para emails
   - Personalización con variables
   - Programación de envío
   - Vista previa multi-dispositivo

TIPOS DE MENSAJES:
- Confirmación de reserva
- Recordatorios de curso
- Cancelaciones y cambios
- Promociones estacionales
- Avisos meteorológicos
- Encuestas de satisfacción

FUNCIONALIDADES AVANZADAS:
- Segmentación de clientes
- A/B testing de mensajes
- Automatización basada en eventos
- Integración con calendario
- Métricas de engagement
```

---

## 💬 Chat

### **Prompt para Sistema de Chat**
```
Implementa un sistema de chat interno profesional para el equipo de Boukii.

CONTEXTO:
- Chat interno para comunicación entre personal
- Canales por departamentos y chats directos
- Integración con notificaciones y mentions

LAYOUT:
1. **Sidebar izquierdo**:
   - Lista de canales (#general, #monitores, #reservas)
   - Mensajes directos
   - Estado de usuarios online
   - Botón "Nuevo chat"

2. **Área principal de chat**:
   - Header con nombre del canal/usuario
   - Miembros del canal
   - Área de mensajes con scroll
   - Input de mensaje con toolbar

3. **Panel derecho** (opcional):
   - Información del canal
   - Archivos compartidos
   - Miembros
   - Configuración

CARACTERÍSTICAS:
- **Mensajes en tiempo real**
- **Tipos de mensaje**:
  - Texto plano
  - Emojis y reacciones
  - Archivos adjuntos
  - Enlaces con preview
  - Mentions (@usuario)
  - Hilos de conversación

- **Estados de usuario**:
  - Online (verde)
  - Ausente (naranja)
  - Ocupado (rojo)
  - Offline (gris)

- **Notificaciones**:
  - Badge de mensajes no leídos
  - Sonido de notificación
  - Desktop notifications

DISEÑO:
- Burbujas de mensaje diferenciadas por usuario
- Timestamps discretos
- Indicadores de mensaje leído
- Búsqueda de mensajes
- Tema consistente con el sistema de diseño

FUNCIONALIDADES:
- Crear canales públicos/privados
- Invitar usuarios
- Compartir pantalla (link externo)
- Programar mensajes
- Modo no molestar
- Historial de mensajes
```

---

## ⚙️ Configuración

### **Prompt para Panel de Configuración**
```
Crea un panel de configuración completo y organizado para la administración del sistema.

CONTEXTURA:
- Configuraciones generales del sistema
- Personalizaciones por escuela
- Integraciones y APIs
- Configuración de usuarios y permisos

ESTRUCTURA:
1. **Header**:
   - Título: "Configuración del Sistema"
   - Subtitle: "Personaliza y configura tu plataforma"

2. **Navegación lateral** (tabs verticales):
   - General
   - Escuela
   - Usuarios y Permisos
   - Notificaciones
   - Integraciones
   - Facturación
   - Seguridad
   - Respaldo

3. **Contenido por sección**:

   **General**:
   - Información de la empresa
   - Zona horaria y idioma
   - Formato de fecha y moneda
   - Logo y branding
   - Configuración de correo

   **Escuela**:
   - Horarios de operación
   - Tipos de cursos disponibles
   - Políticas de cancelación
   - Precios base
   - Equipamiento disponible

   **Usuarios y Permisos**:
   - Roles del sistema
   - Permisos por módulo
   - Configuración de registro
   - Políticas de contraseña

   **Notificaciones**:
   - Templates de email
   - Configuración de SMS
   - Notificaciones push
   - Automatizaciones

   **Integraciones**:
   - APIs externas
   - Calendario (Google/Outlook)
   - Métodos de pago
   - Analytics

   **Seguridad**:
   - Configuración de sesiones
   - Logs de acceso
   - Whitelist de IPs
   - Autenticación 2FA

DISEÑO:
- Cards organizadas por categoría
- Toggle switches para opciones binarias
- Inputs con validación en tiempo real
- Mensajes de confirmación
- Estados de guardado
- Breadcrumbs para navegación

FUNCIONALIDADES:
- Guardado automático
- Importar/exportar configuraciones
- Resetear a valores por defecto
- Historial de cambios
- Confirmación para cambios críticos
```

---

## 👥 Usuarios y Roles

### **Prompt para Gestión de Usuarios**
```
Diseña la interfaz completa de gestión de usuarios y control de acceso.

CONTEXTO:
- Administración de cuentas de usuario del sistema
- Asignación de roles y permisos
- Control de acceso granular por módulos

ESTRUCTURA:
1. **Header**:
   - Título: "Gestión de Usuarios"
   - Subtitle: "Administra cuentas y permisos de acceso"
   - Acciones: "Invitar Usuario", "Roles y Permisos", "Exportar Lista"

2. **Panel de estadísticas**:
   - Usuarios activos
   - Nuevas invitaciones
   - Últimos accesos
   - Distribución por roles

3. **Lista de usuarios** (vista tarjetas):
   - Avatar con iniciales
   - Nombre completo y email
   - Rol actual con badge
   - Estado (Activo/Inactivo/Pendiente)
   - Último acceso
   - Escuelas asignadas (si multi-escuela)
   - Menú de acciones: Ver perfil, Editar, Cambiar rol, Desactivar

4. **Sistema de roles**:
   - **Administrador Global**: Acceso completo
   - **Administrador de Escuela**: Gestión local
   - **Recepcionista**: Reservas y clientes
   - **Monitor**: Vista de sus cursos
   - **Contabilidad**: Reportes financieros

5. **Modal de invitación**:
   - Email del invitado
   - Rol a asignar
   - Escuelas (si aplica)
   - Mensaje personalizado
   - Fecha de expiración de invitación

6. **Editor de permisos**:
   - Lista de módulos del sistema
   - Matriz de permisos por rol
   - Checkboxes para: Ver, Crear, Editar, Eliminar
   - Permisos especiales
   - Vista previa de acceso

FUNCIONALIDADES ESPECIALES:
- Invitaciones por email
- Activación de cuenta
- Reset de contraseña
- Logs de actividad por usuario
- Bloqueo temporal por seguridad
- Configuración de sesiones múltiples

SEGURIDAD:
- Validación de emails
- Políticas de contraseña
- Confirmación para acciones críticas
- Audit log de cambios
- Notificaciones de cambios de permisos
```

---

## 🎫 Vouchers

### **Prompt para Sistema de Vouchers**
```
Crea un sistema completo de gestión de vouchers y códigos promocionales.

CONTEXTO:
- Vales de regalo y códigos de descuento
- Promociones estacionales y ofertas especiales
- Sistema de canje y validación

ESTRUCTURA:
1. **Header**:
   - Título: "Gestión de Vouchers"
   - Subtitle: "Códigos promocionales y vales de regalo"
   - Acciones: "Crear Voucher", "Promoción Masiva", "Estadísticas"

2. **Métricas principales**:
   - Vouchers activos
   - Canjeados este mes
   - Valor total pendiente
   - Próximos a expirar

3. **Tipos de vouchers** (tabs):
   
   **Vales de Regalo**:
   - Valor monetario fijo
   - Código único
   - Fecha de emisión/expiración
   - Estado: Activo/Canjeado/Expirado

   **Códigos Promocionales**:
   - Porcentaje o cantidad de descuento
   - Límite de usos
   - Condiciones específicas
   - Aplicable a cursos/equipos

   **Ofertas Estacionales**:
   - Promociones temporales
   - Descuentos por temporada alta/baja
   - Combos y paquetes especiales

4. **Lista de vouchers**:
   - Código del voucher
   - Tipo con icono distintivo
   - Valor/descuento
   - Condiciones de uso
   - Fechas válidas
   - Usos: Actuales/Máximos
   - Cliente asignado (si aplica)
   - Estado con badge colorido
   - Acciones: Ver, Editar, Desactivar, Duplicar

5. **Formulario de creación**:
   - Tipo de voucher
   - Código (auto/manual)
   - Valor o porcentaje
   - Condiciones de uso
   - Límite de usos
   - Fechas de vigencia
   - Aplicable a: Todos/Cursos específicos/Equipos
   - Cliente específico (opcional)
   - Notas internas

FUNCIONALIDADES:
- Generación masiva de códigos
- Validador de códigos en tiempo real
- Historial de canjes
- Envío automático por email
- Integración con sistema de reservas
- Reportes de efectividad

ESTADOS VISUALES:
- Activo: Verde
- Próximo a expirar: Naranja
- Expirado: Rojo
- Canjeado: Azul
- Pausado: Gris

VALIDACIONES:
- Códigos únicos
- Fechas coherentes
- Límites válidos
- Condiciones específicas
```

---

## 🎯 Prompts de Seguimiento

### **Para cada módulo, después del primer diseño:**

#### **Prompt de Refinamiento**
```
El diseño inicial está bien, pero necesita estos ajustes específicos:

1. **Mejorar accesibilidad**: Añadir aria-labels, mejorar contraste, navegación por teclado
2. **Loading states**: Implementar skeletons y estados de carga para cada sección
3. **Error boundaries**: Manejar errores de API y estados offline
4. **Micro-interacciones**: Añadir feedback visual para acciones del usuario
5. **Performance**: Implementar lazy loading y optimización de renders

¿Puedes implementar estas mejoras manteniendo el diseño actual?
```

#### **Prompt de Responsive**
```
Por favor, optimiza este módulo para dispositivos móviles:

1. **Breakpoints**: Ajustar diseño para tablets (768px) y móviles (480px)
2. **Navegación móvil**: Simplificar header y filtros
3. **Touch targets**: Botones de mínimo 44px de altura
4. **Contenido**: Reorganizar información en pantallas pequeñas
5. **Performance móvil**: Optimizar imágenes y recursos

Mantén toda la funcionalidad pero adaptada a pantallas pequeñas.
```

#### **Prompt de Testing**
```
Crea casos de prueba para este módulo:

1. **Tests unitarios**: Para funciones de filtrado, ordenamiento, validaciones
2. **Tests de integración**: Para flujos completos de usuario
3. **Tests de accesibilidad**: Verificar ARIA labels y navegación
4. **Tests responsive**: En diferentes tamaños de pantalla
5. **Tests de rendimiento**: Verificar tiempos de carga

Incluye mocks de datos y casos edge.
```

---

## 📝 Notas de Implementación

### **Orden Recomendado de Implementación:**
1. **Dashboard** (más impacto visual)
2. **Cursos** (funcionalidad core)
3. **Reservas** (proceso crítico)
4. **Monitores** (gestión de recursos)
5. **Estadísticas** (valor añadido)
6. **Comunicaciones** (soporte)
7. **Usuarios** (administración)
8. **Configuración** (personalización)
9. **Resto de módulos**

### **Consideraciones Técnicas:**
- Mantener consistencia con el sistema de diseño establecido
- Usar los mismos patrones de componentes ya implementados
- Implementar lazy loading para mejor rendimiento
- Asegurar responsive design desde el inicio
- Incluir estados de loading, error y empty state
- Validar formularios en tiempo real
- Implementar búsqueda y filtrado consistentes

### **Testing antes de Release:**
- [ ] Compilación sin errores
- [ ] Responsive design funcional
- [ ] Estados de loading y error
- [ ] Accesibilidad básica
- [ ] Navegación intuitiva
- [ ] Performance aceptable
- [ ] Consistencia visual con sistema de diseño

---

**Cada prompt está diseñado para crear interfaces profesionales y consistentes que den una impresión seria y confiable a los usuarios de Boukii.**