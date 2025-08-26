import { Injectable, signal, computed } from '@angular/core';

export interface School {
  id: number;
  name: string;
  slug?: string;
  logo?: string;
  active: boolean;
  user_role?: string;
  can_administer?: boolean;
  createdAt?: string;
  updatedAt?: string;
}

export interface Season {
  id: number;
  name: string;
  slug: string;
  startDate?: string;
  endDate?: string;
  start_date?: string;
  end_date?: string;
  active?: boolean;
  is_active?: boolean;
  is_current?: boolean;
  schoolId?: number;
  school_id?: number;
}

export interface ContextData {
  schoolId: number | null;
  seasonId: number | null;
  school?: School;
  season?: Season;
}

@Injectable({
  providedIn: 'root'
})
export class ContextService {
  
  // Private signals for state management
  private readonly _schoolId = signal<number | null>(this.getStoredSchoolId());
  private readonly _seasonId = signal<number | null>(this.getStoredSeasonId());
  private readonly _school = signal<School | null>(null);
  private readonly _season = signal<Season | null>(null);

  // Public readonly computed signals
  readonly schoolId = computed(() => this._schoolId());
  readonly seasonId = computed(() => this._seasonId());
  readonly school = computed(() => this._school());
  readonly season = computed(() => this._season());

  // Combined context computed
  readonly context = computed((): ContextData => ({
    schoolId: this._schoolId(),
    seasonId: this._seasonId(),
    school: this._school() || undefined,
    season: this._season() || undefined
  }));

  // Check if context is complete
  readonly hasCompleteContext = computed(() => 
    this._schoolId() !== null && this._seasonId() !== null
  );

  // Check if school is selected
  readonly hasSchoolSelected = computed(() => this._schoolId() !== null);

  constructor() {
    // Load stored context data on initialization
    this.loadStoredContext();
  }

  /**
   * Set the current school context
   */
  async setSchool(schoolId: number): Promise<void> {
    // Update local state
    this._schoolId.set(schoolId);
    this._seasonId.set(null); // Reset season when school changes
    this._season.set(null);

    // Store in localStorage
    localStorage.setItem('context_schoolId', schoolId.toString());
    localStorage.removeItem('context_seasonId');

    // Basic school info
    if (!this._school() || this._school()?.id !== schoolId) {
      this._school.set({ id: schoolId } as School);
    }
  }

  /**
   * Set the current season context
   */
  async setSeason(seasonId: number): Promise<void> {
    // Update local state
    this._seasonId.set(seasonId);

    // Store in localStorage
    localStorage.setItem('context_seasonId', seasonId.toString());

    // Basic season info
    if (!this._season() || this._season()?.id !== seasonId) {
      this._season.set({ id: seasonId } as Season);
    }
  }

  /**
   * Clear all context
   */
  clearContext(): void {
    this._schoolId.set(null);
    this._seasonId.set(null);
    this._school.set(null);
    this._season.set(null);
    
    localStorage.removeItem('context_schoolId');
    localStorage.removeItem('context_seasonId');
  }

  private async loadSchoolDetails(_schoolId: number): Promise<void> {
    // Placeholder for API-based implementation
  }

  private async loadSeasonDetails(_seasonId: number): Promise<void> {
    // Placeholder for API-based implementation
  }

  /**
   * Get stored school ID from localStorage
   */
  private getStoredSchoolId(): number | null {
    const stored = localStorage.getItem('context_schoolId');
    return stored ? parseInt(stored, 10) : null;
  }

  /**
   * Get stored season ID from localStorage
   */
  private getStoredSeasonId(): number | null {
    const stored = localStorage.getItem('context_seasonId');
    return stored ? parseInt(stored, 10) : null;
  }

  /**
   * Load stored context on service initialization
   */
  private async loadStoredContext(): Promise<void> {
    const schoolId = this._schoolId();
    const seasonId = this._seasonId();

    if (schoolId) {
      this._school.set({ id: schoolId } as School);
    }

    if (seasonId) {
      this._season.set({ id: seasonId } as Season);
    }
  }

  /**
   * Get selected school ID
   */
  getSelectedSchoolId(): number | null {
    return this._schoolId();
  }

  /**
   * Get selected season ID
   */
  getSelectedSeasonId(): number | null {
    return this._seasonId();
  }

  /**
   * Set selected season (simpler interface for Select Season page)
   */
  setSelectedSeason(season: Partial<Season>): void {
    if (season.id) {
      this._seasonId.set(season.id);
      this._season.set(season as Season);
      localStorage.setItem('context_seasonId', season.id.toString());
    }
  }

  /**
   * Set selected school (simpler interface for Select School page)
   */
  setSelectedSchool(school: Partial<School>): void {
    if (school.id) {
      this._schoolId.set(school.id);
      this._school.set(school as School);
      localStorage.setItem('context_schoolId', school.id.toString());
    }
  }

  /**
   * Check if user needs to select school
   */
  needsSchoolSelection(): boolean {
    return this._schoolId() === null;
  }

  /**
   * Check if user needs to select season
   */
  needsSeasonSelection(): boolean {
    return this._schoolId() !== null && this._seasonId() === null;
  }

  /**
   * Validate current context with server
   */
  async validateContext(): Promise<boolean> {
    try {
      if (!this.hasCompleteContext()) {
        return false;
      }
      return true;
    } catch {
      this.clearContext();
      return false;
    }
  }
}