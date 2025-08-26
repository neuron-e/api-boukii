import { Component, OnInit, OnDestroy, inject, signal, computed } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { Subject, takeUntil, debounceTime, distinctUntilChanged } from 'rxjs';

import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { AuthV5Service } from '@core/services/auth-v5.service';
import { ToastService } from '@core/services/toast.service';
import { TranslationService } from '@core/services/translation.service';
import { SessionService } from '@core/services/session.service';
import { SchoolService } from '@core/services/school.service';
import { School } from '@core/services/context.service';

@Component({
  selector: 'app-select-school',
  standalone: true,
  imports: [CommonModule, FormsModule, TranslatePipe],
  template: `
    <div class="select-school-page" data-cy="school-selection">
      <!-- Breadcrumb -->
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <span class="breadcrumb-text">{{ 'schools.selectSchool.breadcrumb' | translate }}</span>
      </nav>

      <!-- Page Header -->
      <header class="page-header">
        <h1 class="page-title">{{ 'schools.selectSchool.title' | translate }}</h1>
        <p class="page-subtitle">{{ 'schools.selectSchool.subtitle' | translate }}</p>
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
            [placeholder]="'schools.selectSchool.searchPlaceholder' | translate"
            [(ngModel)]="searchQuery"
            (input)="onSearchInput()"
            [attr.aria-label]="'schools.selectSchool.searchPlaceholder' | translate"
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
          <div class="loading-state" [attr.aria-label]="'schools.selectSchool.loadingSchools' | translate">
            <div class="loading-spinner">
              <div class="spinner large"></div>
            </div>
            <p class="loading-text">{{ 'schools.selectSchool.loadingSchools' | translate }}</p>
          </div>
        } @else if (hasError()) {
          <!-- Error State -->
          <div class="error-state" role="alert">
            <svg class="error-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" [attr.stroke-width]="'2'">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="15" y1="9" x2="9" y2="15"></line>
              <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>
            <h2 class="error-title">{{ 'schools.selectSchool.errorLoading' | translate }}</h2>
            <p class="error-message">{{ errorMessage() || ('common.error' | translate) }}</p>
            <button class="retry-button" (click)="loadSchools()" [disabled]="isLoading()">
              {{ 'common.retry' | translate }}
            </button>
          </div>
        } @else if (filteredSchools().length === 0) {
          <!-- Empty State -->
          <div class="empty-state">
            <svg class="empty-icon" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" [attr.stroke-width]="'2'">
              <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
            </svg>
            <h2 class="empty-title">{{ 'schools.selectSchool.noSchools' | translate }}</h2>
            <p class="empty-message">
              @if (searchQuery.trim()) {
                {{ 'common.noResults' | translate }}
              } @else {
                {{ 'schools.selectSchool.noSchoolsMessage' | translate }}
              }
            </p>
          </div>
        } @else {
          <!-- Schools Grid -->
          <div class="schools-grid">
            @for (school of filteredSchools(); track school.id) {
              <article class="school-card" data-testid="school-card" data-cy="school-item" [class.selecting]="isSelecting() === school.id">
                <div class="school-header">
                  <h3 class="school-name">{{ school.name }}</h3>
                  @if (school.slug) {
                    <span class="school-slug">{{ school.slug }}</span>
                  }
                </div>

                <div class="school-status">
                  <span 
                    class="status-badge" 
                    [class.active]="school.active"
                    [class.inactive]="!school.active"
                  >
                    {{ school.active ? ('schools.status.active' | translate) : ('schools.status.inactive' | translate) }}
                  </span>
                </div>

                <div class="school-actions">
                  <button
                    class="select-school-button"
                    [class.primary]="school.active"
                    [class.secondary]="!school.active"
                    [disabled]="isSelecting() !== null || !school.active"
                    (click)="selectSchool(school)"
                  >
                    @if (isSelecting() === school.id) {
                      <div class="button-spinner">
                        <div class="spinner small"></div>
                      </div>
                    }
                    <span [class.hidden]="isSelecting() === school.id">
                      {{ 'schools.selectSchool.useThisSchool' | translate }}
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
  styleUrl: './select-school.styles.scss'
})
export class SelectSchoolPageComponent implements OnInit, OnDestroy {
  private readonly destroy$ = new Subject<void>();
  private readonly authV5 = inject(AuthV5Service);
  private readonly toast = inject(ToastService);
  private readonly translationService = inject(TranslationService);
  private readonly router = inject(Router);
  private readonly sessionService = inject(SessionService);
  private readonly schoolService = inject(SchoolService);

  // Component state
  private readonly _isLoading = signal(false);
  private readonly _isSearching = signal(false);
  private readonly _isSelecting = signal<number | null>(null);
  private readonly _hasError = signal(false);
  private readonly _errorMessage = signal<string | null>(null);
  private readonly _schools = signal<School[]>([]);

  // Search state
  searchQuery = '';
  private searchSubject = new Subject<string>();
  private tempToken = '';

  // Public computed signals
  readonly isLoading = computed(() => this._isLoading());
  readonly isSearching = computed(() => this._isSearching());
  readonly isSelecting = computed(() => this._isSelecting());
  readonly hasError = computed(() => this._hasError());
  readonly errorMessage = computed(() => this._errorMessage());
  readonly schools = computed(() => this._schools());
  
  // Computed filtered schools based on search
  readonly filteredSchools = computed(() => {
    // For super admins, filtering is handled server-side
    if (this.authV5.isSuperAdmin()) {
      return this.schools();
    }

    const query = this.searchQuery.toLowerCase().trim();
    if (!query) return this.schools();

    return this.schools().filter(school =>
      school.name.toLowerCase().includes(query) ||
      (school.slug && school.slug.toLowerCase().includes(query))
    );
  });

  ngOnInit(): void {
    this.setupSearch();
    this.loadSchools();
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
        if (this.authV5.isSuperAdmin()) {
          // Trigger API search for super admins
          this.loadSchools(1);
        } else {
          // For stored schools, we just filter on the client side
          this._isSearching.set(false);
        }
      });
  }

  onSearchInput(): void {
    this._isSearching.set(true);
    this.searchSubject.next(this.searchQuery);
  }

  private currentPage = 1;
  private lastPage = 1;
  private readonly perPage = 20;

  loadSchools(page: number = 1, append: boolean = false): void {
    this._isLoading.set(true);
    this._hasError.set(false);
    this._errorMessage.set(null);

    this.tempToken = this.authV5.tokenSignal() || '';

    if (this.authV5.isSuperAdmin()) {
      this.schoolService
        .listAll({
          page,
          perPage: this.perPage,
          search: this.searchQuery
        })
        .pipe(takeUntil(this.destroy$))
        .subscribe({
          next: (response) => {
            this.currentPage = response.meta.page;
            this.lastPage = response.meta.lastPage;
            if (append) {
              this._schools.update(prev => [...prev, ...response.data]);
            } else {
              this._schools.set(response.data);
            }
            this._isLoading.set(false);
            this._isSearching.set(false);
          },
          error: (error) => {
            console.error('Error loading schools from API:', error);
            this._hasError.set(true);
            this._errorMessage.set('Error loading schools data');
            this._isLoading.set(false);
            this._isSearching.set(false);
          }
        });
      return;
    }

    try {
      const user: any = this.authV5.user();
      const schools = user?.schools || [];
      if (schools.length === 0) {
        this._hasError.set(true);
        this._errorMessage.set('No schools found. Please login again.');
        this._isLoading.set(false);
        return;
      }
      if (append) {
        this._schools.update(prev => [...prev, ...schools]);
      } else {
        this._schools.set(schools);
      }
    } catch (error) {
      console.error('Error loading schools from auth service:', error);
      this._hasError.set(true);
      this._errorMessage.set('Error loading schools data');
      this.router.navigate(['/auth/login']);
    } finally {
      this._isLoading.set(false);
      this._isSearching.set(false);
    }
  }

  loadMore(): void {
    if (this.currentPage < this.lastPage) {
      this.loadSchools(this.currentPage + 1, true);
    }
  }

  selectSchool(school: School): void {
    if (this._isSelecting() !== null) {
      return;
    }

    this._isSelecting.set(school.id);
    console.log('🏫 Selecting school:', school.name, 'with token:', this.tempToken.substring(0, 20) + '...');

    this.authV5.selectSchool(school.id, this.tempToken).subscribe({
      next: (response) => {
        if (!response.success || !response.data) {
          this.handleSelectionError('School selection failed');
          return;
        }

        console.log('✅ School selection successful:', response.data);

        // Persist selected school using session service
        this.sessionService.selectSchool(school);

        // Show success message
        this.toast.success(this.translationService.get('auth.login.success'));

        // Navigate to select season
        this.router.navigate(['/select-season']);
      },
      error: (error) => {
        console.error('❌ School selection failed:', error);
        this.handleSelectionError(`School selection failed: ${error.message}`);
      }
    });
  }

  private handleSelectionError(message: string): void {
    this._isSelecting.set(null);
    this._hasError.set(true);
    this._errorMessage.set(message);
    this.toast.error(message);
  }

}