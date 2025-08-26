import { Component, inject, OnInit, computed, signal, HostListener, ElementRef, ViewChild } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet, RouterLink, RouterLinkActive, Router } from '@angular/router';
import { ThemeToggleComponent } from '@ui/theme-toggle/theme-toggle.component';
import { ToastComponent } from '@shared/components/toast/toast.component';
import { LanguageSelectorComponent } from '@shared/components/language-selector/language-selector.component';
import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { UiStore } from '@core/stores/ui.store';
import { AuthStore } from '@core/stores/auth.store';
import { LoadingStore } from '@core/stores/loading.store';
import { TranslationService, SupportedLanguage, SUPPORTED_LANGUAGES } from '@core/services/translation.service';
import { EnvironmentService } from '@core/services/environment.service';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { LANGUAGE_STORAGE_KEY, getLanguageConfig } from '@core/config/languages.config';
import { ROLE_SUPERADMIN } from '@core/constants/roles';

interface Notification {
  id: string;
  title: string;
  message: string;
  type: 'info' | 'success' | 'warning' | 'error';
  read: boolean;
  createdAt: Date;
}

@Component({
  selector: 'app-shell',
  standalone: true,
  imports: [
    CommonModule,
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    ThemeToggleComponent,
    ToastComponent,
    LanguageSelectorComponent,
    TranslatePipe,
  ],
  styleUrl: './app-shell.styles.scss',
  template: `
    <!-- Admin Shell - always shows full layout with header and sidebar -->
    <div class="app-shell">
      <!-- NAVBAR -->
      <header class="app-navbar" role="banner">
        <!-- Search (flex:1) -->
        <div class="search">
          <input
            type="text"
            [placeholder]="translationService.instant('nav.searchPlaceholder')"
            [attr.aria-label]="translationService.instant('nav.searchPlaceholder')"
          />
        </div>

        <!-- Language Menu -->
        <div class="language-menu">
          <button
            #languageButton
            class="icon-btn language-toggle only-flag"
            (click)="toggleLanguageDropdown()"
            [attr.aria-expanded]="languageDropdownOpen() ? 'true' : 'false'"
            aria-haspopup="menu"
            [attr.aria-controls]="'language-menu'"
            [attr.title]="label(translationService.currentLanguage())"
          >
            <img class="flag" [src]="flag(translationService.currentLanguage())" alt="" aria-hidden="true" />
          </button>

          @if (languageDropdownOpen()) {
            <div #languageMenu id="language-menu" class="language-dropdown" role="menu">
              @for (lang of languages; track lang) {
                <button
                  class="dropdown-option"
                  [class.active]="isLang(lang)"
                  (click)="setLanguage(lang)"
                  role="menuitemradio"
                  [attr.aria-checked]="isLang(lang)"
                  tabindex="-1"
                >
                  <img [src]="flag(lang)" alt="" aria-hidden="true" />
                  {{ label(lang) }}
                </button>
              }
            </div>
          }
        </div>
        <!-- Theme Toggle -->
        <button
          class="icon-btn"
          data-cy="theme-toggle"
          (click)="ui.toggleTheme()"
          [attr.aria-label]="ui.isDark() ? translationService.instant('theme.switchToLight') : translationService.instant('theme.switchToDark')"
          [attr.title]="translationService.instant('theme.toggle')"
        >
          @if (ui.isDark()) {
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="5"></circle>
              <line x1="12" y1="1" x2="12" y2="3"></line>
              <line x1="12" y1="21" x2="12" y2="23"></line>
              <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
              <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
              <line x1="1" y1="12" x2="3" y2="12"></line>
              <line x1="21" y1="12" x2="23" y2="12"></line>
              <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
              <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
            </svg>
          } @else {
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
            </svg>
          }
        </button>

        <!-- Notifications with badge -->
        <div class="notifications-container">
          <button
            #notificationsButton
            class="icon-btn notifications-btn"
            (click)="toggleNotifications()"
            [attr.aria-label]="translationService.instant('nav.notifications')"
            [attr.title]="translationService.instant('nav.notifications')"
            [attr.aria-expanded]="notificationDropdownOpen() ? 'true' : 'false'"
            aria-haspopup="menu"
            [attr.aria-controls]="'notifications-menu'"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
              <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
            </svg>
            @if (unreadNotificationsCount() > 0) {
              <span class="badge badge--red">{{ unreadNotificationsCount() }}</span>
            }
          </button>

          @if (notificationDropdownOpen()) {
            <div #notificationsMenu id="notifications-menu" class="notifications-dropdown" role="menu">
              @if (notifications().length === 0) {
                <div class="no-notifications">
                  <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                  </svg>
                  <p>{{ translationService.instant('notifications.noNotifications') }}</p>
                </div>
              } @else {
                <div class="notifications-header">
                  <h3>{{ translationService.instant('notifications.title') }}</h3>
                  @if (unreadNotificationsCount() > 0) {
                    <button class="mark-all-read" (click)="markAllAsRead()">
                      {{ translationService.instant('notifications.markAllAsRead') }}
                    </button>
                  }
                </div>
                <div class="notifications-list">
                  @for (notification of notifications(); track notification.id) {
                    <div
                      class="notification-item"
                      [class.unread]="!notification.read"
                      (click)="markAsRead(notification.id)"
                      role="menuitem"
                      tabindex="-1"
                    >
                      <div class="notification-icon" [class]="'notification-' + notification.type">
                        @switch (notification.type) {
                          @case ('info') {
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                              <circle cx="12" cy="12" r="10"></circle>
                              <path d="M12 16v-4"></path>
                              <path d="M12 8h.01"></path>
                            </svg>
                          }
                          @case ('success') {
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                              <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                              <polyline points="22,4 12,14.01 9,11.01"></polyline>
                            </svg>
                          }
                          @case ('warning') {
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                              <line x1="12" y1="9" x2="12" y2="13"></line>
                              <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                          }
                          @case ('error') {
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                              <circle cx="12" cy="12" r="10"></circle>
                              <line x1="15" y1="9" x2="9" y2="15"></line>
                              <line x1="9" y1="9" x2="15" y2="15"></line>
                            </svg>
                          }
                        }
                      </div>
                      <div class="notification-content">
                        <p class="notification-title">{{ notification.title }}</p>
                        <p class="notification-message">{{ notification.message }}</p>
                        <span class="notification-time">{{ getRelativeTime(notification.createdAt) }}</span>
                      </div>
                      @if (!notification.read) {
                        <div class="unread-indicator"></div>
                      }
                    </div>
                  }
                </div>
              }
            </div>
          }
        </div>

        <!-- User Menu -->
        <div class="user-menu">
          <button
            #userButton
            class="user-trigger"
            (click)="toggleUserDropdown()"
            [attr.aria-expanded]="userDropdownOpen() ? 'true' : 'false'"
            aria-haspopup="menu"
            [attr.aria-controls]="'user-menu'"
          >
            <img
              class="user-avatar"
              [src]="getUserAvatar()"
              [alt]="authV5.user()?.name || translationService.instant('userMenu.defaultName')"
            />
            <div class="user-info">
              <div class="user-name">{{ authV5.user()?.name || translationService.instant('userMenu.defaultName') }}</div>
              <div class="user-role">{{ getUserRole() }}</div>
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="6,9 12,15 18,9"></polyline>
            </svg>
          </button>

          @if (userDropdownOpen()) {
            <div #userMenu id="user-menu" class="user-dropdown" role="menu">
              <div class="user-dropdown-header">
                <img
                  class="user-avatar-large"
                  [src]="getUserAvatar()"
                  [alt]="authV5.user()?.name || translationService.instant('userMenu.defaultName')"
                />
                <div class="user-details">
                  <h3 class="user-name-large">{{ authV5.user()?.name || translationService.instant('userMenu.defaultName') }}</h3>
                  <p class="user-email">{{ authV5.user()?.email || translationService.instant('userMenu.defaultEmail') }}</p>
                  <span class="user-role-badge">{{ getUserRole() }}</span>
                </div>
              </div>

              <div class="dropdown-divider"></div>

              <button class="dropdown-option" role="menuitem" tabindex="-1">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                  <circle cx="12" cy="7" r="4"></circle>
                </svg>
                {{ translationService.instant('userMenu.profile') }}
              </button>
              <button class="dropdown-option" role="menuitem" tabindex="-1">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"></path>
                  <circle cx="12" cy="12" r="3"></circle>
                </svg>
                {{ translationService.instant('userMenu.settings') }}
              </button>

              <div class="dropdown-divider"></div>

              <button class="dropdown-option danger" (click)="logout()" [disabled]="loggingOut()" role="menuitem" tabindex="-1">
                @if (loggingOut()) {
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="loading-spinner">
                    <circle cx="12" cy="12" r="10" opacity="0.3"></circle>
                    <path d="M4 12a8 8 0 0 1 8-8V4a10 10 0 0 0-10 10h2z"></path>
                  </svg>
                } @else {
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16,17 21,12 16,7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                  </svg>
                }
                {{ loggingOut() ? translationService.instant('userMenu.loggingOut') : translationService.instant('userMenu.logout') }}
              </button>
            </div>
          }
        </div>
      </header>

      <!-- SIDEBAR -->
      <aside class="app-sidebar" [class.collapsed]="ui.sidebarCollapsed()" role="navigation" [attr.aria-label]="translationService.instant('nav.mainNavigation')">
        <!-- Sidebar Header -->
        <div class="sidebar-header">
          <img class="logo" src="assets/logo.svg" alt="boukii" />
          <button
            class="collapse"
            type="button"
            (click)="ui.toggleSidebar()"
            [attr.aria-expanded]="!ui.sidebarCollapsed()"
            [attr.aria-label]="ui.sidebarCollapsed() ? translationService.instant('nav.expand') : translationService.instant('nav.collapse')"
            aria-controls="app-sidebar"
          >
            <!-- flecha izquierda; rota al colapsar -->
            <svg class="chev" [class.rot]="ui.sidebarCollapsed()" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
              <polyline points="15 6 9 12 15 18" fill="none" stroke="currentColor" stroke-width="2"/>
            </svg>
          </button>
        </div>

        <!-- Navigation Menu -->
        <nav class="sidebar-nav">
          <!-- Dashboard -->
          <a 
            routerLink="/dashboard" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.dashboard') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.dashboard') }}</span>
            <span class="badge counter counter--yellow">3</span>
          </a>

          <!-- Reservas -->
          <a 
            routerLink="/reservations" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.reservas') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.reservas') }}</span>
            <span class="badge counter counter--blue">12</span>
          </a>

          <!-- Clientes -->

          <a
            routerLink="/clients"
            routerLinkActive="active"
            class="nav-item"
            role="menuitem"
            [title]="ui.sidebarCollapsed() ? ('nav.clientes' | translate) : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.clientes') }}</span>
            <span class="badge counter counter--green">8</span>
          </a>

          <!-- Planificador -->
          <a 
            routerLink="/scheduling" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.planificador') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.planificador') }}</span>
            <span class="badge counter counter--red">2</span>
          </a>

          <!-- Instructores -->
          <a 
            routerLink="/monitors" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.instructores') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.instructores') }}</span>
            <span class="badge counter counter--green">5</span>
          </a>

          <!-- Cursos -->
          <a 
            routerLink="/courses" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.cursos') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82zM12 3L1 9l11 6 9-4.91V17h2V9L12 3z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.cursos') }}</span>
            <span class="badge counter counter--blue">15</span>
          </a>

          <!-- Material -->
          <a
            routerLink="/renting"
            routerLinkActive="active"
            class="nav-item"
            role="menuitem"
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.material') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.material') }}</span>
            <span class="badge counter counter--yellow">4</span>
          </a>

          <!-- Bonos -->
          <a
            routerLink="/vouchers"
            routerLinkActive="active"
            class="nav-item"
            role="menuitem"
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.bonos') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M4 4h16v2H4zm0 5h16v6H4zm0 11h16v-2H4z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.bonos') }}</span>
            <span class="badge counter counter--green">7</span>
          </a>

          <!-- Comunicación -->
            <a routerLink="/communications"
               routerLinkActive="active"
               class="nav-item"
               [title]="ui.sidebarCollapsed() ? translationService.instant('nav.comunicacion') : null">
              <div class="nav-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                  <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4v6l4-3 4 3v-6h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
              </div>
              <span class="nav-text label">{{ translationService.instant('nav.comunicacion') }}</span>
              <span class="badge counter counter--red">3</span>
            </a>

          <!-- Chat -->
          <a 
            routerLink="/chat" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.chat') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h4v6l4-3 4 3v-6h4c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.chat') }}</span>
            <span class="badge counter counter--blue">1</span>
          </a>

          <!-- Pagos -->
          <a 
            href="#" 
            class="nav-item disabled" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? (translationService.instant('nav.pagos') + ' (Próximamente)') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.pagos') }}</span>
            <span class="badge counter counter--blue">11</span>
          </a>

          <!-- Reportes -->
          <a 
            routerLink="/statistics" 
            routerLinkActive="active" 
            class="nav-item" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? translationService.instant('nav.reportes') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M3.5 18.49l6-6.01 4 4L22 6.92l-1.41-1.41-7.09 7.97-4-4L2 16.99z"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.reportes') }}</span>
            <span class="badge counter counter--green">2</span>
          </a>

          <!-- Notificaciones -->
          <a 
            href="#" 
            class="nav-item disabled" 
            role="menuitem" 
            [title]="ui.sidebarCollapsed() ? (translationService.instant('nav.notifications') + ' (Próximamente)') : null"
          >
            <div class="nav-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
              </svg>
            </div>
            <span class="nav-text label">{{ translationService.instant('nav.notifications') }}</span>
          </a>

          @if (showSettings()) {
            <a
              routerLink="/settings"
              routerLinkActive="active"
              class="nav-item"
              role="menuitem"
              [title]="ui.sidebarCollapsed() ? translationService.instant('nav.config') : null"
            >
              <div class="nav-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                  <path
                    d="M19.14 12.94a7 7 0 0 0 0-1.88l2.11-1.65a.5.5 0 0 0 .12-.64l-2-3.46a.5.5 0 0 0-.61-.23l-2.49 1a7 7 0 0 0-1.6-.93l-.38-2.65a.5.5 0 0 0-.5-.43h-4a.5.5 0 0 0-.5.43L8.83 5a7 7 0 0 0-1.6.93l-2.49-1a.5.5 0 0 0-.61.23l-2 3.46a.5.5 0 0 0 .12.64L4.86 11a7 7 0 0 0 0 1.88L2.75 14.5a.5.5 0 0 0-.12.64l2 3.46a.5.5 0 0 0 .61.23l2.49-1a7 7 0 0 0 1.6.93l.38 2.65a.5.5 0 0 0 .5.43h4a.5.5 0 0 0 .5-.43l.38-2.65a7 7 0 0 0 1.6-.93l2.49 1a.5.5 0 0 0 .61-.23l2-3.46a.5.5 0 0 0-.12-.64ZM12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5Z"
                  />
                </svg>
              </div>
              <span class="nav-text label">{{ translationService.instant('nav.config') }}</span>
            </a>
          }
        </nav>

        <!-- Support Section -->
        <div class="sidebar-support">
          <div class="support-content">
            <div class="support-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V6a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
              </svg>
            </div>
            <div class="support-text">
              <div class="support-title">{{ translationService.instant('support.needHelp') }}</div>
              <div class="support-subtitle">{{ translationService.instant('support.support') }}</div>
            </div>
          </div>
          <button
            class="support-btn"
            [title]="ui.sidebarCollapsed() ? translationService.instant('support.support') : null"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </button>
        </div>
      </aside>

      <!-- MAIN CONTENT AREA -->
      <main class="app-main" role="main">
        <div class="main-content">
          <router-outlet />
        </div>
      </main>

      <!-- Global loading indicator -->
      @if (loadingStore.isLoading()) {
        <div
          class="global-loading"
          [attr.aria-label]="loadingStore.longestRunningRequest()?.description || translationService.instant('common.loading')"
        >
          <div class="loading-spinner"></div>
        </div>
      }

      <!-- Environment Badge -->
      @if (!environmentService.isProduction()) {
        <div class="env-badge">
          {{ environmentService.envName() | uppercase }}
        </div>
      }
    </div>

    <!-- Toast notifications -->
    <app-toast-container />
  `,
})
export class AppShellComponent implements OnInit {
  // Injected services
  protected readonly ui = inject(UiStore);
  protected readonly auth = inject(AuthStore);
  protected readonly loadingStore = inject(LoadingStore);
  protected readonly authV5 = inject(AuthV5Service);
  protected readonly router = inject(Router);

  readonly languages = SUPPORTED_LANGUAGES;

  constructor(
    public readonly translationService: TranslationService,
    public readonly environmentService: EnvironmentService,
  ) {}

  @ViewChild('languageMenu') languageMenu?: ElementRef<HTMLDivElement>;
  @ViewChild('languageButton') languageButton?: ElementRef<HTMLButtonElement>;
  @ViewChild('userMenu') userMenu?: ElementRef<HTMLDivElement>;
  @ViewChild('userButton') userButton?: ElementRef<HTMLButtonElement>;
  @ViewChild('notificationsMenu') notificationsMenu?: ElementRef<HTMLDivElement>;
  @ViewChild('notificationsButton') notificationsButton?: ElementRef<HTMLButtonElement>;

  // Component state signals
  private readonly _languageDropdownOpen = signal(false);
  private readonly _userDropdownOpen = signal(false);
  private readonly _notificationDropdownOpen = signal(false);
  private readonly _loggingOut = signal(false);
  private readonly _notifications = signal<Notification[]>([
    {
      id: '1',
      title: 'Nueva reserva',
      message: 'Juan Pérez ha realizado una nueva reserva para mañana',
      type: 'info',
      read: false,
      createdAt: new Date(Date.now() - 1000 * 60 * 5) // 5 minutes ago
    },
    {
      id: '2',
      title: 'Pago completado',
      message: 'Se ha recibido el pago de María García',
      type: 'success',
      read: false,
      createdAt: new Date(Date.now() - 1000 * 60 * 30) // 30 minutes ago
    },
    {
      id: '3',
      title: 'Clase cancelada',
      message: 'La clase de natación de las 15:00 ha sido cancelada',
      type: 'warning',
      read: true,
      createdAt: new Date(Date.now() - 1000 * 60 * 60 * 2) // 2 hours ago
    }
  ]);

  // Public computed properties
  readonly languageDropdownOpen = this._languageDropdownOpen.asReadonly();
  readonly userDropdownOpen = this._userDropdownOpen.asReadonly();
  readonly notificationDropdownOpen = this._notificationDropdownOpen.asReadonly();
  readonly notifications = this._notifications.asReadonly();
  readonly loggingOut = this._loggingOut.asReadonly();

  // Computed for notification count
  readonly unreadNotificationsCount = computed(() => {
    return this._notifications().filter(notification => !notification.read).length;
  });

  readonly showSettings = computed(() => {
    const permissions = this.authV5.permissions();
    return (
      permissions.includes('admin') ||
      permissions.includes(ROLE_SUPERADMIN) ||
      permissions.includes('super-admin')
    );
  });


  ngOnInit(): void {
    // Load stored UI preferences
    this.ui.initFromStorage();
    // Initialize theme system
    this.ui.initTheme();

    // Try to load user session if token exists
    this.auth.loadMe();
    this.router.events.subscribe(() => this.closeAll());
  }

  // Sidebar management - removed, delegated to UiStore directly

  // Language management
  toggleLanguageDropdown(): void {
    const opening = !this._languageDropdownOpen();
    this._languageDropdownOpen.set(opening);

    if (opening) {
      setTimeout(() => {
        const firstOption = this.languageMenu?.nativeElement.querySelector<HTMLButtonElement>(
          '.dropdown-option'
        );
        firstOption?.focus();
      });
    } else {
      this.languageButton?.nativeElement.focus();
    }

    // Close other dropdowns if open
    if (this._userDropdownOpen()) {
      this._userDropdownOpen.set(false);
    }
    if (this._notificationDropdownOpen()) {
      this._notificationDropdownOpen.set(false);
    }
  }

  getCurrentLanguage(): string {
    return this.label(this.translationService.currentLanguage());
  }

  flag(lang: SupportedLanguage): string {
    return `assets/img/icons/flags/${lang}.svg`;
  }

  label(lang: SupportedLanguage): string {
    return getLanguageConfig(lang).nativeName;
  }

  isLang(lang: SupportedLanguage): boolean {
    return this.translationService.currentLanguage() === lang;
  }

  async setLanguage(lang: SupportedLanguage): Promise<void> {
    localStorage.setItem(LANGUAGE_STORAGE_KEY, lang);
    await this.translationService.setLanguage(lang);
    this._languageDropdownOpen.set(false);
    this.languageButton?.nativeElement.focus();
  }

  // User menu management
  toggleUserDropdown(): void {
    const opening = !this._userDropdownOpen();
    this._userDropdownOpen.set(opening);

    if (opening) {
      setTimeout(() => {
        const firstOption = this.userMenu?.nativeElement.querySelector<HTMLButtonElement>(
          '.dropdown-option'
        );
        firstOption?.focus();
      });
    } else {
      this.userButton?.nativeElement.focus();
    }

    if (this._languageDropdownOpen()) {
      this._languageDropdownOpen.set(false);
    }
    if (this._notificationDropdownOpen()) {
      this._notificationDropdownOpen.set(false);
    }
  }

  toggleNotifications(): void {
    const opening = !this._notificationDropdownOpen();
    this._notificationDropdownOpen.set(opening);

    if (opening) {
      setTimeout(() => {
        const firstItem = this.notificationsMenu?.nativeElement.querySelector<HTMLElement>(
          '[role="menuitem"]'
        );
        firstItem?.focus();
      });
    } else {
      this.notificationsButton?.nativeElement.focus();
    }

    if (this._languageDropdownOpen()) {
      this._languageDropdownOpen.set(false);
    }
    if (this._userDropdownOpen()) {
      this._userDropdownOpen.set(false);
    }
  }

  markAsRead(notificationId: string): void {
    const currentNotifications = this._notifications();
    const updatedNotifications = currentNotifications.map(notification =>
      notification.id === notificationId ? { ...notification, read: true } : notification
    );
    this._notifications.set(updatedNotifications);
  }

  markAllAsRead(): void {
    const currentNotifications = this._notifications();
    const updatedNotifications = currentNotifications.map(notification => ({
      ...notification,
      read: true
    }));
    this._notifications.set(updatedNotifications);
  }

  getRelativeTime(date: Date): string {
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const minutes = Math.floor(diffMs / 60000);
    const hours = Math.floor(diffMs / 3600000);
    const days = Math.floor(diffMs / 86400000);

    if (minutes < 1) return this.translationService.instant('notifications.justNow');
    if (minutes < 60) return this.translationService.instant('notifications.minutesAgo', { count: minutes });
    if (hours < 24)   return this.translationService.instant('notifications.hoursAgo', { count: hours });
    return this.translationService.instant('notifications.daysAgo', { count: days });
  }

  getUserAvatar(): string {
    // Assuming user has avatar property or we use a default
    return 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?ixlib=rb-1.2.1&ixid=eyJhcHBfaWQiOjEyMDd9&auto=format&fit=facearea&facepad=2&w=256&h=256&q=80';
  }

  getUserRole(): string {
    const permissions = this.authV5.permissions();

    // Determine role based on permissions
    if (permissions.includes('admin') || permissions.includes('super-admin')) {
      return this.translationService.instant('roles.admin');
    } else if (permissions.includes('manager') || permissions.includes('school-manager')) {
      return this.translationService.instant('roles.manager');
    } else if (permissions.includes('instructor')) {
      return this.translationService.instant('roles.instructor');
    } else if (permissions.includes('staff')) {
      return this.translationService.instant('roles.staff');
    } else {
      return this.translationService.instant('roles.user');
    }
  }

  logout(): void {
    if (this._loggingOut()) return; // Prevent multiple clicks

    this._loggingOut.set(true);
    this.authV5.logout();
    this._userDropdownOpen.set(false);
    this._loggingOut.set(false);
  }

  // Click outside handler
  @HostListener('document:click', ['$event'])
  onDocumentClick(event: Event): void {
    const target = event.target as HTMLElement;
    if (
      !target.closest('.language-menu') &&
      !target.closest('.user-menu') &&
      !target.closest('.notifications-container')
    ) {
      this.closeAll();
    }
  }

  // Keyboard navigation
  @HostListener('document:keydown', ['$event'])
  onKeyDown(event: KeyboardEvent): void {
    // Alt + S toggles sidebar
    if (event.altKey && event.key === 's') {
      event.preventDefault();
      this.ui.toggleSidebar();
    }
  }

  @HostListener('document:keydown.escape')
  closeAll(): void {
    if (this._languageDropdownOpen()) {
      this._languageDropdownOpen.set(false);
      this.languageButton?.nativeElement.focus();
    }
    if (this._userDropdownOpen()) {
      this._userDropdownOpen.set(false);
      this.userButton?.nativeElement.focus();
    }
    if (this._notificationDropdownOpen()) {
      this._notificationDropdownOpen.set(false);
      this.notificationsButton?.nativeElement.focus();
    }
  }


  protected getUserInitials(name: string): string {
    if (!name) return '';
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .slice(0, 2);
  }
}
