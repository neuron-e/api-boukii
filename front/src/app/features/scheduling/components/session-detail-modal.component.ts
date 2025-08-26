import { CommonModule } from '@angular/common';
import { Component, EventEmitter, Input, Output } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { animate, style, transition, trigger } from '@angular/animations';

export interface SchedulingSession {
  course: string;
  instructor: string;
  startTime: string;
  endTime: string;
}

@Component({
  selector: 'app-session-detail-modal',
  standalone: true,
  imports: [CommonModule, MatButtonModule],
  template: `
    <div class="modal-overlay" (click)="close.emit()" [@fade]>
      <div class="modal-container" (click)="$event.stopPropagation()" [@scale]>
        <h2>Session Details</h2>
        <div class="details">
          <p><strong>Course:</strong> {{ session?.course }}</p>
          <p><strong>Instructor:</strong> {{ session?.instructor }}</p>
          <p><strong>Time:</strong> {{ session?.startTime }} - {{ session?.endTime }}</p>
        </div>
        <div class="actions">
          <button mat-stroked-button type="button" (click)="edit.emit(session)">Edit</button>
          <button mat-stroked-button type="button" (click)="reschedule.emit(session)">Reschedule</button>
          <button mat-stroked-button color="warn" type="button" (click)="remove.emit(session)">Delete</button>
          <button mat-flat-button color="primary" type="button" (click)="close.emit()">Close</button>
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
export class SessionDetailModalComponent {
  @Input() session: SchedulingSession | null = null;
  @Output() edit = new EventEmitter<SchedulingSession | null>();
  @Output() reschedule = new EventEmitter<SchedulingSession | null>();
  @Output() remove = new EventEmitter<SchedulingSession | null>();
  @Output() close = new EventEmitter<void>();
}
