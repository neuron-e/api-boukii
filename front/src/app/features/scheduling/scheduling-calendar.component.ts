import { Component } from '@angular/core';
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
        <p>Scheduling calendar works!</p>
      </div>
    </div>
  `
})
export class SchedulingCalendarComponent {}
