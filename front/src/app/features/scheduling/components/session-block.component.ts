import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CdkDrag, CdkDragEnd } from '@angular/cdk/drag-drop';
import {
  AnimationBuilder,
  animate,
  state,
  style,
  transition,
  trigger,
} from '@angular/animations';
import { SessionValidationService } from '../services/session-validation.service';

@Component({
  selector: 'app-session-block',
  standalone: true,
  imports: [CommonModule, CdkDrag],
  template: `
    <div
      cdkDrag
      [attr.title]="tooltip"
      class="session-block"
      [ngClass]="status"
      [@dragAnimation]="dragging ? 'dragging' : 'dropped'"
      (cdkDragStarted)="dragging = true"
      (cdkDragEnded)="onDrop($event)"
    >
      <img class="avatar" [src]="instructorAvatar" alt="Instructor" />
      <div class="info">
        <div class="course">{{ courseName }}</div>
        <div class="time">{{ startTime }} - {{ endTime }}</div>
      </div>
    </div>
  `,
  styles: [
    `
      .session-block {
        display: flex;
        align-items: center;
        padding: var(--space-2);
        border-radius: var(--radius-2);
        color: var(--text-inverse);
        font-size: var(--font-size-sm);
      }

      .session-block.confirmed {
        background: var(--green-500, #22c55e);
      }

      .session-block.pending {
        background: var(--orange-400, #fb923c);
      }

      .session-block.conflict {
        background: var(--red-500, #ef4444);
      }

      .avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        margin-right: var(--space-2);
      }

      .course {
        font-weight: 600;
      }

      .time {
        font-size: 0.75rem;
      }
    `,
  ],
  animations: [
    trigger('dragAnimation', [
      state(
        'dragging',
        style({ transform: 'scale(1.05)', boxShadow: '0 2px 6px rgba(0,0,0,0.2)' }),
      ),
      state('dropped', style({ transform: 'scale(1)', boxShadow: 'none' })),
      transition('dragging <=> dropped', [animate('200ms ease-in-out')]),
    ]),
  ],
})
export class SessionBlockComponent {
  @Input() courseName = '';
  @Input() instructorAvatar = '';
  @Input() startTime = '';
  @Input() endTime = '';
  @Input() status: 'confirmed' | 'pending' | 'conflict' = 'pending';

  @Output() sessionDrop = new EventEmitter<{
    startTime: string;
    endTime: string;
    status: 'confirmed' | 'conflict';
  }>();

  dragging = false;

  constructor(
    private validator: SessionValidationService,
    private builder: AnimationBuilder,
  ) {}

  onDrop(event: CdkDragEnd): void {
    this.dragging = false;
    const minutesMoved = Math.round(event.distance.y / 50) * 30;

    const startDate = this.parseTime(this.startTime);
    const endDate = this.parseTime(this.endTime);
    startDate.setMinutes(startDate.getMinutes() + minutesMoved);
    endDate.setMinutes(endDate.getMinutes() + minutesMoved);

    const status = this.validator.checkAvailability(startDate, endDate);

    this.startTime = this.formatTime(startDate);
    this.endTime = this.formatTime(endDate);
    this.status = status;

    this.sessionDrop.emit({
      startTime: this.startTime,
      endTime: this.endTime,
      status,
    });

    const player = this.builder
      .build([
        style({ transform: `translate(${event.distance.x}px, ${event.distance.y}px)` }),
        animate('200ms ease-out', style({ transform: 'translate(0,0)' })),
      ])
      .create(event.source.element.nativeElement);
    player.onDone(() => event.source.reset());
    player.play();
  }

  private parseTime(time: string): Date {
    const [hours, minutes] = time.split(':').map(Number);
    const date = new Date();
    date.setHours(hours, minutes, 0, 0);
    return date;
  }

  private formatTime(date: Date): string {
    return `${date.getHours().toString().padStart(2, '0')}:${date
      .getMinutes()
      .toString()
      .padStart(2, '0')}`;
  }

  get tooltip(): string {
    return `${this.courseName} | ${this.startTime}-${this.endTime} (${this.status})`;
  }
}

