import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-session-block',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="session-block" [ngClass]="status" [attr.title]="tooltip">
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
})
export class SessionBlockComponent {
  @Input() courseName = '';
  @Input() instructorAvatar = '';
  @Input() startTime = '';
  @Input() endTime = '';
  @Input() status: 'confirmed' | 'pending' | 'conflict' = 'pending';

  get tooltip(): string {
    return `${this.courseName} | ${this.startTime}-${this.endTime} (${this.status})`;
  }
}

