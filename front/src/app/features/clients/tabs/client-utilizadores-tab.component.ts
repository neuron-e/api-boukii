import { Component, Input, Output, EventEmitter, OnInit, inject, signal, ViewChild, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';

import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { ToastService } from '@core/services/toast.service';
import { ClientsV5Service } from '@core/services/clients-v5.service';
import { ClientDetail, ClientUtilizador } from '../client-detail.page';

@Component({
  selector: 'app-client-utilizadores-tab',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  template: `
    <div class="client-utilizadores-tab">
      <div class="tab-header">
        <div>
          <h2>{{ 'clients.tabs.utilizadores' | translate }}</h2>
          <p class="tab-description">{{ 'clients.tabs.utilizadoresDescription' | translate }}</p>
        </div>
        
        <button type="button" class="btn btn--primary" (click)="openModal('create')">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
          </svg>
          {{ 'clients.utilizadores.add' | translate }}
        </button>
      </div>

      @if (utilizadores().length === 0) {
        <div class="empty-state">
          <div class="empty-icon">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M16 4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zm4 18v-6h2.5l-2.54-7.63A3 3 0 0 0 17.24 7H14.5c-1.3 0-2.42.84-2.83 2.01L9.35 12 8 10.65V9a1 1 0 0 0-2 0v2.5L9.5 15 8 16v2a1 1 0 0 0 2 0v-1.65L11.35 15l1.92 5.11c.19.5.69.85 1.23.89H20z"/>
            </svg>
          </div>
          <h3>{{ 'clients.utilizadores.empty.title' | translate }}</h3>
          <p>{{ 'clients.utilizadores.empty.message' | translate }}</p>
          <button type="button" class="btn btn--primary" (click)="openModal('create')">
            {{ 'clients.utilizadores.add' | translate }}
          </button>
        </div>
      }

      @if (utilizadores().length > 0) {
        <div class="utilizadores-grid">
          @for (utilizador of utilizadores(); track utilizador.id) {
            <div class="utilizador-card">
              <div class="utilizador-avatar">
                @if (utilizador.image) {
                  <img [src]="utilizador.image" [alt]="getFullName(utilizador)" />
                } @else {
                  <div class="avatar-placeholder">
                    {{ getInitials(getFullName(utilizador)) }}
                  </div>
                }
              </div>
              
              <div class="utilizador-info">
                <h4 class="utilizador-name">{{ getFullName(utilizador) }}</h4>
                @if (utilizador.birth_date) {
                  <p class="utilizador-age">{{ getAge(utilizador.birth_date) }} {{ 'clients.age' | translate }}</p>
                }
                <p class="utilizador-date">
                  {{ 'clients.addedOn' | translate }}: {{ formatDate(utilizador.created_at) }}
                </p>
              </div>

              <div class="utilizador-actions">
                <button type="button" class="action-btn" (click)="openModal('edit', utilizador)" [attr.aria-label]="'common.edit' | translate">
                  <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                  </svg>
                </button>
                <button type="button" class="action-btn action-btn--danger" (click)="confirmDelete(utilizador)" [attr.aria-label]="'common.delete' | translate">
                  <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                  </svg>
                </button>
              </div>
            </div>
          }
        </div>
      }

      <!-- Modal Overlay -->
      @if (showModal()) {
        <div class="modal-overlay" (click)="closeModal()">
          <div class="modal-content" (click)="$event.stopPropagation()" #modalContent>
            <div class="modal-header">
              <h3>
                @if (modalMode() === 'create') {
                  {{ 'clients.utilizadores.create' | translate }}
                } @else {
                  {{ 'clients.utilizadores.edit' | translate }}
                }
              </h3>
              <button type="button" class="close-btn" (click)="closeModal()" [attr.aria-label]="'common.close' | translate">
                <svg viewBox="0 0 24 24" fill="currentColor">
                  <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                </svg>
              </button>
            </div>

            <form [formGroup]="utilizadorForm" (ngSubmit)="onSubmit()" class="modal-form">
              <div class="form-grid">
                <div class="form-field">
                  <label for="first_name_utilizador">{{ 'clients.fields.firstName' | translate }} *</label>
                  <input
                    id="first_name_utilizador"
                    type="text"
                    formControlName="first_name"
                    [class.is-invalid]="isFieldInvalid('first_name')"
                    [attr.aria-describedby]="isFieldInvalid('first_name') ? 'first_name_error' : null" />
                  @if (isFieldInvalid('first_name')) {
                    <small id="first_name_error" class="field-error">{{ getFieldError('first_name') }}</small>
                  }
                </div>

                <div class="form-field">
                  <label for="last_name_utilizador">{{ 'clients.fields.lastName' | translate }} *</label>
                  <input
                    id="last_name_utilizador"
                    type="text"
                    formControlName="last_name"
                    [class.is-invalid]="isFieldInvalid('last_name')"
                    [attr.aria-describedby]="isFieldInvalid('last_name') ? 'last_name_error' : null" />
                  @if (isFieldInvalid('last_name')) {
                    <small id="last_name_error" class="field-error">{{ getFieldError('last_name') }}</small>
                  }
                </div>

                <div class="form-field">
                  <label for="birth_date_utilizador">{{ 'clients.fields.birthDate' | translate }}</label>
                  <input
                    id="birth_date_utilizador"
                    type="date"
                    formControlName="birth_date"
                    [class.is-invalid]="isFieldInvalid('birth_date')"
                    [attr.aria-describedby]="isFieldInvalid('birth_date') ? 'birth_date_error' : null" />
                  @if (isFieldInvalid('birth_date')) {
                    <small id="birth_date_error" class="field-error">{{ getFieldError('birth_date') }}</small>
                  }
                </div>
              </div>

              <div class="modal-actions">
                <button type="button" class="btn btn--outline" (click)="closeModal()">
                  {{ 'common.cancel' | translate }}
                </button>
                <button type="submit" class="btn btn--primary" [disabled]="utilizadorForm.invalid || saving()">
                  @if (saving()) {
                    <div class="loading-spinner"></div>
                  }
                  @if (modalMode() === 'create') {
                    {{ 'common.create' | translate }}
                  } @else {
                    {{ 'common.save' | translate }}
                  }
                </button>
              </div>
            </form>
          </div>
        </div>
      }

      <!-- Delete Confirmation Modal -->
      @if (showDeleteModal()) {
        <div class="modal-overlay" (click)="cancelDelete()">
          <div class="modal-content modal-content--small" (click)="$event.stopPropagation()">
            <div class="modal-header">
              <h3>{{ 'clients.utilizadores.confirmDelete' | translate }}</h3>
            </div>

            <div class="modal-body">
              <p>
                {{ 'clients.utilizadores.confirmDeleteMessage' | translate }}
                <strong>{{ getFullName(utilizadorToDelete()!) }}</strong>?
              </p>
              <p class="warning-text">{{ 'clients.utilizadores.deleteWarning' | translate }}</p>
            </div>

            <div class="modal-actions">
              <button type="button" class="btn btn--outline" (click)="cancelDelete()">
                {{ 'common.cancel' | translate }}
              </button>
              <button type="button" class="btn btn--danger" (click)="deleteUtilizador()" [disabled]="saving()">
                @if (saving()) {
                  <div class="loading-spinner"></div>
                }
                {{ 'common.delete' | translate }}
              </button>
            </div>
          </div>
        </div>
      }
    </div>
  `,
  styleUrls: ['./client-utilizadores-tab.component.scss']
})
export class ClientUtilizadoresTabComponent implements OnInit {
  @Input() client!: ClientDetail;
  @Output() utilizadoresUpdated = new EventEmitter<ClientUtilizador[]>();

  @ViewChild('modalContent', { static: false }) modalContent!: ElementRef;

  private readonly fb = inject(FormBuilder);
  private readonly toastService = inject(ToastService);
  private readonly clientsService = inject(ClientsV5Service);

  readonly utilizadores = signal<ClientUtilizador[]>([]);
  readonly showModal = signal(false);
  readonly modalMode = signal<'create' | 'edit'>('create');
  readonly editingUtilizador = signal<ClientUtilizador | null>(null);
  readonly saving = signal(false);
  readonly showDeleteModal = signal(false);
  readonly utilizadorToDelete = signal<ClientUtilizador | null>(null);

  utilizadorForm!: FormGroup;

  ngOnInit(): void {
    this.utilizadores.set(this.client.utilizadores || []);
    this.initializeForm();
  }

  private initializeForm(): void {
    this.utilizadorForm = this.fb.group({
      first_name: ['', [Validators.required, Validators.minLength(2)]],
      last_name: ['', [Validators.required, Validators.minLength(2)]],
      birth_date: ['']
    });
  }

  openModal(mode: 'create' | 'edit', utilizador?: ClientUtilizador): void {
    this.modalMode.set(mode);
    this.editingUtilizador.set(utilizador || null);
    
    if (mode === 'edit' && utilizador) {
      this.utilizadorForm.patchValue({
        first_name: utilizador.first_name,
        last_name: utilizador.last_name,
        birth_date: utilizador.birth_date || ''
      });
    } else {
      this.utilizadorForm.reset();
    }

    this.showModal.set(true);
    
    // Focus first input after modal opens
    setTimeout(() => {
      const firstInput = this.modalContent?.nativeElement?.querySelector('input[type="text"]') as HTMLInputElement;
      firstInput?.focus();
    }, 100);
  }

  closeModal(): void {
    this.showModal.set(false);
    this.editingUtilizador.set(null);
    this.utilizadorForm.reset();
  }

  confirmDelete(utilizador: ClientUtilizador): void {
    this.utilizadorToDelete.set(utilizador);
    this.showDeleteModal.set(true);
  }

  cancelDelete(): void {
    this.showDeleteModal.set(false);
    this.utilizadorToDelete.set(null);
  }

  deleteUtilizador(): void {
    const utilizadorToDelete = this.utilizadorToDelete();
    if (!utilizadorToDelete) return;

    this.saving.set(true);
    this.clientsService
      .deleteUtilizador(this.client.id, utilizadorToDelete.id)
      .subscribe({
        next: () => {
          const updated = this.utilizadores().filter(u => u.id !== utilizadorToDelete.id);
          this.utilizadores.set(updated);
          this.utilizadoresUpdated.emit(updated);

          this.showDeleteModal.set(false);
          this.utilizadorToDelete.set(null);
          this.toastService.success('clients.utilizadores.deleted');
          this.saving.set(false);
        },
        error: err => {
          console.error(err);
          this.toastService.error('clients.utilizadores.deleteError');
          this.saving.set(false);
        }
      });
  }

  onSubmit(): void {
    if (this.utilizadorForm.invalid) {
      this.markAllFieldsAsTouched();
      return;
    }

    this.saving.set(true);
    const formValue = this.utilizadorForm.value;

    if (this.modalMode() === 'create') {
      this.clientsService
        .createUtilizador(this.client.id, {
          first_name: formValue.first_name,
          last_name: formValue.last_name,
          birth_date: formValue.birth_date || undefined
        })
        .subscribe({
          next: newUtilizador => {
            const updated = [...this.utilizadores(), newUtilizador];
            this.utilizadores.set(updated);
            this.utilizadoresUpdated.emit(updated);
            this.toastService.success('clients.utilizadores.created');
            this.saving.set(false);
            this.closeModal();
          },
          error: err => {
            console.error(err);
            this.toastService.error('clients.utilizadores.createError');
            this.saving.set(false);
          }
        });
    } else {
      const editingUtilizador = this.editingUtilizador();
      if (editingUtilizador) {
        this.clientsService
          .updateUtilizador(this.client.id, editingUtilizador.id, {
            first_name: formValue.first_name,
            last_name: formValue.last_name,
            birth_date: formValue.birth_date || undefined
          })
          .subscribe({
            next: updatedUtilizador => {
              const updated = this.utilizadores().map(u =>
                u.id === editingUtilizador.id ? updatedUtilizador : u
              );
              this.utilizadores.set(updated);
              this.utilizadoresUpdated.emit(updated);
              this.toastService.success('clients.utilizadores.updated');
              this.saving.set(false);
              this.closeModal();
            },
            error: err => {
              console.error(err);
              this.toastService.error('clients.utilizadores.updateError');
              this.saving.set(false);
            }
          });
      }
    }
  }

  isFieldInvalid(fieldName: string): boolean {
    const control = this.utilizadorForm.get(fieldName);
    return !!(control && control.invalid && (control.dirty || control.touched));
  }

  getFieldError(fieldName: string): string {
    const control = this.utilizadorForm.get(fieldName);
    if (control && control.errors && (control.dirty || control.touched)) {
      if (control.errors['required']) {
        return `clients.validation.${fieldName}Required`;
      }
      if (control.errors['minlength']) {
        return `clients.validation.${fieldName}MinLength`;
      }
    }
    return '';
  }

  getFullName(utilizador: ClientUtilizador): string {
    return `${utilizador.first_name} ${utilizador.last_name}`.trim();
  }

  getInitials(name: string): string {
    return name
      .split(' ')
      .map(part => part.charAt(0).toUpperCase())
      .slice(0, 2)
      .join('');
  }

  getAge(birthDate: string): number {
    if (!birthDate) return 0;
    const today = new Date();
    const birth = new Date(birthDate);
    let age = today.getFullYear() - birth.getFullYear();
    const monthDiff = today.getMonth() - birth.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
      age--;
    }
    
    return age;
  }

  formatDate(dateString: string): string {
    if (!dateString) return '';
    try {
      return new Date(dateString).toLocaleDateString();
    } catch {
      return dateString;
    }
  }

  private markAllFieldsAsTouched(): void {
    Object.keys(this.utilizadorForm.controls).forEach(key => {
      const control = this.utilizadorForm.get(key);
      control?.markAsTouched();
    });
  }
}