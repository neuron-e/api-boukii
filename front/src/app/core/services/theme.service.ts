import { Injectable, signal, computed, effect } from '@angular/core';
import { OverlayContainer } from '@angular/cdk/overlay';

export type Theme = 'light' | 'dark' | 'system';

@Injectable({
  providedIn: 'root',
})
export class ThemeService {
  private readonly STORAGE_KEY = 'boukii-theme';
  private readonly themeSignal = signal<Theme>(this.getStoredTheme());

  // Señales públicas de solo lectura
  readonly theme = this.themeSignal.asReadonly();
  readonly isDark = computed(() => this.theme() === 'dark');
  readonly isLight = computed(() => this.theme() === 'light');
  readonly isSystem = computed(() => this.theme() === 'system');

  // Señal computed para el tema efectivo (resuelve 'system')
  readonly effectiveTheme = computed<'light' | 'dark'>(() => {
    const theme = this.theme();
    if (theme === 'system') {
      return this.getSystemPreference();
    }
    return theme;
  });

  constructor(private overlay: OverlayContainer) {
    // Effect para aplicar el tema al DOM cuando cambie
    effect(() => {
      const theme = this.effectiveTheme();
      this.applyTheme(theme);
    });

    // Listener para cambios en la preferencia del sistema
    this.setupSystemPreferenceListener();
  }

  /**
   * Cambia el tema de la aplicación
   */
  setTheme(theme: Theme): void {
    this.themeSignal.set(theme);
    this.saveTheme(theme);
  }

  /**
   * Alterna entre light y dark (ignora system)
   */
  toggleTheme(): void {
    const currentEffective = this.effectiveTheme();
    const newTheme: Theme = currentEffective === 'light' ? 'dark' : 'light';
    this.setTheme(newTheme);
  }

  /**
   * Obtiene el tema guardado en localStorage
   */
  private getStoredTheme(): Theme {
    if (typeof window === 'undefined') {
      return 'system';
    }

    try {
      const stored = localStorage.getItem(this.STORAGE_KEY) as Theme | null;
      return this.isValidTheme(stored) ? stored : 'system';
    } catch {
      return 'system';
    }
  }

  /**
   * Guarda el tema en localStorage
   */
  private saveTheme(theme: Theme): void {
    if (typeof window === 'undefined') {
      return;
    }

    try {
      localStorage.setItem(this.STORAGE_KEY, theme);
    } catch {
      // Silently fail if localStorage is not available
    }
  }

  /**
   * Obtiene la preferencia del sistema
   */
  private getSystemPreference(): 'light' | 'dark' {
    if (typeof window === 'undefined') {
      return 'light';
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }

  /**
   * Configura el listener para cambios en la preferencia del sistema
   */
  private setupSystemPreferenceListener(): void {
    if (typeof window === 'undefined') {
      return;
    }

    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

    const handleChange = () => {
      // Solo re-evaluar si el tema actual es 'system'
      if (this.theme() === 'system') {
        // Forzar re-evaluación del computed
        this.themeSignal.set('system');
      }
    };

    // Usar addEventListener si está disponible, sino usar el método legacy
    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener('change', handleChange);
    } else {
      // Para compatibilidad con navegadores más antiguos
      mediaQuery.addListener(handleChange);
    }
  }

  /**
   * Aplica el tema al DOM
   */
  private applyTheme(theme: 'light' | 'dark'): void {
    if (typeof document === 'undefined') {
      return;
    }

    const root = document.body;
    const htmlEl = document.documentElement;
    const isDark = theme === 'dark';

    // Aplicar clase dark a elementos raíz
    htmlEl.classList.toggle('dark', isDark);
    root.classList.toggle('dark', isDark);

    // Mantener clases auxiliares de tema
    root.classList.remove('theme-light', 'theme-dark');
    root.classList.add(`theme-${theme}`);

    // Emitir evento personalizado para otros componentes que lo necesiten
    const event = new CustomEvent('themeChanged', {
      detail: { theme, previous: theme === 'light' ? 'dark' : 'light' },
    });
    document.dispatchEvent(event);

    // Propagar clase al contenedor de overlays para que herede las variables
    const el = this.overlay.getContainerElement();
    el.classList.toggle('dark', isDark);
  }

  /**
   * Valida si un valor es un tema válido
   */
  private isValidTheme(value: unknown): value is Theme {
    return typeof value === 'string' && ['light', 'dark', 'system'].includes(value);
  }

  /**
   * Método para obtener información de debug del estado del tema
   */
  getDebugInfo() {
    return {
      selected: this.theme(),
      effective: this.effectiveTheme(),
      systemPreference: this.getSystemPreference(),
      stored: this.getStoredTheme(),
    };
  }
}
