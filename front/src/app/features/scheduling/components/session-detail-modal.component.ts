import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';

export interface SchedulingSession {
  course: string;
  instructor: string;
  startTime: string;
  endTime: string;
}

@Component({
  selector: 'app-session-detail-modal',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="modal-overlay" (click)="close.emit()">
      <div class="modal-container" (click)="$event.stopPropagation()">
        <h2>Session Details</h2>
        <div class="details">
          <p><strong>Course:</strong> {{ session?.course }}</p>
          <p><strong>Instructor:</strong> {{ session?.instructor }}</p>
          <p><strong>Time:</strong> {{ session?.startTime }} - {{ session?.endTime }}</p>
        </div>
        <div class="actions">
          <button type="button" (click)="edit.emit(session)">Edit</button>
          <button type="button" (click)="reschedule.emit(session)">Reschedule</button>
          <button type="button" (click)="remove.emit(session)">Delete</button>
          <button type="button" (click)="close.emit()">Close</button>
        </div>
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
      .actions {
        display: flex;
        justify-content: flex-end;
        gap: var(--space-2);
        margin-top: var(--space-4);
      }
    `,
  ],
})
export class SessionDetailModalComponent {
  @Input() session: SchedulingSession | null = null;
  @Output() edit = new EventEmitter<SchedulingSession | null>();
  @Output() reschedule = new EventEmitter<SchedulingSession | null>();
  @Output() remove = new EventEmitter<SchedulingSession | null>();
  @Output() close = new EventEmitter<void>();
}
