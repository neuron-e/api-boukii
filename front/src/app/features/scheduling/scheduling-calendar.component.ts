import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { SessionBlockComponent } from './components/session-block.component';
import { SessionDetailModalComponent, SchedulingSession } from './components/session-detail-modal.component';
import { CreateSessionModalComponent } from './components/create-session-modal.component';
import { InstructorSidebarComponent } from './components/instructor-sidebar.component';
import { Instructor } from './services/instructor-availability.service';

@Component({
  selector: 'app-scheduling-calendar',
  standalone: true,
  imports: [
    CommonModule,
    SessionBlockComponent,
    SessionDetailModalComponent,
    CreateSessionModalComponent,
    InstructorSidebarComponent,
  ],
  template: `
    <div class="page" data-cy="scheduling-calendar">
      <div class="page-header">
        <h1>Scheduling Calendar</h1>
      </div>
      <div class="page-content">
        <app-instructor-sidebar></app-instructor-sidebar>
        <div class="calendar">
          <div class="day-header" *ngFor="let day of days">{{ day }}</div>
          <ng-container *ngFor="let hour of hours">
            <div
              class="time-slot"
              *ngFor="let day of days"
              (dblclick)="onEmptySlotDblClick(day, hour)"
            >
              <ng-container *ngIf="getSession(day, hour) as session">
                <app-session-block
                  [courseName]="session.course"
                  [instructorAvatar]="session.instructorAvatar"
                  [instructor]="session.instructor"
                  [startTime]="session.startTime"
                  [endTime]="session.endTime"
                  [status]="session.status"
                  (sessionClick)="openSessionDetail(session)"
                  (instructorAssigned)="assignInstructor(session, $event)"
                ></app-session-block>
              </ng-container>
            </div>
          </ng-container>
        </div>
      </div>
    </div>
    <app-session-detail-modal
      *ngIf="selectedSession"
      [session]="selectedSession"
      (close)="selectedSession = null"
    ></app-session-detail-modal>
    <app-create-session-modal
      *ngIf="creatingSlot"
      [startTime]="creatingSlot.startTime"
      (create)="addSession($event)"
      (cancel)="creatingSlot = null"
    ></app-create-session-modal>
  `,
  styles: [
    `
      .page-content {
        display: flex;
      }

      .calendar {
        flex: 1;
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

  sessions: (SchedulingSession & {
    day: string;
    instructorAvatar: string;
    status: 'confirmed' | 'pending' | 'conflict';
  })[] = [
    {
      course: 'Math 101',
      instructor: 'John Doe',
      startTime: '09:00',
      endTime: '10:00',
      day: 'Monday',
      instructorAvatar: 'https://via.placeholder.com/24',
      status: 'confirmed',
    },
  ];

  selectedSession: (SchedulingSession & {
    day: string;
    instructorAvatar: string;
    status: 'confirmed' | 'pending' | 'conflict';
  }) | null = null;

  creatingSlot: { day: string; startTime: string } | null = null;

  get hours(): number[] {
    return Array.from({ length: this.endHour - this.startHour }, (_, i) => this.startHour + i);
  }

  getSession(day: string, hour: number) {
    return this.sessions.find(
      (s) =>
        s.day === day && parseInt(s.startTime.split(':')[0], 10) === hour,
    );
  }

  openSessionDetail(session: any) {
    this.selectedSession = session;
  }

  onEmptySlotDblClick(day: string, hour: number) {
    this.creatingSlot = { day, startTime: `${hour.toString().padStart(2, '0')}:00` };
  }

  addSession(session: SchedulingSession) {
    if (this.creatingSlot) {
      this.sessions.push({
        ...session,
        day: this.creatingSlot.day,
        instructorAvatar: '',
        instructor: '',
        status: 'pending',
      });
      this.creatingSlot = null;
    }
  }

  assignInstructor(session: any, instructor: Instructor) {
    session.instructor = instructor.name;
    session.instructorAvatar = instructor.avatar;
  }
}
