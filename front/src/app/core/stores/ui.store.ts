import { Injectable, signal, computed } from '@angular/core';

export type Theme = 'light' | 'dark';

function getStoredSidebarState(): boolean {
  if (typeof window === 'undefined') return false;
  try {
    return localStorage.getItem('sidebar-collapsed') === 'true';
  } catch {
    return false;
  }
}

@Injectable({ providedIn: 'root' })
export class UiStore {
  // Private signals

  private readonly _sidebarCollapsed = signal(getStoredSidebarState());
  private readonly _theme = signal<Theme>('light');

  // Public readonly signals
  readonly sidebarCollapsed = this._sidebarCollapsed.asReadonly();
  readonly theme = this._theme.asReadonly();

  // Computed signals
  readonly effectiveTheme = computed(() => this._theme());
  readonly isDark = computed(() => this._theme() === 'dark');
  readonly sidebarIcon = computed(() => (this._sidebarCollapsed() ? 'panel-right' : 'panel-left'));

  // Methods
  initFromStorage(): void {
    if (typeof window === 'undefined') return;
    try {
      const stored = localStorage.getItem('sidebar-collapsed');
      this._sidebarCollapsed.set(stored === 'true');
    } catch {}
  }

  toggleSidebar(): void {
    const newState = !this._sidebarCollapsed();
    this._sidebarCollapsed.set(newState);
    // Persist sidebar state
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('sidebar-collapsed', String(newState));
      } catch {}
    }
  }

  setTheme(theme: Theme): void {
    const nextTheme = theme;
    this._theme.set(nextTheme);
    try {
      if (typeof document !== 'undefined') {
        const isDark = nextTheme === 'dark';
        document.documentElement.classList.toggle('dark', isDark);
        document.body.classList.toggle('dark', isDark);
      }
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem('theme', nextTheme);
      }
    } catch {}
  }

  toggleTheme(): void {
    const next = this._theme() === 'light' ? 'dark' : 'light';
    this.setTheme(next);
  }

  initTheme(): void {
    let v: Theme = 'light';
    try {
      if (typeof localStorage !== 'undefined') {
        v = (localStorage.getItem('theme') as Theme) || 'light';
      }
    } catch {}
    this._theme.set(v);
    try {
      if (typeof document !== 'undefined') {
        const isDark = v === 'dark';
        document.documentElement.classList.toggle('dark', isDark);
        document.body.classList.toggle('dark', isDark);
      }
    } catch {}
  }
}
