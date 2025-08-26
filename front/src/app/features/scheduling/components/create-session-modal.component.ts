import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { animate, style, transition, trigger } from '@angular/animations';
import { SchedulingSession } from './session-detail-modal.component';

@Component({
  selector: 'app-create-session-modal',
  standalone: true,
  imports: [CommonModule, FormsModule, MatButtonModule],
  template: `
    <div class="modal-overlay" (click)="cancel.emit()" [@fade]>
      <div class="modal-container" (click)="$event.stopPropagation()" [@scale]>
        <h2>Create Session</h2>
        <form (ngSubmit)="onSubmit()" #form="ngForm">
          <label>
            Course
            <input name="course" [(ngModel)]="session.course" required />
          </label>
          <label>
            Instructor
            <input name="instructor" [(ngModel)]="session.instructor" required />
          </label>
          <label>
            Start Time
            <input name="startTime" type="time" [(ngModel)]="session.startTime" required />
          </label>
          <label>
            End Time
            <input name="endTime" type="time" [(ngModel)]="session.endTime" required />
          </label>
          <div class="actions">
            <button mat-stroked-button type="button" (click)="cancel.emit()">Cancel</button>
            <button mat-flat-button color="primary" type="submit" [disabled]="form.invalid">Create</button>
          </div>
        </form>
      </div>
    </div>
  `,
  styles: [
    `
      .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
      }
      .modal-container {
        background: var(--surface);
        padding: var(--space-4);
        border-radius: var(--radius-2);
        width: 320px;
      }
      form {
        display: flex;
        flex-direction: column;
        gap: var(--space-2);
      }
      .actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-2);
        margin-top: var(--space-4);
      }
    `,
  ],
  animations: [
    trigger('fade', [
      transition(':enter', [
        style({ opacity: 0 }),
        animate('200ms ease-out', style({ opacity: 1 })),
      ]),
      transition(':leave', [
        animate('200ms ease-in', style({ opacity: 0 })),
      ]),
    ]),
    trigger('scale', [
      transition(':enter', [
        style({ transform: 'scale(0.95)', opacity: 0 }),
        animate('200ms ease-out', style({ transform: 'scale(1)', opacity: 1 })),
      ]),
      transition(':leave', [
        animate('200ms ease-in', style({ transform: 'scale(0.95)', opacity: 0 })),
      ]),
    ]),
  ],
})
export class CreateSessionModalComponent implements OnChanges {
  @Input() day = '';
  @Input() startTime = '';
  @Output() create = new EventEmitter<SchedulingSession>();
  @Output() cancel = new EventEmitter<void>();

  session: SchedulingSession = {
    course: '',
    instructor: '',
    startTime: this.startTime,
    endTime: '',
  };

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['startTime']) {
      this.session.startTime = this.startTime;
    }
  }

  onSubmit(): void {
    this.create.emit({ ...this.session });
  }
}
