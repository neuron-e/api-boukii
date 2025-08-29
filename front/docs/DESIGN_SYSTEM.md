# Boukii V5 Design System

Documentación completa del sistema de diseño estándar para Boukii V5 Admin Panel.

## 📋 Índice

- [Principios de Diseño](#principios-de-diseño)
- [Tokens y Variables](#tokens-y-variables)
- [Componentes Estándar](#componentes-estándar)
- [Layout y Espaciado](#layout-y-espaciado)
- [Patrones de Uso](#patrones-de-uso)
- [Implementación](#implementación)

## 🎯 Principios de Diseño

### 1. **Consistencia**
- Todos los componentes siguen los mismos patrones visuales
- Uso exclusivo de tokens CSS para valores de diseño
- Nomenclatura estandarizada para clases y elementos

### 2. **Reutilización**
- Componentes modulares y reutilizables
- Estilos centralizados en `/src/styles/components/`
- Tokens unificados en `/src/styles/tokens.css`

### 3. **Accesibilidad**
- Contraste mínimo AA (4.5:1)
- Navegación por teclado completa
- Estados de focus y hover claramente definidos

### 4. **Responsive First**
- Mobile-first approach
- Breakpoints consistentes
- Contenido adaptable sin scroll horizontal

## 🎨 Tokens y Variables

### Colores Base
```css
/* Colores principales */
--color-primary: #00B7F6;         /* Azul Boukii */
--color-text-primary: #1A1A1A;    /* Negro principal */
--color-text-secondary: #444444;   /* Gris oscuro */
--color-text-tertiary: #6c757d;   /* Gris medio */

/* Estados */
--color-success-500: #10b981;     /* Verde éxito */
--color-warning-500: #f59e0b;     /* Amarillo advertencia */
--color-error-500: #ef4444;       /* Rojo error */
```

### Espaciado
```css
/* Escala de espaciado (base 4px) */
--space-1: 0.25rem;  /* 4px */
--space-2: 0.5rem;   /* 8px */
--space-3: 0.75rem;  /* 12px */
--space-4: 1rem;     /* 16px */
--space-5: 1.25rem;  /* 20px */
--space-6: 1.5rem;   /* 24px */
--space-8: 2rem;     /* 32px */
--space-10: 2.5rem;  /* 40px */
```

### Layout
```css
/* Dimensiones específicas */
--content-inline: 12px;           /* Margen lateral páginas */
--navbar-h: 56px;                /* Altura navbar */
--sidebar-w: 264px;              /* Ancho sidebar */
--kpi-card-h: 72px;              /* Altura cards KPI */
```

## 🧩 Componentes Estándar

### 1. Page Layout (`page-layout.css`)

#### Contenedor Principal
```html
<div class="page-container">
  <header class="page-header">
    <div class="header-content">
      <div class="header-main">
        <h1 class="page-title">Título</h1>
        <p class="page-subtitle">Subtítulo opcional</p>
      </div>
      <div class="header-actions">
        <!-- Chips y botones -->
      </div>
    </div>
  </header>
  
  <!-- Contenido de la página -->
</div>
```

#### Chips de Header
```html
<div class="header-chip chip-info">
  <svg><!-- icono --></svg>
  Texto informativo
</div>

<div class="header-chip chip-warning">
  <svg><!-- icono --></svg>
  Texto de advertencia
</div>
```

#### Botón Primario
```html
<button class="header-primary-btn">
  <svg><!-- icono --></svg>
  Texto del botón
</button>
```

### 2. KPI Cards (`kpi-cards.css`)

```html
<section class="kpi-cards-section">
  <div class="kpi-cards-grid">
    <div class="kpi-card">
      <div class="kpi-content">
        <div class="kpi-label">Etiqueta</div>
        <div class="kpi-number">123</div>
      </div>
      <div class="kpi-icon-container">
        <div class="kpi-icon icon-total">
          <svg><!-- icono --></svg>
        </div>
      </div>
    </div>
    <!-- Más cards... -->
  </div>
</section>
```

**Variantes de iconos disponibles:**
- `icon-total` - Azul
- `icon-nuevos` - Verde
- `icon-habituales` - Púrpura
- `icon-vip` - Amarillo
- `icon-activos` - Azul info
- `icon-datos-faltantes` - Amarillo advertencia

**Variante warning:**
```html
<div class="kpi-card kpi-card-warning">
  <!-- contenido -->
</div>
```

### 3. Data Tables (`table.css`)

```html
<div class="table-container">
  <div class="table-wrapper">
    <table class="data-table">
      <thead>
        <tr>
          <th>Columna 1</th>
          <th>Columna 2</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <tr class="table-row">
          <td class="table-cell-primary">Contenido principal</td>
          <td class="table-cell-secondary">Contenido secundario</td>
          <td class="table-actions">
            <button class="table-action-btn">
              <svg><!-- icono --></svg>
            </button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>
```

#### Elementos de Tabla Especializados

**Avatar:**
```html
<div class="table-avatar">
  <img src="..." class="avatar-image" alt="...">
  <span class="avatar-initials">AB</span>
</div>
```

**Badges:**
```html
<span class="table-badge badge-active">Activo</span>
<span class="table-badge badge-inactive">Inactivo</span>
<span class="table-badge badge-blocked">Bloqueado</span>
<span class="table-badge badge-vip">VIP</span>
<span class="table-badge badge-habitual">Habitual</span>
<span class="table-badge badge-nuevo">Nuevo</span>
```

### 4. Filtros y Búsqueda

```html
<section class="filters-section">
  <div class="filter-bar">
    <div class="search-container">
      <svg class="search-icon"><!-- icono búsqueda --></svg>
      <input type="text" class="search-input" placeholder="Buscar...">
    </div>
    
    <div class="filter-controls">
      <div class="filter-dropdown">
        <svg class="filter-icon"><!-- icono filtro --></svg>
        <select class="filter-select">
          <option>Opción 1</option>
        </select>
      </div>
      
      <div class="view-toggle">
        <button class="view-toggle-btn active">Tabla</button>
        <button class="view-toggle-btn">Tarjetas</button>
      </div>
    </div>
  </div>
</section>
```

## 📐 Layout y Espaciado

### Márgenes Estándar
- **Lateral de páginas**: `var(--content-inline)` = `12px`
- **Entre secciones**: `var(--space-6)` = `24px`
- **Elementos relacionados**: `var(--space-4)` = `16px`
- **Elementos pequeños**: `var(--space-2)` = `8px`

### Altura Componentes
- **KPI Cards**: `72px`
- **Campos de entrada**: `36px`
- **Botones pequeños**: `32px`
- **Filas de tabla**: `72px`

### Breakpoints
```css
/* Tablet */
@media (max-width: 1024px) { /* ... */ }

/* Mobile */
@media (max-width: 768px) { /* ... */ }

/* Pequeño */
@media (max-width: 480px) { /* ... */ }
```

## 🔧 Patrones de Uso

### 1. Páginas Listado
```html
<div class="page-container">
  <header class="page-header"><!-- título y acciones --></header>
  <section class="kpi-cards-section"><!-- métricas --></section>
  <section class="filters-section"><!-- búsqueda y filtros --></section>
  <div class="table-container"><!-- tabla de datos --></div>
</div>
```

### 2. Estado Hover y Focus
- **Elevación sutil**: `transform: translateY(-1px)`
- **Sombra**: `var(--shadow-sm)`
- **Border**: Cambio a color primario
- **Box-shadow**: Focus ring con 10% de opacidad del color primario

### 3. Estados de Carga
- Usar skeleton loaders con colores de fondo neutros
- Animaciones sutiles de fade-in/fade-out

## 🚀 Implementación

### 1. Importación
```scss
// En src/styles.scss
@import "./styles/components/index.css";
```

### 2. Uso en Componentes
```scss
// En component.scss - NO duplicar estilos, usar clases estándar
.my-specific-component {
  // Solo customizaciones específicas aquí
  // Usar variables CSS para valores
  color: var(--color-text-primary);
  padding: var(--space-4);
}
```

### 3. Nuevos Componentes
Antes de crear estilos nuevos:
1. ¿Existe un componente estándar similar?
2. ¿Se puede extender el sistema actual?
3. ¿Seguirá los tokens existentes?

### 4. Nomenclatura
- **Clases base**: nombre del componente (`kpi-card`, `data-table`)
- **Modificadores**: guión + descriptor (`kpi-card-warning`)
- **Elementos**: guión doble + elemento (`kpi-card__content`)
- **Estados**: punto + estado (`.active`, `.disabled`)

## ✅ Checklist de Implementación

Para cada nueva página o componente:

- [ ] Usa `page-container` como wrapper principal
- [ ] Header con estructura estándar (`page-header`)
- [ ] KPI cards con clases `kpi-cards-*` si aplica
- [ ] Filtros con `filters-section` y `filter-bar`
- [ ] Tablas con `table-container` y `data-table`
- [ ] Solo tokens CSS para colores, espaciado y tipografía
- [ ] Responsive comportamiento incluido
- [ ] Estados hover/focus/active definidos
- [ ] Accessibility (aria-labels, keyboard navigation)

---

## 📞 Soporte

Para dudas sobre el sistema de diseño:
1. Consultar esta documentación
2. Revisar ejemplos en `/src/app/features/clients/`
3. Verificar tokens en `/src/styles/tokens.css`
4. Usar componentes en `/src/styles/components/`

**Última actualización**: 2025-08-29