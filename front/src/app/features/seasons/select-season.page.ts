import { Component, OnInit, OnDestroy, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject, takeUntil, debounceTime, distinctUntilChanged, catchError, of } from 'rxjs';
import { switchMap } from 'rxjs/operators';

import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { ContextService } from '@core/services/context.service';
import { ToastService } from '@core/services/toast.service';
import { TranslationService } from '@core/services/translation.service';
import { AuthErrorHandlerService } from '@core/services/auth-error-handler.service';

interface Season {
  id: number;
  name: string;
  slug: string;
  description?: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
  is_current: boolean;
  school_id: number;
  course_count?: number;
  booking_count?: number;
  can_manage?: boolean;
}

@Component({
  selector: 'app-select-season',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe],
  template: `
    <div class="select-season-page" data-cy="season-selection">
      <!-- Breadcrumb -->
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <span class="breadcrumb-text">{{ 'seasons.selectSeason.breadcrumb' | translate }}</span>
      </nav>

      <!-- Page Header -->
      <header class="page-header">
        <h1 class="page-title">{{ 'seasons.selectSeason.title' | translate }}</h1>
        <p class="page-subtitle">{{ 'seasons.selectSeason.subtitle' | translate }}</p>
      </header>

      <!-- Search Bar -->
      <div class="search-section">
        <div class="search-input-wrapper">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" [attr.stroke-width]="'2'">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
          </svg>
          <input
            type="text"
            class="search-input"
            [placeholder]="'seasons.selectSeason.searchPlaceholder' | translate"
            [(ngModel)]="searchQuery"
            (input)="onSearchInput()"
            [attr.aria-label]="'seasons.selectSeason.searchPlaceholder' | translate"
          />
          @if (isSearching()) {
            <div class="search-spinner">
              <div class="spinner"></div>
            </div>
          }
        </div>
      </div>

      <!-- Content Area -->
      <main class="content-area" role="main">
        @if (isLoading() && !isSearching()) {
          <!-- Loading State -->
          <div class="loading-state" [attr.aria-label]="'seasons.selectSeason.loadingSeasons' | translate">
            <div class="loading-spinner">
              <div class="spinner large"></div>
            </div>
            <p class="loading-text">{{ 'seasons.selectSeason.loadingSeasons' | translate }}</p>
          </div>
        } @else if (hasError()) {
          <!-- Error State -->
          <div class="error-state" role="alert">
            <svg class="error-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" [attr.stroke-width]="'2'">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <h2 class="error-title">{{ 'seasons.selectSeason.errorLoading' | translate }}</h2>
            <p class="error-message">{{ errorMessage() || ('common.error' | translate) }}</p>
            <button class="retry-button" (click)="loadSeasons()" [disabled]="isLoading()">
              {{ 'common.retry' | translate }}
            </button>
          </div>
        } @else if (filteredSeasons().length === 0) {
          <!-- Empty State -->
          <div class="empty-state">
            <svg class="empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" [attr.stroke-width]="'2'">
              <rect width="18" height="18" x="3" y="4" rx="2" ry="2"></rect>
              <line x1="16" y1="2" x2="16" y2="6"></line>
              <line x1="8" y1="2" x2="8" y2="6"></line>
              <line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <h2 class="empty-title">{{ 'seasons.selectSeason.noSeasons' | translate }}</h2>
            <p class="empty-message">
              @if (searchQuery.trim()) {
                {{ 'common.noResults' | translate }}
              } @else {
                {{ 'seasons.selectSeason.noSeasonsMessage' | translate }}
              }
            </p>
          </div>
        } @else {
          <!-- Seasons Grid -->
          <div class="seasons-grid">
            @for (season of filteredSeasons(); track season.id) {
              <article class="season-card" 
                data-testid="season-card" 
                data-cy="season-item" 
                [class.selecting]="isSelecting() === season.id"
                [class.current]="season.is_current"
                [class.active]="season.is_active">
                
                <div class="season-header">
                  <div class="season-main-info">
                    <h3 class="season-name">{{ season.name }}</h3>
                    <span class="season-slug">{{ season.slug }}</span>
                  </div>
                  
                  <div class="season-badges">
                    @if (season.is_current) {
                      <span class="badge badge-current">
                        {{ 'seasons.status.current' | translate }}
                      </span>
                    }
                    <span 
                      class="badge badge-status"
                      [class.badge-active]="season.is_active"
                      [class.badge-inactive]="!season.is_active"
                    >
                      {{ season.is_active ? ('seasons.status.active' | translate) : ('seasons.status.inactive' | translate) }}
                    </span>
                  </div>
                </div>

                @if (season.description) {
                  <div class="season-description">
                    <p>{{ season.description }}</p>
                  </div>
                }

                <div class="season-details">
                  <div class="season-dates">
                    <div class="date-item">
                      <span class="date-label">{{ 'seasons.startDate' | translate }}</span>
                      <span class="date-value">{{ formatDate(season.start_date) }}</span>
                    </div>
                    <div class="date-item">
                      <span class="date-label">{{ 'seasons.endDate' | translate }}</span>
                      <span class="date-value">{{ formatDate(season.end_date) }}</span>
                    </div>
                  </div>

                  @if (season.course_count !== undefined || season.booking_count !== undefined) {
                    <div class="season-stats">
                      @if (season.course_count !== undefined) {
                        <div class="stat-item">
                          <span class="stat-value">{{ season.course_count }}</span>
                          <span class="stat-label">{{ 'seasons.courses' | translate }}</span>
                        </div>
                      }
                      @if (season.booking_count !== undefined) {
                        <div class="stat-item">
                          <span class="stat-value">{{ season.booking_count }}</span>
                          <span class="stat-label">{{ 'seasons.bookings' | translate }}</span>
                        </div>
                      }
                    </div>
                  }
                </div>

                <div class="season-actions">
                  <button
                    class="select-season-button"
                    [class.primary]="season.is_active"
                    [class.secondary]="!season.is_active"
                    [disabled]="isSelecting() !== null || !season.is_active"
                    (click)="selectSeason(season)"
                  >
                    @if (isSelecting() === season.id) {
                      <div class="button-spinner">
                        <div class="spinner small"></div>
                      </div>
                    }
                    <span [class.hidden]="isSelecting() === season.id">
                      {{ 'seasons.selectSeason.useThisSeason' | translate }}
                    </span>
                  </button>
                </div>
              </article>
            }
          </div>
        }
      </main>
    </div>
  `,
  styleUrl: './select-season.styles.scss'
})
export class SelectSeasonPageComponent implements OnInit, OnDestroy {
  private readonly destroy$ = new Subject<void>();
  private readonly authV5 = inject(AuthV5Service);
  private readonly context = inject(ContextService);
  private readonly toast = inject(ToastService);
  private readonly translationService = inject(TranslationService);
  private readonly router = inject(Router);
  private readonly errorHandler = inject(AuthErrorHandlerService);

  // Component state
  private readonly _isLoading = signal(false);
  private readonly _isSearching = signal(false);
  private readonly _isSelecting = signal<number | null>(null);
  private readonly _hasError = signal(false);
  private readonly _errorMessage = signal<string | null>(null);
  private readonly _seasons = signal<Season[]>([]);

  // Search state
  searchQuery = '';
  private searchSubject = new Subject<string>();
  private selectedSchoolId: number | null = null;

  // Public computed signals
  readonly isLoading = computed(() => this._isLoading());
  readonly isSearching = computed(() => this._isSearching());
  readonly isSelecting = computed(() => this._isSelecting());
  readonly hasError = computed(() => this._hasError());
  readonly errorMessage = computed(() => this._errorMessage());
  readonly seasons = computed(() => this._seasons());
  
  // Computed filtered seasons based on search
  readonly filteredSeasons = computed(() => {
    const query = this.searchQuery.toLowerCase().trim();
    if (!query) return this.seasons();
    
    return this.seasons().filter(season =>
      season.name.toLowerCase().includes(query) ||
      season.slug.toLowerCase().includes(query) ||
      (season.description && season.description.toLowerCase().includes(query))
    );
  });

  ngOnInit(): void {
    this.setupSearch();
    this.initializeSeasonSelection();
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }

  private setupSearch(): void {
    this.searchSubject
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        takeUntil(this.destroy$)
      )
      .subscribe(() => {
        // For seasons, we filter on the client side after loading
        this._isSearching.set(false);
      });
  }

  private initializeSeasonSelection(): void {
    // Get selected school ID from context
    this.selectedSchoolId = this.context.getSelectedSchoolId();
    
    if (!this.selectedSchoolId) {
      this.handleError('seasons.selectSeason.noSchoolSelected', '/select-school');
      return;
    }

    // Check if user is authenticated
    if (!this.authV5.isAuthenticated()) {
      this.handleError('auth.sessionExpired', '/auth/login');
      return;
    }

    this.loadSeasons();
  }

  onSearchInput(): void {
    this._isSearching.set(true);
    this.searchSubject.next(this.searchQuery);
  }

  loadSeasons(): void {
    if (!this.selectedSchoolId) {
      this.handleError('seasons.selectSeason.noSchoolSelected', '/select-school');
      return;
    }

    this._isLoading.set(true);
    this._hasError.set(false);
    this._errorMessage.set(null);

    this.authV5.getSeasons(this.selectedSchoolId).pipe(
      catchError(error => {
        console.error('❌ Error loading seasons:', error);
        this.handleApiError(error);
        return of([]);
      }),
      takeUntil(this.destroy$)
    ).subscribe({
      next: (seasons) => {
        console.log('📅 Seasons loaded:', seasons);
        this._seasons.set(seasons);
        this._isLoading.set(false);
        
        // If there's only one active season, auto-select it
        const activeSeasons = seasons.filter(s => s.is_active);
        if (activeSeasons.length === 1) {
          console.log('🎯 Auto-selecting single active season:', activeSeasons[0].name);
          setTimeout(() => this.selectSeason(activeSeasons[0]), 500);
        }
      },
      error: (error) => {
        console.error('❌ Error in seasons subscription:', error);
        const authError = this.errorHandler.handleSeasonSelectionError(error);
        this._isLoading.set(false);
        this._hasError.set(true);
        this._errorMessage.set(authError.message);
      }
    });
  }

  selectSeason(season: Season): void {
    if (this._isSelecting() !== null) {
      return;
    }

    if (!season.is_active) {
      this.toast.warning(this.translationService.get('seasons.selectSeason.inactiveSeason'));
      return;
    }

    this._isSelecting.set(season.id);
    console.log('📅 Selecting season:', season.name, 'for school:', this.selectedSchoolId);

    // Ensure AuthV5Service has the current school set before selecting season
    const ensureSchool$ = this.selectedSchoolId
      ? this.authV5.setCurrentSchool(this.selectedSchoolId)
      : of(null);

    ensureSchool$
      .pipe(
        switchMap(() => this.authV5.selectSeason(season.id)),
        catchError(error => {
          console.error('❌ Season selection failed:', error);
          this.handleSelectionError(error);
          return of(null);
        }),
        takeUntil(this.destroy$)
      )
      .subscribe({
        next: (response) => {
          if (!response) return;

        console.log('✅ Season selection successful:', response);

        // Update context with selected season
        this.context.setSelectedSeason({
          id: season.id,
          name: season.name,
          slug: season.slug,
          is_current: season.is_current
        });

        // Show success message
        this.toast.success(
          this.translationService.get('seasons.selectSeason.success', { seasonName: season.name })
        );
        
        // Navigate to dashboard
        this.router.navigate(['/dashboard']);
      },
      error: (error) => {
        console.error('❌ Season selection subscription error:', error);
        const authError = this.errorHandler.handleSeasonSelectionError(error);
        this._isSelecting.set(null);
        this._hasError.set(true);
        this._errorMessage.set(authError.message);
      }
    });
  }

  formatDate(dateStr: string): string {
    try {
      const date = new Date(dateStr);
      return new Intl.DateTimeFormat(this.translationService.currentLanguage(), {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      }).format(date);
    } catch {
      return dateStr;
    }
  }

  private handleError(translationKey: string, redirectPath?: string): void {
    this._hasError.set(true);
    this._errorMessage.set(this.translationService.get(translationKey));
    
    if (redirectPath) {
      setTimeout(() => {
        this.router.navigate([redirectPath]);
      }, 2000);
    }
  }

  private handleApiError(error: any): void {
    this._isLoading.set(false);
    this._isSelecting.set(null);
    
    let errorMessage = 'common.error';
    
    if (error.status === 401) {
      errorMessage = 'auth.sessionExpired';
      setTimeout(() => this.router.navigate(['/auth/login']), 2000);
    } else if (error.status === 403) {
      errorMessage = 'seasons.selectSeason.noPermission';
    } else if (error.status === 404) {
      errorMessage = 'seasons.selectSeason.notFound';
    }
    
    this._hasError.set(true);
    this._errorMessage.set(this.translationService.get(errorMessage));
    this.toast.error(this.translationService.get(errorMessage));
  }

  private handleSelectionError(error: any): void {
    this._isSelecting.set(null);
    
    const errorMessage = error?.message || this.translationService.get('seasons.selectSeason.selectionFailed');
    this._hasError.set(true);
    this._errorMessage.set(errorMessage);
    this.toast.error(errorMessage);
  }
}
