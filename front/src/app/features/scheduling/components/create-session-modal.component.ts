import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, OnChanges, Output, SimpleChanges } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { SchedulingSession } from './session-detail-modal.component';

@Component({
  selector: 'app-create-session-modal',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="modal-overlay" (click)="cancel.emit()">
      <div class="modal-container" (click)="$event.stopPropagation()">
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
            <button type="button" (click)="cancel.emit()">Cancel</button>
            <button type="submit" [disabled]="form.invalid">Create</button>
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
