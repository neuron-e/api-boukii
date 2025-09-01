import { Component, inject, Inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { UIInputComponent } from '@shared/components/ui/input/ui-input.component';
import { UISelectComponent } from '@shared/components/ui/select/ui-select.component';
import { UICheckboxComponent } from '@shared/components/ui/checkbox/ui-checkbox.component';
import { UITextareaComponent } from '@shared/components/ui/textarea/ui-textarea.component';
import { ApiService } from '@core/services/api.service';

export interface CreateClientWizardData {
  // Identity
  first_name: string;
  last_name: string;
  date_of_birth?: string;
  gender?: 'male' | 'female' | 'other' | 'prefer_not_to_say';
  nationality?: string;
  preferred_language?: 'es' | 'en' | 'fr' | 'de' | 'it';
  // Contact
  email?: string;
  phone?: string;
  telephone?: string;
  communication_method?: 'email' | 'sms' | 'phone' | 'whatsapp';
  status?: 'active' | 'inactive' | 'blocked' | 'pending';
  // Address
  address?: { street?: string; city?: string; postal_code?: string; country?: string };
  emergency_contact?: { name?: string; phone?: string; relationship?: string };
  // Preferences
  level?: 'Principiante' | 'Intermedio' | 'Avanzado' | 'Experto';
  instructor_gender?: 'male' | 'female' | 'no_preference';
  group_size?: 'private' | 'small_group' | 'large_group' | 'no_preference';
  // Misc
  notes?: string;
  sendWelcome?: boolean;
  avatar?: string; // uploaded avatar URL
}

@Component({
  selector: 'app-create-client-wizard-dialog',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    UIInputComponent,
    UISelectComponent,
    UICheckboxComponent,
    UITextareaComponent,
  ],
  template: `
    <div class="modal-header">
      <div class="header-left">
        <div class="header-icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
          </svg>
        </div>
        <div class="header-text">
          <h2>Nuevo Cliente</h2>
          <p>Paso {{ currentStep() + 1 }} de 4: {{ getStepTitle() }}</p>
          <p class="description">Crea un nuevo cliente completando la información en 4 pasos</p>
        </div>
      </div>
      <button class="close-button" mat-dialog-close>×</button>
    </div>

    <mat-dialog-content class="modal-content">
      <div class="progress-stepper">
        <div class="step-item" 
             *ngFor="let step of steps; let i = index" 
             [class.active]="currentStep() === i"
             [class.completed]="currentStep() > i">
          <div class="step-number">{{ i + 1 }}</div>
          <div class="step-line" *ngIf="i < steps.length - 1"></div>
        </div>
      </div>

      <div *ngIf="currentStep() === 0" class="step-content">
        <div class="info-card">
          <div class="section-header">
            <div class="section-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z"/>
              </svg>
            </div>
            <h3>Información Personal</h3>
          </div>

          <div class="avatar-section">
            <div class="avatar-preview" *ngIf="avatarPreviewUrl; else initialsTpl">
              <img [src]="avatarPreviewUrl" alt="Avatar preview" />
            </div>
            <ng-template #initialsTpl>
              <div class="avatar-circle">{{ getInitials() }}</div>
            </ng-template>
            <input type="file" accept="image/*" (change)="onFileInput($event)" hidden #fileInput>
            <button class="upload-photo-btn" type="button" (click)="fileInput.click()">
              📷 Subir foto
            </button>
          </div>

          <div class="form-grid">
            <div class="field-group">
              <label class="field-label">Nombre completo *</label>
              <input 
                type="text" 
                class="field-input"
                [class.field-error]="getFieldError('full_name', 'identity')"
                placeholder="Nombre y apellidos"
                [formControl]="identityForm.controls.full_name">
              <div class="error-message" *ngIf="getFieldError('full_name', 'identity')">
                El nombre completo es obligatorio
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">Email *</label>
              <input 
                type="email" 
                class="field-input"
                [class.field-error]="getFieldError('email', 'identity')"
                placeholder="correo@ejemplo.com"
                [formControl]="identityForm.controls.email">
              <div class="error-message" *ngIf="getFieldError('email', 'identity')">
                El email es obligatorio
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">Teléfono *</label>
              <input 
                type="tel" 
                class="field-input"
                [class.field-error]="getFieldError('phone', 'identity')"
                placeholder="+34 666 123 456"
                [formControl]="identityForm.controls.phone">
              <div class="error-message" *ngIf="getFieldError('phone', 'identity')">
                El teléfono es obligatorio
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">Tipo de cliente</label>
              <select 
                class="field-select"
                [formControl]="identityForm.controls.client_type">
                <option value="nuevo">Nuevo</option>
                <option *ngFor="let option of clientTypeOptions.slice(1)" [value]="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <div *ngIf="currentStep() === 1" class="step-content">
        <div class="info-card">
          <div class="section-header">
            <div class="section-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
              </svg>
            </div>
            <h3>Contacto y Preferencias</h3>
          </div>

          <div class="form-grid">
            <div class="field-group field-full">
              <label class="field-label">Dirección *</label>
              <input 
                type="text" 
                class="field-input" 
                [class.field-error]="getFieldError('address', 'contact')"
                placeholder="Calle, número, ciudad, código postal"
                [formControl]="contactForm.controls.address">
              <div class="error-message" *ngIf="getFieldError('address', 'contact')">
                La dirección es obligatoria
              </div>
            </div>

            <div class="field-group">
              <label class="field-label">Idioma</label>
              <select 
                class="field-select"
                [formControl]="contactForm.controls.language">
                <option value="es">Español</option>
                <option *ngFor="let option of languageOptions.slice(1)" [value]="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>
          </div>

          <div class="preferences-section">
            <h4>Preferencias de Contacto</h4>
            <div class="checkbox-group">
              <label class="preference-checkbox">
                <input type="checkbox" [checked]="preferences.email" (change)="updatePreference('email', $event)">
                <span class="checkmark"></span>
                <span class="checkbox-label">Email</span>
              </label>
              <label class="preference-checkbox">
                <input type="checkbox" [checked]="preferences.sms" (change)="updatePreference('sms', $event)">
                <span class="checkmark"></span>
                <span class="checkbox-label">SMS</span>
              </label>
              <label class="preference-checkbox">
                <input type="checkbox" [checked]="preferences.whatsapp" (change)="updatePreference('whatsapp', $event)">
                <span class="checkmark"></span>
                <span class="checkbox-label">WhatsApp</span>
              </label>
              <label class="preference-checkbox">
                <input type="checkbox" [checked]="preferences.phone" (change)="updatePreference('phone', $event)">
                <span class="checkmark"></span>
                <span class="checkbox-label">Llamada telefónica</span>
              </label>
            </div>
          </div>
        </div>
      </div>

      <div *ngIf="currentStep() === 2" class="step-content">
        <div class="info-card">
          <div class="section-header">
            <div class="section-icon">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 0 0-1.02.24l-2.2 2.2a15.074 15.074 0 0 1-6.59-6.59l2.2-2.2c.27-.27.35-.67.24-1.02C8.7 6.45 8.5 5.25 8.5 4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1c0 9.39 7.61 17 17 17a1 1 0 0 0 1-1v-3.5a1 1 0 0 0-1-1z"/>
              </svg>
            </div>
            <h3>Contacto de Emergencia</h3>
          </div>

          <div class="form-grid">
            <div class="field-group">
              <label class="field-label">Nombre *</label>
              <input 
                type="text" 
                class="field-input" 
                [class.field-error]="getFieldError('emergency_name', 'emergency')"
                placeholder="Nombre del contacto"
                [formControl]="emergencyForm.controls.emergency_name">
              <div class="error-message" *ngIf="getFieldError('emergency_name', 'emergency')">
                El nombre es obligatorio
              </div>
            </div>
            
            <div class="field-group">
              <label class="field-label">Teléfono *</label>
              <input 
                type="tel" 
                class="field-input" 
                [class.field-error]="getFieldError('emergency_phone', 'emergency')"
                placeholder="+34 666 123 456"
                [formControl]="emergencyForm.controls.emergency_phone">
              <div class="error-message" *ngIf="getFieldError('emergency_phone', 'emergency')">
                El teléfono es obligatorio
              </div>
            </div>
            
            <div class="field-group field-full">
              <label class="field-label">Relación *</label>
              <select 
                class="field-select"
                [class.field-error]="getFieldError('emergency_relationship', 'emergency')"
                [formControl]="emergencyForm.controls.emergency_relationship">
                <option value="">Seleccionar relación</option>
                <option *ngFor="let option of relationshipOptions.slice(1)" [value]="option.value">
                  {{ option.label }}
                </option>
              </select>
              <div class="error-message" *ngIf="getFieldError('emergency_relationship', 'emergency')">
                La relación es obligatoria
              </div>
            </div>
          </div>
        </div>
      </div>

      <div *ngIf="currentStep() === 3" class="step-content">
        <div class="info-card">
          <div class="section-header">
            <div class="section-icon">⭐</div>
            <h3>Configuración Final</h3>
          </div>

          <div class="notes-section">
            <label class="field-label">Notas y Comentarios</label>
            <textarea 
              class="notes-textarea" 
              placeholder="Añade cualquier información adicional sobre el cliente..."
              [formControl]="preferencesForm.controls.notes"
              rows="4">
            </textarea>
          </div>

          <div class="summary-section">
            <h4>Resumen del Cliente</h4>
            <div class="summary-list">
              <div class="summary-row">
                <span class="summary-label">Nombre</span>
                <span class="summary-value">{{ getSummaryName() }}</span>
              </div>
              <div class="summary-row">
                <span class="summary-label">Email</span>
                <span class="summary-value">{{ getSummaryEmail() }}</span>
              </div>
              <div class="summary-row">
                <span class="summary-label">Teléfono</span>
                <span class="summary-value">{{ getSummaryPhone() }}</span>
              </div>
              <div class="summary-row">
                <span class="summary-label">Tipo</span>
                <span class="summary-value">{{ getSummaryType() }}</span>
              </div>
              <div class="summary-row" *ngIf="getSummaryAddress()">
                <span class="summary-label">Dirección</span>
                <span class="summary-value">{{ getSummaryAddress() }}</span>
              </div>
              <div class="summary-row" *ngIf="hasEmergencyContact()">
                <span class="summary-label">Contacto de emergencia</span>
                <span class="summary-value">{{ getEmergencyContactSummary() }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </mat-dialog-content>
    
    <div class="modal-footer">
      <button class="btn btn-cancel" mat-dialog-close>Cancelar</button>
      <button class="btn btn-back" (click)="prev()" *ngIf="currentStep() > 0">Anterior</button>
      <button class="btn btn-next" (click)="onNextClick()" *ngIf="currentStep() < 3" [disabled]="isNextDisabled()">Siguiente</button>
      <button class="btn btn-create" (click)="confirm()" *ngIf="currentStep() === 3" [disabled]="!canCreateClient()">Crear Cliente</button>
    </div>
  `,
  styles: [
    `
      :host {
        display: block;
        width: 100%;
        max-width: 500px;
        font-family: var(--font-family-sans);
        font-size: var(--font-size);
      }
      
      ::ng-deep .create-client-dialog .mat-mdc-dialog-container {
        padding: 0 !important;
        border-radius: var(--radius-lg) !important;
        overflow: hidden !important;
        max-width: 500px !important;
        width: 100% !important;
        margin: auto !important;
      }
      
      ::ng-deep .create-client-dialog .mat-mdc-dialog-surface {
        border-radius: var(--radius-lg) !important;
        overflow: hidden !important;
        width: 100% !important;
        max-width: 500px !important;
      }
      
      ::ng-deep .create-client-dialog {
        width: 500px !important;
        max-width: 90vw !important;
      }
      
      .modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: var(--space-5) var(--space-6);
        background: var(--ski-light-gray);
        border-bottom: 1px solid var(--border);
      }
      
      .header-left {
        display: flex;
        align-items: flex-start;
        gap: var(--space-3);
      }
      
      .header-icon {
        width: var(--space-10);
        height: var(--space-10);
        background: var(--ski-dark-gray);
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-white);
        flex-shrink: 0;
      }
      
      .header-text {
        flex: 1;
      }
      
      .header-text h2 {
        margin: 0;
        font-size: var(--text-lg);
        font-weight: var(--font-weight-medium);
        color: var(--foreground);
        line-height: 1.4;
      }
      
      .header-text p {
        margin: calc(var(--spacing) * 0.5) 0 0;
        font-size: var(--text-sm);
        color: var(--muted-foreground);
        line-height: 1.4;
      }
      
      .header-text .description {
        margin-top: var(--space-1);
        font-size: var(--text-sm);
        color: var(--muted-foreground);
      }
      
      .close-button {
        width: var(--space-6);
        height: var(--space-6);
        border: none;
        background: none;
        font-size: var(--text-lg);
        color: var(--muted-foreground);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: var(--radius-sm);
        margin-top: calc(var(--spacing) * -0.5);
        transition: all var(--duration-fast) var(--ease-out);
      }
      
      .close-button:hover {
        background: var(--muted);
        color: var(--foreground);
      }
      
      .modal-content {
        padding: var(--space-6);
        background: var(--card);
        min-height: 350px;
      }
      
      .progress-stepper {
        display: flex;
        align-items: center;
        justify-content: center;
        margin: var(--space-6) auto var(--space-8);
        padding: 0;
        gap: var(--space-8);
        position: relative;
      }
      
      .progress-stepper::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 16px;
        right: 16px;
        height: 2px;
        background: var(--border);
        z-index: 1;
        transform: translateY(-50%);
      }
      
      .step-item {
        position: relative;
        z-index: 2;
      }
      
      .step-number {
        width: var(--space-8);
        height: var(--space-8);
        border-radius: 50%;
        background: var(--card);
        color: var(--muted-foreground);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--font-size);
        font-weight: var(--font-weight-medium);
        border: 2px solid var(--border);
        transition: all var(--duration-fast) var(--ease-out);
      }
      
      .step-item.active .step-number {
        background: var(--foreground);
        color: var(--card);
        border-color: var(--foreground);
      }
      
      .step-item.completed .step-number {
        background: var(--foreground);
        color: var(--card);
        border-color: var(--foreground);
      }
      
      .step-content {
        margin-bottom: 32px;
      }
      
      .section-header {
        display: flex;
        align-items: center;
        gap: var(--space-3);
        margin-bottom: var(--space-8);
      }
      
      .section-icon {
        width: var(--space-8);
        height: var(--space-8);
        background: var(--ski-dark-gray);
        border-radius: var(--radius-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-white);
        flex-shrink: 0;
      }
      
      .section-header h3 {
        margin: 0;
        font-size: var(--text-base);
        font-weight: var(--font-weight-medium);
        color: var(--foreground);
      }
      
      .upload-section {
        display: flex;
        justify-content: center;
        margin: var(--space-6) 0;
      }
      
      .avatar-upload {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-4);
        border: 2px dashed var(--border);
        border-radius: var(--radius-md);
        background: var(--card);
        min-width: 200px;
        transition: border-color var(--duration-fast) var(--ease-out), background var(--duration-fast) var(--ease-out);
      }
      .avatar-upload.dragover {
        border-color: var(--primary);
        background: color-mix(in srgb, var(--primary) 6%, transparent);
      }
      
      .avatar-preview img {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        display: block;
      }

      .avatar-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--muted);
        color: var(--muted-foreground);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--text-lg);
        font-weight: var(--font-weight-medium);
      }
      
      .upload-btn {
        background: none;
        border: none;
        color: var(--primary);
        font-size: var(--text-sm);
        cursor: pointer;
        text-decoration: underline;
      }
      
      .form-container {
        padding: 0 var(--space-4);
      }
      
      .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-4) var(--space-5);
        margin-bottom: var(--space-6);
      }
      
      .field-full {
        grid-column: 1 / -1;
      }
      
      .preferences-section {
        margin-top: var(--space-6);
        padding-top: var(--space-4);
        border-top: 1px solid var(--border);
      }
      
      .preferences-section h4 {
        margin: 0 0 var(--space-4) 0;
        font-size: var(--text-base);
        font-weight: var(--font-weight-medium);
        color: var(--foreground);
      }
      
      .checkbox-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-3);
      }
      
      .notes-section {
        margin-bottom: var(--space-6);
      }
      
      .notes-textarea {
        width: 100%;
        padding: var(--space-3) var(--space-4);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        font-size: var(--font-size);
        font-family: var(--font-family-sans);
        color: var(--foreground);
        background: var(--card);
        resize: vertical;
        min-height: 80px;
      }
      
      .notes-textarea:focus {
        outline: none;
        border-color: var(--foreground);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--foreground) 20%, transparent);
      }
      
      .summary-section {
        background: var(--muted);
        padding: var(--space-4);
        border-radius: var(--radius-md);
      }
      
      .summary-section h4 {
        margin: 0 0 var(--space-3) 0;
        font-size: var(--text-base);
        font-weight: var(--font-weight-medium);
        color: var(--foreground);
      }
      
      .summary-list {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
      }
      
      .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
      }
      
      .summary-label {
        font-size: var(--text-sm);
        color: var(--muted-foreground);
      }
      
      .summary-value {
        font-size: var(--text-sm);
        color: var(--foreground);
        font-weight: var(--font-weight-medium);
      }
      
      .field-group {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
      }
      
      .field-label {
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        color: var(--foreground);
        margin-bottom: 0;
      }
      
      .field-input,
      .field-select {
        height: calc(var(--space-8) + var(--space-3));
        padding: var(--space-3) var(--space-4);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        font-size: var(--font-size);
        font-family: var(--font-family-sans);
        color: var(--foreground);
        background: var(--card);
        transition: all var(--duration-fast) var(--ease-out);
        width: 100%;
        box-sizing: border-box;
      }
      
      .field-input:focus,
      .field-select:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 2px color-mix(in srgb, var(--primary) 20%, transparent);
      }
      
      .field-input::placeholder {
        color: var(--muted-foreground);
      }
      
      .field-select {
        cursor: pointer;
        background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="gray"><path d="M7 10l5 5 5-5z"/></svg>');
        background-repeat: no-repeat;
        background-position: right var(--space-4) center;
        background-size: 16px;
        appearance: none;
        padding-right: calc(var(--space-8) + var(--space-4));
        color: var(--muted-foreground);
      }
      
      .modal-footer {
        padding: var(--space-4) var(--space-6);
        background: var(--ski-light-gray);
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: var(--space-2);
      }
      
      .btn {
        height: var(--space-9);
        padding: 0 var(--space-4);
        border-radius: var(--radius-sm);
        font-size: var(--text-sm);
        font-weight: var(--font-weight-medium);
        cursor: pointer;
        border: 1px solid transparent;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all var(--duration-fast) var(--ease-out);
        font-family: var(--font-family-sans);
      }
      
      .btn-cancel {
        background: var(--card);
        color: var(--muted-foreground);
        border-color: var(--border);
      }
      
      .btn-cancel:hover {
        background: var(--muted);
        color: var(--foreground);
      }
      
      .btn-back {
        background: var(--card);
        color: var(--foreground);
        border-color: var(--border);
      }
      
      .btn-back:hover {
        background: var(--muted);
        color: var(--foreground);
      }
      
      .btn-next {
        background: var(--foreground);
        color: var(--card);
        border-color: var(--foreground);
      }
      
      .btn-next:hover {
        background: var(--ski-secondary);
        border-color: var(--ski-secondary);
      }
      
      .btn-create {
        background: #22c55e;
        color: white;
        border-color: #22c55e;
      }
      
      .btn-create:hover {
        background: #16a34a;
        border-color: #16a34a;
      }
      
      .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
      }
      
      .btn:disabled:hover {
        background: var(--ski-accent);
        border-color: var(--ski-accent);
      }

      .field-error {
        border-color: var(--destructive) !important;
      }

      .error-message {
        font-size: var(--text-sm);
        color: var(--destructive);
        margin-top: var(--space-1);
      }

      .checkbox-item {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        cursor: pointer;
        padding: var(--space-2);
        border-radius: var(--radius-sm);
        transition: background var(--duration-fast) var(--ease-out);
      }

      .checkbox-item:hover {
        background: var(--muted);
      }

      .checkbox-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        margin: 0;
        cursor: pointer;
      }

      .checkbox-mark,
      .checkbox-item span {
        font-size: var(--text-sm);
        color: var(--foreground);
      }

      .info-card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: var(--space-6);
        margin-bottom: var(--space-4);
      }

      .avatar-section {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: var(--space-2);
        margin: var(--space-4) 0 var(--space-6);
      }

      .avatar-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: var(--muted);
        color: var(--muted-foreground);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: var(--text-lg);
        font-weight: var(--font-weight-medium);
      }

      .upload-photo-btn {
        background: none;
        border: none;
        color: var(--primary);
        font-size: var(--text-sm);
        cursor: pointer;
        text-decoration: underline;
        padding: 0;
      }

      .upload-photo-btn:hover {
        color: var(--primary);
        opacity: 0.8;
      }

      .preference-checkbox {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        cursor: pointer;
        padding: var(--space-2) 0;
        position: relative;
      }

      .preference-checkbox input[type="checkbox"] {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        width: 16px;
        height: 16px;
      }

      .checkmark {
        height: 16px;
        width: 16px;
        background-color: transparent;
        border: 2px solid var(--border);
        border-radius: 2px;
        position: relative;
        transition: all var(--duration-fast) var(--ease-out);
      }

      .preference-checkbox input:checked ~ .checkmark {
        background-color: var(--primary);
        border-color: var(--primary);
      }

      .preference-checkbox input:checked ~ .checkmark:after {
        content: '';
        position: absolute;
        left: 4px;
        top: 1px;
        width: 4px;
        height: 8px;
        border: solid white;
        border-width: 0 2px 2px 0;
        transform: rotate(45deg);
      }

      .checkbox-label {
        font-size: var(--text-sm);
        color: var(--foreground);
      }
    `,
  ]
})
export class CreateClientWizardDialogComponent {
  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<CreateClientWizardDialogComponent>);
  private readonly api = inject(ApiService);
  currentStep = signal(0);
  showIdentityErrors = false;
  showContactErrors = false;
  isDragOver = false;
  uploading = false;
  avatarPreviewUrl: string | null = null;
  uploadedAvatarUrl: string | null = null;

  steps = [
    { title: 'Información Personal', description: 'Datos básicos del cliente' },
    { title: 'Dirección y Contacto', description: 'Ubicación y preferencias' },
    { title: 'Contacto de Emergencia', description: 'Persona de contacto' },
    { title: 'Configuración Final', description: 'Notas y resumen' }
  ];

  preferences = {
    email: true,
    sms: false,
    whatsapp: true,
    phone: false
  };
  constructor(@Inject(MAT_DIALOG_DATA) public data?: Partial<CreateClientWizardData>) {
    if (data) {
      this.identityForm.patchValue({
        first_name: data.first_name ?? '',
        last_name: data.last_name ?? '',
        date_of_birth: data.date_of_birth ?? '',
        gender: data.gender ?? '',
        nationality: data.nationality ?? '',
        preferred_language: data.preferred_language ?? 'es',
      });
      this.contactForm.patchValue({
        email: data.email ?? '',
        phone: data.phone ?? '',
        telephone: data.telephone ?? '',
        communication_method: data.communication_method ?? 'email',
        status: data.status ?? 'active',
      });
      this.addressForm.patchValue({
        street: data.address?.street ?? '',
        city: data.address?.city ?? '',
        postal_code: data.address?.postal_code ?? '',
        country: data.address?.country ?? '',
        emergency_name: data.emergency_contact?.name ?? '',
        emergency_phone: data.emergency_contact?.phone ?? '',
        emergency_relationship: data.emergency_contact?.relationship ?? '',
      });
      this.preferencesForm.patchValue({
        level: data.level ?? '',
        instructor_gender: data.instructor_gender ?? 'no_preference',
        group_size: data.group_size ?? 'no_preference',
        notes: data.notes ?? '',
        sendWelcome: data.sendWelcome ?? true,
      });
      if (data.avatar) {
        this.avatarPreviewUrl = data.avatar;
        this.uploadedAvatarUrl = data.avatar;
      }
    }
  }

  identityForm = this.fb.group({
    full_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: ['', Validators.required],
    client_type: ['nuevo'],
    first_name: [''],
    last_name: [''],
    date_of_birth: [''],
    gender: [''],
    nationality: [''],
    preferred_language: ['es'],
  });

  contactForm = this.fb.group({
    address: ['', Validators.required],
    language: ['es'],
    email: ['', [Validators.email]],
    phone: [''],
    telephone: [''],
    communication_method: ['email'],
    status: ['active', Validators.required],
  });

  emergencyForm = this.fb.group({
    emergency_name: ['', Validators.required],
    emergency_phone: ['', Validators.required],
    emergency_relationship: ['', Validators.required],
  });

  addressForm = this.fb.group({
    street: [''],
    city: [''],
    postal_code: [''],
    country: [''],
    emergency_name: [''],
    emergency_phone: [''],
    emergency_relationship: [''],
  });

  preferencesForm = this.fb.group({
    level: [''],
    instructor_gender: ['no_preference'],
    group_size: ['no_preference'],
    notes: [''],
    sendWelcome: [true],
  });

  genderOptions = [
    { value: '', label: '—' },
    { value: 'male', label: 'Hombre' },
    { value: 'female', label: 'Mujer' },
    { value: 'other', label: 'Otro' },
    { value: 'prefer_not_to_say', label: 'Prefiero no decir' },
  ];

  clientTypeOptions = [
    { value: 'nuevo', label: 'Nuevo' },
    { value: 'vip', label: 'VIP' },
    { value: 'habitual', label: 'Habitual' },
    { value: 'empresa', label: 'Empresa' },
  ];

  relationshipOptions = [
    { value: '', label: 'Seleccionar relación' },
    { value: 'padre', label: 'Padre' },
    { value: 'madre', label: 'Madre' },
    { value: 'conyuge', label: 'Cónyuge' },
    { value: 'hermano', label: 'Hermano/a' },
    { value: 'hijo', label: 'Hijo/a' },
    { value: 'amigo', label: 'Amigo/a' },
    { value: 'otro', label: 'Otro' },
  ];
  languageOptions = [
    { value: 'es', label: 'ES' },
    { value: 'en', label: 'EN' },
    { value: 'fr', label: 'FR' },
    { value: 'de', label: 'DE' },
    { value: 'it', label: 'IT' },
  ];
  communicationOptions = [
    { value: 'email', label: 'Email' },
    { value: 'sms', label: 'SMS' },
    { value: 'phone', label: 'Teléfono' },
    { value: 'whatsapp', label: 'WhatsApp' },
  ];
  levelOptions = [
    { value: 'Principiante', label: 'Principiante' },
    { value: 'Intermedio', label: 'Intermedio' },
    { value: 'Avanzado', label: 'Avanzado' },
    { value: 'Experto', label: 'Experto' },
  ];
  instructorGenderOptions = [
    { value: 'no_preference', label: 'Indistinto' },
    { value: 'male', label: 'Hombre' },
    { value: 'female', label: 'Mujer' },
  ];
  groupSizeOptions = [
    { value: 'no_preference', label: 'Indistinto' },
    { value: 'private', label: 'Privado' },
    { value: 'small_group', label: 'Grupo pequeño' },
    { value: 'large_group', label: 'Grupo grande' },
  ];

  getStepTitle(): string {
    return this.steps[this.currentStep()]?.title || '';
  }

  updatePreference(type: keyof typeof this.preferences, event: any): void {
    this.preferences[type] = event.target.checked;
  }

  getSummaryName(): string {
    return this.identityForm.value.full_name || '—';
  }

  getInitials(): string {
    const fullName = (this.identityForm.value.full_name || '').toString().trim();
    const nameParts = fullName.split(' ').filter(Boolean);
    if (nameParts.length >= 2) {
      return (nameParts[0][0] + nameParts[1][0]).toUpperCase();
    } else if (nameParts.length === 1) {
      return nameParts[0][0].toUpperCase();
    }
    return 'UN';
  }

  onDragOver(event: DragEvent) { event.preventDefault(); this.isDragOver = true; }
  onDragLeave(event: DragEvent) { event.preventDefault(); this.isDragOver = false; }
  onDrop(event: DragEvent) {
    event.preventDefault();
    this.isDragOver = false;
    if (!event.dataTransfer || !event.dataTransfer.files?.length) return;
    this.handleFiles(event.dataTransfer.files);
  }
  onFileInput(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files.length) this.handleFiles(input.files);
  }
  private async handleFiles(files: FileList) {
    const file = files[0];
    if (!file) return;
    // Preview
    const reader = new FileReader();
    reader.onload = () => { this.avatarPreviewUrl = reader.result as string; };
    reader.readAsDataURL(file);
    // Upload
    this.uploading = true;
    try {
      const res = await this.api.uploadFile<{ success: boolean; data: { url: string } }>(
        '/uploads/images', file, { type: 'image' }
      );
      this.uploadedAvatarUrl = res?.data?.url || null;
    } catch (e) {
      console.error('Upload failed', e);
      this.uploadedAvatarUrl = null;
    } finally {
      this.uploading = false;
    }
  }

  getSummaryEmail(): string {
    return this.identityForm.value.email || '—';
  }

  getSummaryPhone(): string {
    return this.identityForm.value.phone || '—';
  }

  getSummaryType(): string {
    const type = this.identityForm.value.client_type;
    return this.clientTypeOptions.find(opt => opt.value === type)?.label || '—';
  }

  getSummaryAddress(): string | null {
    return this.contactForm.value.address || null;
  }

  hasEmergencyContact(): boolean {
    return !!(this.emergencyForm.value.emergency_name || this.emergencyForm.value.emergency_phone);
  }

  getEmergencyContactSummary(): string {
    const name = this.emergencyForm.value.emergency_name;
    const phone = this.emergencyForm.value.emergency_phone;
    if (name && phone) return `${name} (${phone})`;
    return name || phone || '—';
  }

  next() {
    if (this.currentStep() === 0 && this.identityForm.invalid) return;
    if (this.currentStep() === 1) {
      const { email, phone, telephone } = this.contactForm.value;
      if (!email && !phone && !telephone) return;
    }
    this.currentStep.set(Math.min(3, this.currentStep() + 1));
  }
  
  prev() { 
    this.currentStep.set(Math.max(0, this.currentStep() - 1)); 
  }


  onNextClick() {
    if (this.currentStep() === 0) {
      this.showIdentityErrors = this.identityForm.invalid;
    }
    if (this.currentStep() === 1) {
      this.showContactErrors = !this.hasAtLeastOneContact();
    }
    this.next();
  }

  hasAtLeastOneContact(): boolean {
    const { email, phone, telephone } = this.contactForm.value;
    return !!(email || phone || telephone);
  }

  isNextDisabled(): boolean {
    switch (this.currentStep()) {
      case 0: 
        const identityControls = ['full_name', 'email', 'phone'];
        return identityControls.some(field => {
          const control = this.identityForm.get(field);
          return !control?.value || control?.invalid;
        });
      case 1: 
        return !this.contactForm.get('address')?.value;
      case 2: 
        const emergencyControls = ['emergency_name', 'emergency_phone', 'emergency_relationship'];
        return emergencyControls.some(field => {
          const control = this.emergencyForm.get(field);
          return !control?.value || control?.invalid;
        });
      default: return false;
    }
  }

  canCreateClient(): boolean {
    return !this.isNextDisabled();
  }

  getFieldError(fieldName: string, formGroup: string): string | null {
    let control;
    switch (formGroup) {
      case 'identity':
        control = this.identityForm.get(fieldName);
        break;
      case 'contact':
        control = this.contactForm.get(fieldName);
        break;
      case 'address':
        control = this.addressForm.get(fieldName);
        break;
      case 'emergency':
        control = this.emergencyForm.get(fieldName);
        break;
      case 'preferences':
        control = this.preferencesForm.get(fieldName);
        break;
      default:
        return null;
    }

    if (!control || !control.errors || !control.touched) {
      return null;
    }

    if (control.errors['required']) {
      return 'Este campo es obligatorio';
    }
    if (control.errors['email']) {
      return 'El email no es válido';
    }
    if (control.errors['minlength']) {
      return 'Muy corto';
    }
    if (control.errors['maxlength']) {
      return 'Muy largo';
    }

    return 'Campo inválido';
  }

  confirm() {
    if (!this.canCreateClient()) return;

    // Parse full name into first and last name
    const fullName = this.identityForm.value.full_name || '';
    const nameParts = fullName.trim().split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || '';

    const payload: CreateClientWizardData = {
      first_name: firstName,
      last_name: lastName,
      email: this.identityForm.value.email || undefined,
      phone: this.identityForm.value.phone || undefined,
      preferred_language: this.contactForm.value.language || 'es',
      address: {
        street: this.contactForm.value.address || undefined,
      },
      emergency_contact: {
        name: this.emergencyForm.value.emergency_name || undefined,
        phone: this.emergencyForm.value.emergency_phone || undefined,
        relationship: this.emergencyForm.value.emergency_relationship || undefined,
      },
      notes: this.preferencesForm.value.notes || undefined,
      sendWelcome: true,
      avatar: this.uploadedAvatarUrl || undefined,
    } as CreateClientWizardData;

    this.dialogRef.close(payload);
  }
}
