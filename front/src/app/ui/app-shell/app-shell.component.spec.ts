import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { screen } from '@testing-library/dom';
import userEvent from '@testing-library/user-event';
import { OverlayContainer } from '@angular/cdk/overlay';
import { ThemeService } from '@core/services/theme.service';
import { signal, computed } from '@angular/core';
import { expect } from '@jest/globals';

import { AppShellComponent } from './app-shell.component';
import { UiStore } from '@core/stores/ui.store';
import { AuthStore } from '@core/stores/auth.store';
import { LoadingStore } from '@core/stores/loading.store';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { TranslationService, SupportedLanguage } from '@core/services/translation.service';
import { EnvironmentService } from '@core/services/environment.service';

class MockTranslationService {
  private lang: SupportedLanguage = 'es';

  currentLanguage(): SupportedLanguage {
    return this.lang;
  }

  setLanguage(lang: SupportedLanguage): void {
    this.lang = lang;
  }

  get(key: string): string {
    return key;
  }

  instant(key: string): string {
    return key;
  }
}

class MockUiStore {
  sidebarCollapsed = signal(false);
  private theme = signal<'light' | 'dark'>('light');
  isDark = computed(() => this.theme() === 'dark');


  initTheme(): void {
     this.setTheme(this.theme());
  }
  initFromStorage(): void {
    const stored = localStorage.getItem('sidebar-collapsed');
    this.sidebarCollapsed.set(stored === 'true');
  }
  initializeTheme(): void {
    this.setTheme(this.theme());
  }
  toggleSidebar(): void {
    const newState = !this.sidebarCollapsed();
    this.sidebarCollapsed.set(newState);
    localStorage.setItem('sidebar-collapsed', String(newState));
  }
  setTheme(theme: 'light' | 'dark'): void {
    this.theme.set(theme);
    localStorage.setItem('theme', theme);
    const isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.body.classList.toggle('dark', isDark);
  }

  toggleTheme(): void {
    const next = this.theme() === 'light' ? 'dark' : 'light';
    this.setTheme(next);
  }
}

describe('AppShellComponent interactions', () => {
  const logoutSpy = jest.fn();

  beforeEach(async () => {
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    document.body.classList.remove('dark');
    logoutSpy.mockReset();
    await TestBed.configureTestingModule({
      imports: [AppShellComponent],
      providers: [
        provideRouter([]),
        { provide: UiStore, useClass: MockUiStore },
        { provide: AuthStore, useValue: { loadMe: () => {} } },
        { provide: LoadingStore, useValue: { isLoading: () => false, longestRunningRequest: () => null } },
        {
          provide: AuthV5Service,
          useValue: {
            isAuthenticated: () => true,
            currentSchool: () => ({}),
            currentSchoolIdSignal: () => 1,
            user: () => ({ name: 'Test User' }),
            permissions: () => [],
            logout: logoutSpy,
          },
        },
        { provide: TranslationService, useClass: MockTranslationService },
        { provide: EnvironmentService, useValue: { isProduction: () => true, envName: () => 'production' } },
      ],
    }).compileComponents();
  });

  function renderComponent() {
    const fixture = TestBed.createComponent(AppShellComponent);
    fixture.detectChanges();
    document.body.appendChild(fixture.nativeElement);
    return fixture;
  }

  it('chevron toggles collapsed class, rotates and persists', async () => {
    const fixture = renderComponent();
    const user = userEvent.setup();
    const button = screen.getByLabelText('nav.collapse');
    const sidebar = fixture.nativeElement.querySelector('aside.app-sidebar');
    expect(sidebar.classList).not.toContain('collapsed');
    await user.click(button);
    fixture.detectChanges();
    expect(sidebar.classList).toContain('collapsed');
    expect(fixture.nativeElement.querySelector('.chev')?.classList).toContain('rot');
    expect(localStorage.getItem('sidebar-collapsed')).toBe('true');
    // Click again to expand and ensure button remains focusable
    await user.click(button);
    fixture.detectChanges();
    expect(sidebar.classList).not.toContain('collapsed');
    fixture.nativeElement.remove();
  });

  it('setTheme() propagates class to OverlayContainer', async () => {
    const fixture = renderComponent();
    const overlay = TestBed.inject(OverlayContainer);
    const themeService = TestBed.inject(ThemeService);
    themeService.setTheme('dark');
    await Promise.resolve();
    expect(overlay.getContainerElement().classList.contains('dark')).toBe(true);
    fixture.nativeElement.remove();
  });

  it('theme toggle persists selection and updates document class', async () => {
    const fixture = renderComponent();
    const user = userEvent.setup();
    const button = screen.getByTitle('theme.toggle');

    await user.click(button); // light -> dark
    fixture.detectChanges();
    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.body.classList.contains('dark')).toBe(true);

    await user.click(button); // dark -> light
    fixture.detectChanges();
    expect(localStorage.getItem('theme')).toBe('light');
    expect(document.body.classList.contains('dark')).toBe(false);

    fixture.nativeElement.remove();
  });

  it('changes language via TranslationService, persists selection, and updates active option', async () => {
    const translation = TestBed.inject(TranslationService) as unknown as MockTranslationService;
    const setLangSpy = jest.spyOn(translation, 'setLanguage');
    const fixture = renderComponent();
    const user = userEvent.setup();

    const languageButton = screen.getByRole('button', { name: 'Español' });
    await user.click(languageButton);
    fixture.detectChanges();

    const enOption = screen.getByRole('menuitemradio', { name: 'English' });
    await user.click(enOption);
    fixture.detectChanges();

    expect(setLangSpy).toHaveBeenCalledWith('en');
    expect(localStorage.getItem('language')).toBe('en');

    // reopen to check active state
    await user.click(screen.getByRole('button', { name: 'English' }));
    fixture.detectChanges();
    const enOptionActive = screen.getByRole('menuitemradio', { name: 'English' });
    const esOption = screen.getByRole('menuitemradio', { name: 'Español' });
    expect(enOptionActive.classList).toContain('active');
    expect(enOptionActive.getAttribute('aria-checked')).toBe('true');
    expect(esOption.classList).not.toContain('active');
    fixture.nativeElement.remove();
  });

  it('shows numeric badge when expanded and dot when collapsed', () => {
    const fixture = renderComponent();
    const ui = TestBed.inject(UiStore) as unknown as MockUiStore;

    const badge = fixture.nativeElement.querySelector('.notifications-container .badge') as HTMLElement;
    expect(badge.getAttribute('data-count')).toBe('2');
    expect(getComputedStyle(badge).width).toBe('18px');

    ui.toggleSidebar();
    fixture.detectChanges();

    expect(getComputedStyle(badge).width).toBe('6px');

    fixture.nativeElement.remove();
  });

  it('closes dropdowns with Escape key', async () => {
    const fixture = renderComponent();
    const user = userEvent.setup();

    const languageButton = screen.getByRole('button', { name: 'ES' });
    await user.click(languageButton);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('.language-dropdown')).not.toBeNull();

    await user.keyboard('{Escape}');
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('.language-dropdown')).toBeNull();

    fixture.nativeElement.remove();
  });

  it('closes dropdowns on click outside', async () => {
    const fixture = renderComponent();
    const user = userEvent.setup();

    const menuButton = screen.getByRole('button', { name: /Test User/ });
    await user.click(menuButton);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('.user-dropdown')).not.toBeNull();

    await user.click(document.body);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('.user-dropdown')).toBeNull();

    fixture.nativeElement.remove();
  });

  it('invokes logout without confirmation dialog', async () => {
    const fixture = renderComponent();
    const user = userEvent.setup();

    // Open user dropdown
    const menuButton = screen.getByRole('button', { name: /Test User/ });
    await user.click(menuButton);
    fixture.detectChanges();

    const confirmSpy = jest.spyOn(window, 'confirm');

    const logoutButton = screen.getByRole('menuitem', { name: 'userMenu.logout' });
    await user.click(logoutButton);

    expect(confirmSpy).not.toHaveBeenCalled();
    expect(logoutSpy).toHaveBeenCalled();
    fixture.nativeElement.remove();
  });
});
