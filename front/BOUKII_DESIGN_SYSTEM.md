# Boukii V5 - Sistema de Diseño Profesional

> **Guía completa de estilos y patrones para crear interfaces profesionales y consistentes en toda la aplicación**

---

## 📋 Índice

1. [Principios de Diseño](#principios-de-diseño)
2. [Paleta de Colores](#paleta-de-colores)
3. [Tipografía](#tipografía)
4. [Espaciado y Layout](#espaciado-y-layout)
5. [Componentes Base](#componentes-base)
6. [Patrones de Interface](#patrones-de-interface)
7. [Iconografía](#iconografía)
8. [Estados y Feedback](#estados-y-feedback)
9. [Animaciones](#animaciones)
10. [Responsive Design](#responsive-design)

---

## 🎨 Principios de Diseño

### 1. **Profesionalidad**
- Interfaces limpias y serias
- Colores corporativos consistentes
- Tipografía legible y moderna
- Espaciado generoso y ordenado

### 2. **Usabilidad**
- Navegación intuitiva
- Estados visuales claros
- Feedback inmediato
- Accesibilidad prioritaria

### 3. **Consistencia**
- Patrones reutilizables
- Nomenclatura estándar
- Comportamientos predecibles
- Jerarquía visual clara

---

## 🎨 Paleta de Colores

### **Colores Principales**
```scss
// Azul Corporativo (Principal)
$primary-500: #3b82f6;
$primary-600: #2563eb;
$primary-gradient: linear-gradient(135deg, #3b82f6, #2563eb);

// Verde (Éxito/Stock)
$success-500: #10b981;
$success-600: #059669;
$success-gradient: linear-gradient(135deg, #10b981, #059669);

// Naranja (Advertencia)
$warning-500: #f59e0b;
$warning-600: #d97706;
$warning-gradient: linear-gradient(135deg, #f59e0b, #d97706);

// Rojo (Error/Peligro)
$error-500: #ef4444;
$error-600: #dc2626;
$error-gradient: linear-gradient(135deg, #ef4444, #dc2626);

// Púrpura (Especial)
$purple-500: #8b5cf6;
$purple-600: #7c3aed;
$purple-gradient: linear-gradient(135deg, #8b5cf6, #7c3aed);
```

### **Colores Neutros**
```scss
// Grises (Textos y Backgrounds)
$gray-50: #f8fafc;
$gray-100: #f1f5f9;
$gray-200: #e2e8f0;
$gray-300: #cbd5e1;
$gray-400: #94a3b8;
$gray-500: #64748b;
$gray-600: #475569;
$gray-700: #334155;
$gray-800: #1e293b;
$gray-900: #0f172a;

// Texto
$text-primary: #1e293b;
$text-secondary: #64748b;
$text-muted: #9ca3af;
$text-light: #d1d5db;
```

### **Colores de Estado**
```scss
// Estados Positivos
$active-bg: #dcfce7;
$active-text: #166534;
$available-bg: #dcfce7;
$available-text: #166534;

// Estados Negativos
$inactive-bg: #fee2e2;
$inactive-text: #991b1b;
$unavailable-bg: #fee2e2;
$unavailable-text: #991b1b;
```

---

## ✍️ Tipografía

### **Fuente Principal**
```scss
font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
```

### **Jerarquía de Títulos**
```scss
// Título Principal (Páginas)
.page-title {
  font-size: 2rem;          // 32px
  font-weight: 700;
  color: $text-primary;
  line-height: 1.2;
}

// Título Secundario (Secciones)
.section-title {
  font-size: 1.25rem;       // 20px
  font-weight: 700;
  color: $text-primary;
  line-height: 1.3;
}

// Título de Tarjeta
.card-title {
  font-size: 1.1rem;        // 18px
  font-weight: 600;
  color: $text-primary;
  line-height: 1.4;
}

// Subtítulo
.subtitle {
  font-size: 1rem;          // 16px
  font-weight: 400;
  color: $text-secondary;
  line-height: 1.5;
}
```

### **Texto de Contenido**
```scss
// Texto Base
.text-base {
  font-size: 1rem;          // 16px
  font-weight: 400;
  color: $text-primary;
  line-height: 1.6;
}

// Texto Pequeño
.text-small {
  font-size: 0.875rem;      // 14px
  font-weight: 400;
  color: $text-secondary;
  line-height: 1.5;
}

// Texto Muy Pequeño
.text-xs {
  font-size: 0.75rem;       // 12px
  font-weight: 500;
  color: $text-muted;
  line-height: 1.4;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
```

---

## 📐 Espaciado y Layout

### **Sistema de Espaciado**
```scss
$space-1: 0.25rem;  // 4px
$space-2: 0.5rem;   // 8px
$space-3: 0.75rem;  // 12px
$space-4: 1rem;     // 16px
$space-5: 1.25rem;  // 20px
$space-6: 1.5rem;   // 24px
$space-8: 2rem;     // 32px
$space-10: 2.5rem;  // 40px
$space-12: 3rem;    // 48px
$space-16: 4rem;    // 64px
```

### **Contenedores Base**
```scss
// Página Principal
.page-container {
  min-height: 100vh;
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
}

// Header de Página
.page-header {
  background: white;
  border-bottom: 1px solid #e2e8f0;
  padding: $space-8 $space-10;
  margin-bottom: $space-8;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

// Contenido Principal
.main-content {
  padding: 0 $space-10 $space-10;
}
```

### **Grids Responsive**
```scss
// Grid de Tarjetas
.cards-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: $space-6;
}

// Grid de Formulario
.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: $space-6;
  
  @media (max-width: 768px) {
    grid-template-columns: 1fr;
    gap: $space-4;
  }
}
```

---

## 🧩 Componentes Base

### **1. Botones**

#### **Botón Primario**
```scss
.btn-primary {
  display: flex;
  align-items: center;
  gap: $space-2;
  padding: $space-3 $space-5;
  border-radius: 0.5rem;
  font-weight: 600;
  font-size: 0.95rem;
  color: white;
  background: $primary-gradient;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
  
  &:hover {
    box-shadow: 0 6px 12px -1px rgba(59, 130, 246, 0.4);
    transform: translateY(-1px);
  }
  
  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
  }
}
```

#### **Botón Secundario**
```scss
.btn-secondary {
  display: flex;
  align-items: center;
  gap: $space-2;
  padding: $space-3 $space-5;
  border-radius: 0.5rem;
  font-weight: 600;
  font-size: 0.95rem;
  color: #475569;
  background: white;
  border: 1px solid #d1d5db;
  cursor: pointer;
  transition: all 0.2s ease;
  
  &:hover {
    background: #f8fafc;
    border-color: #9ca3af;
    transform: translateY(-1px);
  }
}
```

### **2. Campos de Formulario**

#### **Input de Texto**
```scss
.form-input {
  width: 100%;
  padding: $space-3 $space-4;
  border: 2px solid #d1d5db;
  border-radius: 0.5rem;
  font-size: 1rem;
  background: white;
  transition: all 0.2s ease;
  
  &:focus {
    outline: none;
    border-color: $primary-500;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
  }
  
  &::placeholder {
    color: #9ca3af;
  }
  
  &:invalid {
    border-color: $error-500;
    
    &:focus {
      border-color: $error-500;
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }
  }
}
```

#### **Campo con Icono**
```scss
.input-with-icon {
  position: relative;
  
  .input-icon {
    position: absolute;
    left: $space-4;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
  }
  
  .form-input {
    padding-left: 3rem;
  }
}
```

### **3. Tarjetas**

#### **Tarjeta Base**
```scss
.card {
  background: white;
  border-radius: 1rem;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  border: 1px solid #f1f5f9;
  overflow: hidden;
  transition: all 0.3s ease;
  
  &:hover {
    box-shadow: 0 8px 25px 0 rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
  }
}
```

#### **Tarjeta con Header**
```scss
.card-header {
  background: linear-gradient(135deg, #f8fafc, #f1f5f9);
  padding: $space-8 $space-8 $space-4;
  text-align: center;
}

.card-body {
  padding: $space-6 $space-8;
}

.card-footer {
  padding: $space-4 $space-8 $space-8;
  border-top: 1px solid #f1f5f9;
}
```

### **4. Tablas**

#### **Tabla Profesional**
```scss
.data-table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
  
  thead {
    background: #f8fafc;
    border-bottom: 2px solid #e2e8f0;
    
    th {
      padding: $space-4 $space-6;
      text-align: left;
      font-weight: 700;
      color: #374151;
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }
  }
  
  tbody {
    tr {
      border-bottom: 1px solid #f1f5f9;
      transition: all 0.2s ease;
      
      &:hover {
        background: #f8fafc;
      }
      
      td {
        padding: $space-4 $space-6;
        vertical-align: middle;
      }
    }
  }
}
```

---

## 🎭 Patrones de Interface

### **1. Estructura de Página Estándar**
```html
<div class="page-container">
  <!-- Header con título y acciones -->
  <header class="page-header">
    <div class="header-content">
      <h1 class="page-title">
        <svg class="title-icon">...</svg>
        Título de Página
      </h1>
      <p class="page-subtitle">Descripción de la página</p>
    </div>
    <div class="header-actions">
      <button class="btn-secondary">Acción Secundaria</button>
      <button class="btn-primary">Acción Principal</button>
    </div>
  </header>

  <!-- Filtros y búsqueda -->
  <section class="filters-section">
    <div class="search-box">
      <svg class="search-icon">...</svg>
      <input class="search-input" placeholder="Buscar...">
    </div>
    <div class="filter-controls">
      <!-- Filtros adicionales -->
    </div>
  </section>

  <!-- Contenido principal -->
  <main class="main-content">
    <!-- Grid de tarjetas o tabla -->
  </main>
</div>
```

### **2. Modal/Formulario**
```html
<div class="modal-overlay">
  <div class="modal-container">
    <!-- Header del modal -->
    <header class="modal-header">
      <div class="header-content">
        <h2 class="modal-title">
          <svg class="title-icon">...</svg>
          Título del Modal
        </h2>
        <p class="modal-subtitle">Descripción</p>
      </div>
      <button class="close-btn">×</button>
    </header>
    
    <!-- Contenido del modal -->
    <div class="modal-content">
      <!-- Formulario o contenido -->
    </div>
    
    <!-- Acciones del modal -->
    <div class="modal-actions">
      <button class="btn-secondary">Cancelar</button>
      <button class="btn-primary">Confirmar</button>
    </div>
  </div>
</div>
```

### **3. Estado Vacío**
```html
<div class="empty-state">
  <div class="empty-icon">
    <svg>...</svg>
  </div>
  <h3 class="empty-title">No hay elementos</h3>
  <p class="empty-message">Descripción del estado vacío</p>
  <button class="btn-primary">Acción Sugerida</button>
</div>
```

---

## 🎨 Iconografía

### **Biblioteca de Iconos**
Se utilizan iconos de **Heroicons** (Outline) con las siguientes especificaciones:

```scss
// Tamaños estándar
.icon-xs { width: 16px; height: 16px; }   // Botones pequeños
.icon-sm { width: 20px; height: 20px; }   // Botones normales
.icon-md { width: 24px; height: 24px; }   // Títulos
.icon-lg { width: 32px; height: 32px; }   // Headers
.icon-xl { width: 48px; height: 48px; }   // Estados vacíos

// Propiedades base
.icon {
  stroke-width: 2;
  fill: none;
  stroke: currentColor;
}
```

### **Iconos por Contexto**
- **Usuarios**: user, user-group, user-plus
- **Equipamiento**: cube, squares-2x2, wrench-screwdriver
- **Reservas**: calendar, clock, check-circle
- **Acciones**: plus, pencil, eye, trash, arrow-right
- **Estados**: check-circle, x-circle, exclamation-triangle
- **Navegación**: chevron-down, chevron-right, bars-3

---

## 📱 Estados y Feedback

### **1. Estados de Botón**
```scss
// Estado Loading
.btn.loading {
  cursor: not-allowed;
  opacity: 0.8;
  
  .btn-text { opacity: 0.7; }
  
  .spinner {
    animation: spin 1s linear infinite;
    margin-right: $space-2;
  }
}

// Estado Deshabilitado
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}
```

### **2. Estados de Elementos**
```scss
// Elemento Activo
.element.active {
  background: #dcfce7;
  color: #166534;
  border-color: #16a34a;
}

// Elemento Inactivo
.element.inactive {
  background: #fee2e2;
  color: #991b1b;
  border-color: #dc2626;
  opacity: 0.7;
}

// Elemento Seleccionado
.element.selected {
  background: $primary-gradient;
  color: white;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}
```

### **3. Mensajes de Error**
```scss
.field-error {
  color: $error-500;
  font-size: 0.875rem;
  margin-top: $space-2;
  font-weight: 500;
  display: flex;
  align-items: center;
  gap: $space-1;
}
```

---

## ⚡ Animaciones

### **1. Transiciones Base**
```scss
// Transición estándar
.transition {
  transition: all 0.2s ease;
}

// Transición suave
.transition-smooth {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
```

### **2. Animaciones de Entrada**
```scss
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
```

### **3. Hover Effects**
```scss
.hover-lift {
  transition: transform 0.2s ease;
  
  &:hover {
    transform: translateY(-2px);
  }
}

.hover-scale {
  transition: transform 0.2s ease;
  
  &:hover {
    transform: scale(1.05);
  }
}
```

---

## 📱 Responsive Design

### **Breakpoints**
```scss
// Móvil pequeño
$mobile-sm: 480px;

// Móvil
$mobile: 768px;

// Tablet
$tablet: 1024px;

// Desktop
$desktop: 1280px;

// Desktop grande
$desktop-lg: 1536px;
```

### **Patrones Responsive**
```scss
// Grid adaptativo
.responsive-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: $space-6;
  
  @media (max-width: $mobile) {
    grid-template-columns: 1fr;
    gap: $space-4;
  }
}

// Header responsive
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  
  @media (max-width: $mobile) {
    flex-direction: column;
    gap: $space-6;
    align-items: stretch;
    
    .header-actions {
      display: flex;
      gap: $space-4;
      
      .btn {
        flex: 1;
      }
    }
  }
}

// Padding responsive
.responsive-padding {
  padding: 0 $space-10 $space-10;
  
  @media (max-width: $mobile) {
    padding: 0 $space-6 $space-6;
  }
  
  @media (max-width: $mobile-sm) {
    padding: 0 $space-4 $space-4;
  }
}
```

---

## 🛠️ Utilidades CSS

### **Espaciado**
```scss
// Margins
.m-0 { margin: 0; }
.m-1 { margin: $space-1; }
.m-2 { margin: $space-2; }
// ... hasta m-16

// Paddings
.p-0 { padding: 0; }
.p-1 { padding: $space-1; }
.p-2 { padding: $space-2; }
// ... hasta p-16
```

### **Colores de Texto**
```scss
.text-primary { color: $text-primary; }
.text-secondary { color: $text-secondary; }
.text-muted { color: $text-muted; }
.text-success { color: $success-600; }
.text-warning { color: $warning-600; }
.text-error { color: $error-600; }
```

### **Backgrounds**
```scss
.bg-white { background: white; }
.bg-gray-50 { background: $gray-50; }
.bg-primary { background: $primary-gradient; }
.bg-success { background: $success-gradient; }
.bg-warning { background: $warning-gradient; }
.bg-error { background: $error-gradient; }
```

---

## 🎯 Checklist de Implementación

### **Para cada pantalla nueva:**

- [ ] **Header profesional** con título, subtítulo e iconos
- [ ] **Barra de búsqueda** con icono y placeholder descriptivo
- [ ] **Filtros y controles** organizados y funcionales
- [ ] **Vista de tarjetas Y tabla** (toggle entre ambas)
- [ ] **Estados vacíos** informativos y útiles
- [ ] **Loading states** y feedback visual
- [ ] **Botones de acción** primarios y secundarios
- [ ] **Responsive design** para móviles
- [ ] **Hover effects** y transiciones
- [ ] **Colores consistentes** según la paleta
- [ ] **Tipografía correcta** según jerarquías
- [ ] **Espaciado uniforme** usando sistema de grid
- [ ] **Iconografía apropiada** y consistente

---

## 📝 Notas Finales

Este sistema de diseño debe aplicarse de forma **consistente** en toda la aplicación. Cada nueva pantalla debe seguir estos patrones para mantener la **coherencia visual** y **profesionalidad** del producto.

**Recuerda**: El objetivo es crear interfaces que sean **profesionales**, **intuitivas** y **modernas**, apropiadas para un entorno empresarial serio.