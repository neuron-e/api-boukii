import { Component, inject, signal, computed, Input, Output, EventEmitter, OnInit, OnChanges, SimpleChanges, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, FormArray, Validators } from '@angular/forms';
import { catchError, finalize, debounceTime, distinctUntilChanged } from 'rxjs/operators';
import { of } from 'rxjs';
import { TranslatePipe } from '../../../../../shared/pipes/translate.pipe';
import { SchoolPermissionsService } from '../../services/school-permissions.service';
import { UsersService } from '../../../users/services/users.service';
import { RolesService } from '../../../roles/services/roles.service';
import { 
  BulkPermissionAssignment,
  School
} from '../../types/school-permissions.types';
import { Role } from '../../../roles/types/role.types';
import { UserListItem } from '../../../users/types/user.types';

@Component({
  selector: 'app-bulk-assignment-modal',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  template: `
    <div class="modal-overlay" *ngIf="isOpen" (click)="closeModal()" data-testid="bulk-assignment-modal">
      <div class="modal-content" (click)="$event.stopPropagation()">
        <div class="modal-header">
          <h2>{{ 'permissions.bulk.title' | translate }}</h2>
          <button class="close-btn" (click)="closeModal()" aria-label="Close">
            <i class="icon-x"></i>
          </button>
        </div>

        <div class="modal-body">
          <form [formGroup]="bulkForm" (ngSubmit)="executeBulkAssignment()">
            
            <!-- Step 1: Select Users -->
            <div class="form-section">
              <h3>
                <span class="step-number">1</span>
                {{ 'permissions.bulk.selectUsers' | translate }}
              </h3>
              <p class="section-description">{{ 'permissions.bulk.selectUsersDescription' | translate }}</p>
              
              <!-- User Search -->
              <div class="user-search">
                <input 
                  type="text" 
                  [formControl]="userSearchControl"
                  [placeholder]="'permissions.bulk.searchUsers' | translate"
                  class="form-input"
                  data-testid="user-search"
                />
              </div>

              <!-- User Selection Mode -->
              <div class="selection-mode">
                <div class="mode-toggle">
                  <label class="radio-option">
                    <input type="radio" value="manual" formControlName="selectionMode" />
                    <span class="radio-custom"></span>
                    <span class="radio-label">{{ 'permissions.bulk.manualSelection' | translate }}</span>
                  </label>
                  <label class="radio-option">
                    <input type="radio" value="filter" formControlName="selectionMode" />
                    <span class="radio-custom"></span>
                    <span class="radio-label">{{ 'permissions.bulk.filterSelection' | translate }}</span>
                  </label>
                </div>
              </div>

              <!-- Manual User Selection -->
              <div *ngIf="bulkForm.get('selectionMode')?.value === 'manual'" class="manual-selection">
                <div class="users-grid" *ngIf="!loadingUsers()">
                  <div 
                    *ngFor="let user of filteredUsers(); trackBy: trackByUserId" 
                    class="user-card"
                    [class.selected]="isUserSelected(user.id)"
                  >
                    <label class="user-checkbox">
                      <input 
                        type="checkbox" 
                        [value]="user.id"
                        [checked]="isUserSelected(user.id)"
                        (change)="toggleUser(user.id, $event)"
                        [attr.data-testid]="'user-select-' + user.id"
                      />
                      <div class="checkbox-custom"></div>
                      <div class="user-info">
                        <div class="user-avatar">{{ getInitials(user.name) }}</div>
                        <div class="user-details">
                          <div class="user-name">{{ user.name }}</div>
                          <div class="user-email">{{ user.email }}</div>
                          <div class="user-roles">
                            <span *ngFor="let role of user.roles" class="role-badge">{{ role }}</span>
                          </div>
                        </div>
                      </div>
                    </label>
                  </div>
                </div>

                <div *ngIf="loadingUsers()" class="loading-users">
                  <div class="spinner"></div>
                  {{ 'common.loading' | translate }}
                </div>

                <div *ngIf="!loadingUsers() && filteredUsers().length === 0" class="no-users">
                  <i class="icon-users"></i>
                  <p>{{ 'permissions.bulk.noUsers' | translate }}</p>
                </div>
              </div>

              <!-- Filter-based Selection -->
              <div *ngIf="bulkForm.get('selectionMode')?.value === 'filter'" class="filter-selection">
                <div class="filter-options">
                  <div class="filter-group">
                    <label>{{ 'permissions.bulk.filterByRole' | translate }}</label>
                    <select formControlName="filterRole" class="form-select">
                      <option value="">{{ 'permissions.bulk.allRoles' | translate }}</option>
                      <option *ngFor="let role of availableRoles()" [value]="role.name">
                        {{ role.description }}
                      </option>
                    </select>
                  </div>
                  
                  <div class="filter-group">
                    <label>{{ 'permissions.bulk.filterByStatus' | translate }}</label>
                    <select formControlName="filterStatus" class="form-select">
                      <option value="">{{ 'permissions.bulk.allStatuses' | translate }}</option>
                      <option value="active">{{ 'users.status.active' | translate }}</option>
                      <option value="inactive">{{ 'users.status.inactive' | translate }}</option>
                      <option value="pending">{{ 'users.status.pending' | translate }}</option>
                    </select>
                  </div>
                </div>

                <div class="filter-preview">
                  <div class="preview-info">
                    <i class="icon-info"></i>
                    <span>{{ selectedUserCount() }} {{ 'permissions.bulk.usersWillBeSelected' | translate }}</span>
                  </div>
                </div>
              </div>

              <div class="selected-count" *ngIf="selectedUserCount() > 0">
                <strong>{{ selectedUserCount() }}</strong> {{ 'permissions.bulk.usersSelected' | translate }}
              </div>
            </div>

            <!-- Step 2: Select School -->
            <div class="form-section">
              <h3>
                <span class="step-number">2</span>
                {{ 'permissions.bulk.selectSchool' | translate }}
              </h3>
              <p class="section-description">{{ 'permissions.bulk.selectSchoolDescription' | translate }}</p>
              
              <div class="school-selection">
                <select formControlName="schoolId" class="form-select" data-testid="school-select">
                  <option value="">{{ 'permissions.bulk.chooseSchool' | translate }}</option>
                  <option *ngFor="let school of availableSchools()" [value]="school.id">
                    {{ school.name }} ({{ school.code }})
                  </option>
                </select>
              </div>
            </div>

            <!-- Step 3: Configure Roles & Permissions -->
            <div class="form-section">
              <h3>
                <span class="step-number">3</span>
                {{ 'permissions.bulk.configureRoles' | translate }}
              </h3>
              <p class="section-description">{{ 'permissions.bulk.configureRolesDescription' | translate }}</p>
              
              <!-- Assignment Action -->
              <div class="assignment-action">
                <div class="action-toggle">
                  <label class="radio-option">
                    <input type="radio" value="assign" formControlName="action" />
                    <span class="radio-custom"></span>
                    <span class="radio-label">{{ 'permissions.bulk.assignRoles' | translate }}</span>
                  </label>
                  <label class="radio-option">
                    <input type="radio" value="replace" formControlName="action" />
                    <span class="radio-custom"></span>
                    <span class="radio-label">{{ 'permissions.bulk.replaceRoles' | translate }}</span>
                  </label>
                  <label class="radio-option">
                    <input type="radio" value="remove" formControlName="action" />
                    <span class="radio-custom"></span>
                    <span class="radio-label">{{ 'permissions.bulk.removeRoles' | translate }}</span>
                  </label>
                </div>
              </div>

              <!-- Role Selection -->
              <div class="roles-selection" *ngIf="bulkForm.get('action')?.value !== 'remove'">
                <div class="roles-grid">
                  <div 
                    *ngFor="let role of availableRoles(); trackBy: trackByRoleId" 
                    class="role-option"
                    [class.selected]="isRoleSelectedForBulk(role.name)"
                  >
                    <label class="role-checkbox">
                      <input 
                        type="checkbox" 
                        [value]="role.name"
                        [checked]="isRoleSelectedForBulk(role.name)"
                        (change)="toggleBulkRole(role.name, $event)"
                        [attr.data-testid]="'bulk-role-' + role.name"
                      />
                      <div class="checkbox-custom"></div>
                      <div class="role-info">
                        <div class="role-name">{{ role.description }}</div>
                        <div class="role-identifier">{{ role.name }}</div>
                        <div class="role-permissions-count">
                          {{ role.permissions.length }} {{ 'permissions.modal.permissions' | translate }}
                        </div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Remove Action Role Selection -->
              <div class="roles-selection" *ngIf="bulkForm.get('action')?.value === 'remove'">
                <p class="warning-text">
                  <i class="icon-alert-triangle"></i>
                  {{ 'permissions.bulk.removeWarning' | translate }}
                </p>
                <div class="roles-grid">
                  <div 
                    *ngFor="let role of availableRoles(); trackBy: trackByRoleId" 
                    class="role-option remove-mode"
                    [class.selected]="isRoleSelectedForBulk(role.name)"
                  >
                    <label class="role-checkbox">
                      <input 
                        type="checkbox" 
                        [value]="role.name"
                        [checked]="isRoleSelectedForBulk(role.name)"
                        (change)="toggleBulkRole(role.name, $event)"
                        [attr.data-testid]="'bulk-remove-role-' + role.name"
                      />
                      <div class="checkbox-custom"></div>
                      <div class="role-info">
                        <div class="role-name">{{ role.description }}</div>
                        <div class="role-identifier">{{ role.name }}</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Step 4: Date Range (Optional) -->
            <div class="form-section">
              <h3>
                <span class="step-number">4</span>
                {{ 'permissions.bulk.dateRange' | translate }}
                <span class="optional-label">({{ 'common.optional' | translate }})</span>
              </h3>
              <p class="section-description">{{ 'permissions.bulk.dateRangeDescription' | translate }}</p>
              
              <div class="date-inputs">
                <div class="date-field">
                  <label for="bulkStartDate">{{ 'permissions.modal.startDate' | translate }}</label>
                  <input 
                    id="bulkStartDate"
                    type="date" 
                    formControlName="startDate"
                    class="form-input"
                  />
                </div>
                <div class="date-field">
                  <label for="bulkEndDate">{{ 'permissions.modal.endDate' | translate }}</label>
                  <input 
                    id="bulkEndDate"
                    type="date" 
                    formControlName="endDate"
                    class="form-input"
                  />
                  <small class="field-hint">{{ 'permissions.modal.endDateHint' | translate }}</small>
                </div>
              </div>
            </div>
          </form>

          <!-- Preview Section -->
          <div class="preview-section" *ngIf="canShowPreview()">
            <h3>{{ 'permissions.bulk.preview' | translate }}</h3>
            <div class="preview-summary">
              <div class="summary-item">
                <i class="icon-users"></i>
                <span><strong>{{ selectedUserCount() }}</strong> {{ 'permissions.bulk.users' | translate }}</span>
              </div>
              <div class="summary-item">
                <i class="icon-building"></i>
                <span><strong>{{ getSelectedSchoolName() }}</strong></span>
              </div>
              <div class="summary-item">
                <i class="icon-shield"></i>
                <span><strong>{{ selectedRolesForBulk().length }}</strong> {{ 'permissions.bulk.roles' | translate }}</span>
              </div>
              <div class="summary-item">
                <i class="icon-calendar"></i>
                <span>{{ getDateRangeText() }}</span>
              </div>
            </div>

            <div class="preview-actions">
              <div class="action-description" [ngSwitch]="bulkForm.get('action')?.value">
                <div *ngSwitchCase="'assign'">
                  <i class="icon-plus-circle"></i>
                  {{ 'permissions.bulk.willAssignRoles' | translate }}
                </div>
                <div *ngSwitchCase="'replace'">
                  <i class="icon-refresh-cw"></i>
                  {{ 'permissions.bulk.willReplaceRoles' | translate }}
                </div>
                <div *ngSwitchCase="'remove'">
                  <i class="icon-minus-circle"></i>
                  {{ 'permissions.bulk.willRemoveRoles' | translate }}
                </div>
              </div>
            </div>
          </div>

          <!-- Progress Section -->
          <div class="progress-section" *ngIf="executing()">
            <div class="progress-header">
              <h3>{{ 'permissions.bulk.executing' | translate }}</h3>
              <div class="progress-stats">
                {{ executionProgress().processed }} / {{ executionProgress().total }}
              </div>
            </div>
            <div class="progress-bar">
              <div 
                class="progress-fill" 
                [style.width.%]="getProgressPercentage()"
              ></div>
            </div>
            <div class="progress-details" *ngIf="executionProgress().errors.length > 0">
              <p class="error-count">{{ executionProgress().errors.length }} {{ 'permissions.bulk.errors' | translate }}</p>
            </div>
          </div>

          <!-- Results Section -->
          <div class="results-section" *ngIf="executionResults()">
            <div class="results-summary" [class.has-errors]="executionResults()!.failed > 0">
              <div class="result-item success">
                <i class="icon-check-circle"></i>
                <span><strong>{{ executionResults()!.successful }}</strong> {{ 'permissions.bulk.successful' | translate }}</span>
              </div>
              <div class="result-item error" *ngIf="executionResults()!.failed > 0">
                <i class="icon-x-circle"></i>
                <span><strong>{{ executionResults()!.failed }}</strong> {{ 'permissions.bulk.failed' | translate }}</span>
              </div>
            </div>

            <div class="error-details" *ngIf="executionResults()!.errors.length > 0">
              <h4>{{ 'permissions.bulk.errorDetails' | translate }}</h4>
              <ul class="errors-list">
                <li *ngFor="let error of executionResults()!.errors" class="error-item">
                  {{ error }}
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button 
            type="button" 
            class="btn btn-outline"
            (click)="closeModal()"
            [disabled]="executing()"
          >
            {{ executionResults() ? ('common.close' | translate) : ('common.cancel' | translate) }}
          </button>
          
          <button 
            type="button"
            class="btn btn-warning"
            (click)="resetForm()"
            [disabled]="executing()"
            *ngIf="!executionResults()"
          >
            <i class="icon-refresh-ccw"></i>
            {{ 'permissions.bulk.reset' | translate }}
          </button>
          
          <button 
            type="button"
            class="btn btn-primary"
            (click)="executeBulkAssignment()"
            [disabled]="!canExecute() || executing()"
            *ngIf="!executionResults()"
            data-testid="execute-bulk-btn"
          >
            <span *ngIf="executing()" class="spinner"></span>
            {{ executing() ? ('permissions.bulk.executing' | translate) : ('permissions.bulk.execute' | translate) }}
          </button>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 1000;
    }

    .modal-content {
      background: var(--color-surface);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-xl);
      max-width: 900px;
      max-height: 95vh;
      width: 95%;
      overflow: hidden;
      display: flex;
      flex-direction: column;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: var(--space-lg);
      border-bottom: 1px solid var(--color-border);
      background: var(--color-surface-secondary);
    }

    .modal-header h2 {
      margin: 0;
      color: var(--color-text-primary);
      font-size: var(--font-size-xl);
      font-weight: var(--font-weight-semibold);
    }

    .modal-body {
      flex: 1;
      overflow-y: auto;
      padding: var(--space-lg);
    }

    .form-section {
      margin-bottom: var(--space-xl);
      padding-bottom: var(--space-lg);
      border-bottom: 1px solid var(--color-border-light);
    }

    .form-section:last-child {
      border-bottom: none;
    }

    .form-section h3 {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      margin: 0 0 var(--space-xs) 0;
      font-size: var(--font-size-lg);
      font-weight: var(--font-weight-semibold);
      color: var(--color-text-primary);
    }

    .step-number {
      display: flex;
      align-items: center;
      justify-content: center;
      width: 24px;
      height: 24px;
      background: var(--color-primary);
      color: white;
      border-radius: 50%;
      font-size: var(--font-size-sm);
      font-weight: var(--font-weight-semibold);
    }

    .optional-label {
      font-size: var(--font-size-sm);
      color: var(--color-text-secondary);
      font-weight: var(--font-weight-normal);
    }

    .section-description {
      margin: 0 0 var(--space-md) 0;
      font-size: var(--font-size-sm);
      color: var(--color-text-secondary);
      line-height: 1.5;
    }

    .user-search {
      margin-bottom: var(--space-md);
    }

    .selection-mode {
      margin-bottom: var(--space-md);
    }

    .mode-toggle, .action-toggle {
      display: flex;
      gap: var(--space-lg);
    }

    .radio-option {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      cursor: pointer;
    }

    .radio-option input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .radio-custom {
      width: 18px;
      height: 18px;
      border: 2px solid var(--color-border);
      border-radius: 50%;
      background: var(--color-surface);
      transition: all 0.2s ease;
      position: relative;
    }

    .radio-option input[type="radio"]:checked + .radio-custom {
      border-color: var(--color-primary);
      background: var(--color-primary-light);
    }

    .radio-option input[type="radio"]:checked + .radio-custom::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 8px;
      height: 8px;
      background: var(--color-primary);
      border-radius: 50%;
    }

    .radio-label {
      font-weight: var(--font-weight-medium);
      color: var(--color-text-primary);
    }

    .users-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: var(--space-md);
      max-height: 400px;
      overflow-y: auto;
      padding: var(--space-sm);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
    }

    .user-card {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      transition: all 0.2s ease;
    }

    .user-card:hover {
      border-color: var(--color-primary-light);
    }

    .user-card.selected {
      border-color: var(--color-primary);
      background: var(--color-primary-light);
    }

    .user-checkbox {
      display: flex;
      align-items: flex-start;
      gap: var(--space-sm);
      padding: var(--space-sm);
      cursor: pointer;
      width: 100%;
    }

    .user-checkbox input[type="checkbox"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .checkbox-custom {
      width: 18px;
      height: 18px;
      border: 2px solid var(--color-border);
      border-radius: var(--radius-xs);
      background: var(--color-surface);
      transition: all 0.2s ease;
      flex-shrink: 0;
      margin-top: 2px;
    }

    .user-checkbox input[type="checkbox"]:checked + .checkbox-custom {
      background: var(--color-primary);
      border-color: var(--color-primary);
    }

    .user-checkbox input[type="checkbox"]:checked + .checkbox-custom::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 8px;
      height: 8px;
      background: white;
      mask: url('data:image/svg+xml,<svg viewBox="0 0 16 16" fill="white" xmlns="http://www.w3.org/2000/svg"><path d="M13.854 3.646a.5.5 0 0 1 0 .708l-7 7a.5.5 0 0 1-.708 0l-3.5-3.5a.5.5 0 1 1 .708-.708L6.5 10.293l6.646-6.647a.5.5 0 0 1 .708 0z"/></svg>') no-repeat center center;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      flex: 1;
      min-width: 0;
    }

    .user-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: var(--color-primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: var(--font-size-sm);
      font-weight: var(--font-weight-semibold);
      flex-shrink: 0;
    }

    .user-details {
      flex: 1;
      min-width: 0;
    }

    .user-name {
      font-weight: var(--font-weight-medium);
      color: var(--color-text-primary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .user-email {
      font-size: var(--font-size-xs);
      color: var(--color-text-secondary);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .user-roles {
      display: flex;
      gap: var(--space-xs);
      margin-top: var(--space-xs);
      flex-wrap: wrap;
    }

    .role-badge {
      padding: 1px var(--space-xs);
      background: var(--color-surface-secondary);
      color: var(--color-text-secondary);
      font-size: var(--font-size-xs);
      border-radius: var(--radius-xs);
    }

    .filter-options {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-md);
      margin-bottom: var(--space-md);
    }

    .filter-group {
      display: flex;
      flex-direction: column;
      gap: var(--space-xs);
    }

    .filter-group label {
      font-size: var(--font-size-sm);
      font-weight: var(--font-weight-medium);
      color: var(--color-text-secondary);
    }

    .form-input, .form-select {
      padding: var(--space-sm);
      border: 1px solid var(--color-border);
      border-radius: var(--radius-sm);
      background: var(--color-surface);
      color: var(--color-text-primary);
    }

    .filter-preview {
      padding: var(--space-md);
      background: var(--color-info-light);
      border-radius: var(--radius-md);
      border: 1px solid var(--color-info);
    }

    .preview-info {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      color: var(--color-info);
      font-weight: var(--font-weight-medium);
    }

    .selected-count {
      margin-top: var(--space-md);
      padding: var(--space-sm) var(--space-md);
      background: var(--color-primary-light);
      border-radius: var(--radius-md);
      color: var(--color-primary);
      font-weight: var(--font-weight-medium);
    }

    .school-selection {
      max-width: 400px;
    }

    .roles-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: var(--space-sm);
    }

    .role-option {
      border: 1px solid var(--color-border);
      border-radius: var(--radius-md);
      transition: all 0.2s ease;
    }

    .role-option:hover {
      border-color: var(--color-primary-light);
    }

    .role-option.selected {
      border-color: var(--color-primary);
      background: var(--color-primary-light);
    }

    .role-option.remove-mode.selected {
      border-color: var(--color-danger);
      background: var(--color-danger-light);
    }

    .role-checkbox {
      display: flex;
      align-items: flex-start;
      gap: var(--space-sm);
      padding: var(--space-md);
      cursor: pointer;
      width: 100%;
    }

    .role-info {
      flex: 1;
    }

    .role-name {
      font-weight: var(--font-weight-semibold);
      color: var(--color-text-primary);
      margin-bottom: var(--space-xs);
    }

    .role-identifier {
      font-size: var(--font-size-sm);
      color: var(--color-text-secondary);
      font-family: var(--font-family-mono);
      background: var(--color-surface-secondary);
      padding: 2px var(--space-xs);
      border-radius: var(--radius-xs);
      display: inline-block;
      margin-bottom: var(--space-xs);
    }

    .role-permissions-count {
      font-size: var(--font-size-xs);
      color: var(--color-primary);
      font-weight: var(--font-weight-medium);
    }

    .warning-text {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-md);
      background: var(--color-warning-light);
      border-radius: var(--radius-md);
      color: var(--color-warning);
      font-weight: var(--font-weight-medium);
      margin-bottom: var(--space-md);
    }

    .date-inputs {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: var(--space-md);
      max-width: 500px;
    }

    .date-field {
      display: flex;
      flex-direction: column;
      gap: var(--space-xs);
    }

    .date-field label {
      font-size: var(--font-size-sm);
      font-weight: var(--font-weight-medium);
      color: var(--color-text-secondary);
    }

    .field-hint {
      font-size: var(--font-size-xs);
      color: var(--color-text-secondary);
      font-style: italic;
    }

    .preview-section {
      margin-top: var(--space-xl);
      padding: var(--space-lg);
      background: var(--color-surface-secondary);
      border-radius: var(--radius-md);
      border: 1px solid var(--color-border);
    }

    .preview-section h3 {
      margin: 0 0 var(--space-md) 0;
      font-size: var(--font-size-lg);
      font-weight: var(--font-weight-semibold);
      color: var(--color-text-primary);
    }

    .preview-summary {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: var(--space-md);
      margin-bottom: var(--space-lg);
    }

    .summary-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      color: var(--color-text-secondary);
    }

    .preview-actions {
      padding: var(--space-md);
      background: var(--color-surface);
      border-radius: var(--radius-md);
      border: 1px solid var(--color-border);
    }

    .action-description {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      font-weight: var(--font-weight-medium);
    }

    .progress-section {
      margin-top: var(--space-lg);
      padding: var(--space-lg);
      background: var(--color-surface-secondary);
      border-radius: var(--radius-md);
    }

    .progress-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-md);
    }

    .progress-bar {
      width: 100%;
      height: 8px;
      background: var(--color-border);
      border-radius: var(--radius-sm);
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: var(--color-primary);
      transition: width 0.3s ease;
    }

    .progress-details {
      margin-top: var(--space-sm);
    }

    .error-count {
      color: var(--color-danger);
      font-weight: var(--font-weight-medium);
    }

    .results-section {
      margin-top: var(--space-lg);
      padding: var(--space-lg);
      border-radius: var(--radius-md);
    }

    .results-summary {
      display: flex;
      gap: var(--space-lg);
      margin-bottom: var(--space-md);
    }

    .results-summary.has-errors {
      background: var(--color-danger-light);
      padding: var(--space-md);
      border-radius: var(--radius-md);
    }

    .result-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      font-weight: var(--font-weight-medium);
    }

    .result-item.success {
      color: var(--color-success);
    }

    .result-item.error {
      color: var(--color-danger);
    }

    .error-details {
      padding: var(--space-md);
      background: var(--color-danger-light);
      border-radius: var(--radius-md);
    }

    .error-details h4 {
      margin: 0 0 var(--space-sm) 0;
      color: var(--color-danger);
    }

    .errors-list {
      margin: 0;
      padding-left: var(--space-lg);
    }

    .error-item {
      color: var(--color-danger);
      margin-bottom: var(--space-xs);
    }

    .loading-users, .no-users {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: var(--space-xl);
      color: var(--color-text-secondary);
    }

    .spinner {
      width: 24px;
      height: 24px;
      border: 2px solid var(--color-border);
      border-top: 2px solid var(--color-primary);
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-bottom: var(--space-sm);
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: var(--space-sm);
      padding: var(--space-lg);
      border-top: 1px solid var(--color-border);
      background: var(--color-surface-secondary);
    }

    .btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-sm);
      font-size: var(--font-size-sm);
      font-weight: var(--font-weight-medium);
      border: none;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: var(--space-xs);
      transition: all 0.2s ease;
    }

    .btn-primary {
      background: var(--color-primary);
      color: white;
    }

    .btn-primary:hover:not(:disabled) {
      background: var(--color-primary-dark);
    }

    .btn-outline {
      background: transparent;
      color: var(--color-text-primary);
      border: 1px solid var(--color-border);
    }

    .btn-outline:hover:not(:disabled) {
      background: var(--color-surface-hover);
    }

    .btn-warning {
      background: var(--color-warning);
      color: white;
    }

    .btn-warning:hover:not(:disabled) {
      background: var(--color-warning-dark);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .close-btn {
      background: none;
      border: none;
      color: var(--color-text-secondary);
      cursor: pointer;
      padding: var(--space-sm);
      border-radius: var(--radius-sm);
      transition: all 0.2s ease;
    }

    .close-btn:hover {
      background: var(--color-surface-hover);
      color: var(--color-text-primary);
    }

    @media (max-width: 768px) {
      .modal-content {
        width: 98%;
        max-height: 98vh;
      }

      .users-grid {
        grid-template-columns: 1fr;
      }

      .filter-options {
        grid-template-columns: 1fr;
      }

      .date-inputs {
        grid-template-columns: 1fr;
      }

      .preview-summary {
        grid-template-columns: 1fr;
      }

      .mode-toggle, .action-toggle {
        flex-direction: column;
        gap: var(--space-md);
      }

      .results-summary {
        flex-direction: column;
        gap: var(--space-md);
      }

      .modal-footer {
        flex-direction: column;
      }
    }
  `]
})
export class BulkAssignmentModalComponent implements OnInit, OnChanges {
  @Input() isOpen = false;
  @Input() preselectedUsers: number[] = [];
  @Input() preselectedSchoolId: number | null = null;

  @Output() closed = new EventEmitter<void>();
  @Output() completed = new EventEmitter<{ successful: number; failed: number; errors: string[] }>();

  private readonly permissionsService = inject(SchoolPermissionsService);
  private readonly usersService = inject(UsersService);
  private readonly rolesService = inject(RolesService);
  private readonly fb = inject(FormBuilder);

  // Form controls
  userSearchControl = this.fb.control('');

  // Reactive state using signals
  availableUsers = signal<UserListItem[]>([]);
  filteredUsers = signal<UserListItem[]>([]);
  availableRoles = signal<Role[]>([]);
  availableSchools = signal<School[]>([]);
  selectedUserIds = signal<number[]>([]);
  selectedRolesForBulk = signal<string[]>([]);
  loadingUsers = signal(false);
  executing = signal(false);
  executionProgress = signal<{ processed: number; total: number; errors: string[] }>({ processed: 0, total: 0, errors: [] });
  executionResults = signal<{ successful: number; failed: number; errors: string[] } | null>(null);

  // Computed values
  selectedUserCount = computed(() => {
    if (this.bulkForm.get('selectionMode')?.value === 'manual') {
      return this.selectedUserIds().length;
    } else {
      // Calculate based on filters
      return this.filteredUsers().length;
    }
  });

  // Form
  bulkForm: FormGroup;

  constructor() {
    this.bulkForm = this.fb.group({
      selectionMode: ['manual', Validators.required],
      filterRole: [''],
      filterStatus: [''],
      schoolId: ['', Validators.required],
      action: ['assign', Validators.required],
      roles: this.fb.array([]),
      startDate: [''],
      endDate: ['']
    });
  }

  ngOnInit(): void {
    this.loadInitialData();
    this.setupUserSearch();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['isOpen'] && this.isOpen) {
      this.resetForm();
      if (this.preselectedUsers.length > 0) {
        this.selectedUserIds.set([...this.preselectedUsers]);
      }
      if (this.preselectedSchoolId) {
        this.bulkForm.patchValue({ schoolId: this.preselectedSchoolId });
      }
    }
  }

  private loadInitialData(): void {
    // Load users, roles, and schools
    this.loadUsers();
    this.loadRoles();
    this.loadSchools();
  }

  private loadUsers(): void {
    this.loadingUsers.set(true);
    this.usersService.getUsers().pipe(
      catchError(error => {
        console.error('Error loading users:', error);
        return of({ data: [], meta: { total: 0, page: 1, perPage: 20, lastPage: 1 } });
      }),
      finalize(() => this.loadingUsers.set(false))
    ).subscribe(response => {
      this.availableUsers.set(response.data);
      this.filteredUsers.set(response.data);
    });
  }

  private loadRoles(): void {
    this.rolesService.getRoles().pipe(
      catchError(error => {
        console.error('Error loading roles:', error);
        return of({ data: [] as Role[] });
      })
    ).subscribe(response => {
      this.availableRoles.set(response.data);
    });
  }

  private loadSchools(): void {
    // Mock schools data - in real app, load from SchoolsService
    this.availableSchools.set([
      { id: 1, name: 'Escuela Central', code: 'EC001', active: true },
      { id: 2, name: 'Sede Norte', code: 'SN002', active: true },
      { id: 3, name: 'Campus Sur', code: 'CS003', active: true }
    ]);
  }

  private setupUserSearch(): void {
    this.userSearchControl.valueChanges.pipe(
      debounceTime(300),
      distinctUntilChanged()
    ).subscribe(searchTerm => {
      this.filterUsers(searchTerm || '');
    });
  }

  private filterUsers(searchTerm: string): void {
    const filtered = this.availableUsers().filter(user =>
      user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      user.email.toLowerCase().includes(searchTerm.toLowerCase())
    );
    this.filteredUsers.set(filtered);
  }

  isUserSelected(userId: number): boolean {
    return this.selectedUserIds().includes(userId);
  }

  toggleUser(userId: number, event: Event): void {
    const checkbox = event.target as HTMLInputElement;
    const currentSelected = [...this.selectedUserIds()];

    if (checkbox.checked && !currentSelected.includes(userId)) {
      currentSelected.push(userId);
    } else if (!checkbox.checked) {
      const index = currentSelected.indexOf(userId);
      if (index > -1) {
        currentSelected.splice(index, 1);
      }
    }

    this.selectedUserIds.set(currentSelected);
  }

  isRoleSelectedForBulk(roleName: string): boolean {
    return this.selectedRolesForBulk().includes(roleName);
  }

  toggleBulkRole(roleName: string, event: Event): void {
    const checkbox = event.target as HTMLInputElement;
    const currentRoles = [...this.selectedRolesForBulk()];

    if (checkbox.checked && !currentRoles.includes(roleName)) {
      currentRoles.push(roleName);
    } else if (!checkbox.checked) {
      const index = currentRoles.indexOf(roleName);
      if (index > -1) {
        currentRoles.splice(index, 1);
      }
    }

    this.selectedRolesForBulk.set(currentRoles);
  }

  canShowPreview(): boolean {
    return this.bulkForm.get('schoolId')?.value && 
           this.selectedUserCount() > 0 && 
           this.selectedRolesForBulk().length > 0;
  }

  canExecute(): boolean {
    return this.bulkForm.valid && 
           this.selectedUserCount() > 0 && 
           this.selectedRolesForBulk().length > 0 &&
           !this.executing();
  }

  getSelectedSchoolName(): string {
    const schoolId = this.bulkForm.get('schoolId')?.value;
    const school = this.availableSchools().find(s => s.id === parseInt(schoolId));
    return school ? school.name : '';
  }

  getDateRangeText(): string {
    const startDate = this.bulkForm.get('startDate')?.value;
    const endDate = this.bulkForm.get('endDate')?.value;

    if (!startDate && !endDate) return 'Sin límite de tiempo';
    if (startDate && !endDate) return `Desde ${startDate}`;
    if (!startDate && endDate) return `Hasta ${endDate}`;
    return `${startDate} - ${endDate}`;
  }

  getProgressPercentage(): number {
    const progress = this.executionProgress();
    return progress.total > 0 ? (progress.processed / progress.total) * 100 : 0;
  }

  executeBulkAssignment(): void {
    if (!this.canExecute()) return;

    this.executing.set(true);
    this.executionResults.set(null);

    const userIds = this.bulkForm.get('selectionMode')?.value === 'manual' 
      ? this.selectedUserIds() 
      : this.filteredUsers().map(u => u.id);

    const assignment: BulkPermissionAssignment = {
      userIds,
      schoolId: parseInt(this.bulkForm.get('schoolId')?.value),
      roles: this.selectedRolesForBulk(),
      startDate: this.bulkForm.get('startDate')?.value || undefined,
      endDate: this.bulkForm.get('endDate')?.value || undefined
    };

    this.executionProgress.set({ processed: 0, total: userIds.length, errors: [] });

    this.permissionsService.bulkAssignRoles(assignment).pipe(
      catchError(error => {
        return of({ successful: 0, failed: userIds.length, errors: [error.message || 'Bulk assignment failed'] });
      }),
      finalize(() => this.executing.set(false))
    ).subscribe(result => {
      this.executionResults.set(result);
      this.completed.emit(result);
    });
  }

  resetForm(): void {
    this.bulkForm.reset({
      selectionMode: 'manual',
      action: 'assign'
    });
    this.selectedUserIds.set([]);
    this.selectedRolesForBulk.set([]); 
    this.executionResults.set(null);
    this.executionProgress.set({ processed: 0, total: 0, errors: [] });
    this.userSearchControl.reset();
  }

  closeModal(): void {
    this.closed.emit();
    this.resetForm();
  }

  getInitials(name: string): string {
    return name
      .split(' ')
      .map(word => word.charAt(0))
      .join('')
      .toUpperCase()
      .substring(0, 2);
  }

  trackByUserId(index: number, user: UserListItem): number {
    return user.id;
  }

  trackByRoleId(index: number, role: Role): number {
    return role.id;
  }
}