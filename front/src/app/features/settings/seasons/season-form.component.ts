import { Component, Input, Output, EventEmitter, inject, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Season } from '../data/seasons';

@Component({
  selector: 'app-season-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="modal-overlay" (click)="onCancel()">
      <div class="modal-content" (click)="$event.stopPropagation()">
        <h2>{{ season ? 'Editar Temporada' : 'Nueva Temporada' }}</h2>
        <form [formGroup]="form" (ngSubmit)="onSubmit()">
          <label>
            Nombre
            <input formControlName="name" />
          </label>
          <label>
            Fecha Inicio
            <input type="date" formControlName="start_date" />
          </label>
          <label>
            Fecha Fin
            <input type="date" formControlName="end_date" />
          </label>
          <label>
            Activa
            <input type="checkbox" formControlName="is_active" />
          </label>
          <div class="actions">
            <button type="submit" [disabled]="form.invalid">Guardar</button>
            <button type="button" (click)="onCancel()">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  `,
  styles: [
    `
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background: #fff;
      padding: 1rem;
      border-radius: 4px;
      min-width: 300px;
    }
    label {
      display: flex;
      flex-direction: column;
      margin-bottom: 0.5rem;
    }
    .actions {
      margin-top: 1rem;
      display: flex;
      gap: 0.5rem;
    }
    `
  ]
})
export class SeasonFormComponent implements OnChanges {
  private fb = inject(FormBuilder);

  @Input() season: Season | null = null;
  @Output() save = new EventEmitter<Season>();
  @Output() cancel = new EventEmitter<void>();

  form: FormGroup = this.fb.group({
    name: ['', Validators.required],
    start_date: ['', Validators.required],
    end_date: ['', Validators.required],
    is_active: [false]
  });

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['season']) {
      if (this.season) {
        this.form.patchValue(this.season);
      } else {
        this.form.reset({ name: '', start_date: '', end_date: '', is_active: false });
      }
    }
  }

  onSubmit(): void {
    if (this.form.valid) {
      const season: Season = { ...(this.season ?? {}), ...this.form.value };
      this.save.emit(season);
    }
  }

  onCancel(): void {
    this.cancel.emit();
  }
}
