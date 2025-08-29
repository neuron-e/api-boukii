# Boukii V5 – Guía de Tokens y Temas

Esta guía resume cómo usar los tokens de diseño y el soporte light/dark de forma coherente en `front/`.

## Tokens canónicos

- Fondo y superficies:
  - `--color-background`: fondo de página
  - `--color-surface`: superficie base (cards, contenedores)
  - `--color-surface-elevated`: superficie elevada (dropdowns, hovers)
  - `--color-surface-secondary`: superficie secundaria
- Texto:
  - `--color-text-primary`, `--color-text-secondary`, `--color-text-tertiary`
- Borde y sombras:
  - `--color-border`
  - `--shadow-xs | --shadow-sm | --shadow-base | --shadow-md | --shadow-lg`
- Primario/acciones:
  - `--color-primary`, `--color-primary-hover`, `--color-primary-focus`
- Marca Boukii:
  - `--color-ski-*` (blue/green/yellow/red, etc.)

Los alias de compatibilidad (`--surface`, `--surface-2`, `--text-1`, etc.) siguen existiendo, pero usa los tokens `--color-*` nuevos en estilos nuevos o refactors.

## Temas (light/dark)

- El archivo fuente de la verdad es `src/styles/tokens.css`.
- El tema activo se controla añadiendo/quittando la clase `.dark` en `<html>` o `<body>` (ya gestionado por `UiStore`).
- No necesitas importar `light.css`/`dark.css`; `tokens.css` define valores por defecto y mapeos.

## Patrones recomendados

- Superficies: `background: var(--color-surface); border: 1px solid var(--color-border); box-shadow: var(--shadow-sm);`
- Hover de listas/botones secundarios: `background: var(--color-surface-elevated);`
- Botón primario: usa variables semánticas
  - `background: var(--button-primary-bg)`
  - `color: var(--button-primary-text)`
  - `outline: 2px solid var(--color-primary-focus)`
- Texto:
  - Títulos y texto principal: `var(--color-text-primary)`
  - Texto secundario/metadata: `var(--color-text-secondary|tertiary)`

## Ejemplo

```css
.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
}
.btn--primary {
  background: var(--button-primary-bg);
  color: var(--button-primary-text);
  border-color: transparent;
}
```

## Migración de estilos antiguos

- Reemplaza `#fff`, `#f8fafc`, `#e5e7eb` por `--color-surface`, `--color-surface-elevated`, `--color-border`.
- Reemplaza `--text-1|2|muted` por `--color-text-primary|secondary|tertiary`.
- Sustituye `--surface`/`--surface-2` por `--color-surface`/`--color-surface-elevated`.
- Elimina definiciones duplicadas de tokens en hojas locales; importa `tokens.css`.
