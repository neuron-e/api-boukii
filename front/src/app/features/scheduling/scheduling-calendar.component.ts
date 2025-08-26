import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-scheduling-calendar',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page" data-cy="scheduling-calendar">
      <div class="page-header">
        <h1>Scheduling Calendar</h1>
      </div>
      <div class="page-content">
        <div class="calendar">
          <div class="day-header" *ngFor="let day of days">{{ day }}</div>
          <ng-container *ngFor="let hour of hours">
            <div class="time-slot" *ngFor="let day of days"></div>
          </ng-container>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        background: var(--surface);
        border: 1px solid var(--border);
      }

      .day-header {
        padding: var(--space-2);
        text-align: center;
        font-weight: 500;
        background: var(--surface-2);
        border-right: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
      }

      .time-slot {
        min-height: 40px;
        border-right: 1px solid var(--border);
        border-bottom: 1px solid var(--border);
      }

      .calendar > :nth-child(7n) {
        border-right: none;
      }
    `,
  ],
})
export class SchedulingCalendarComponent {
  @Input() startHour = 7;
  @Input() endHour = 21;

  days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

  get hours(): number[] {
    return Array.from({ length: this.endHour - this.startHour }, (_, i) => this.startHour + i);
  }
}
