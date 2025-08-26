import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { CdkDrag } from '@angular/cdk/drag-drop';
import { Instructor, InstructorAvailabilityService } from '../services/instructor-availability.service';
import { Observable } from 'rxjs';

@Component({
  selector: 'app-instructor-sidebar',
  standalone: true,
  imports: [CommonModule, CdkDrag],
  template: `
    <div class="instructor-sidebar">
      <div
        class="instructor-item"
        *ngFor="let instructor of instructors$ | async"
        cdkDrag
        [cdkDragData]="instructor"
      >
        <span class="status" [class.available]="instructor.available"></span>
        <img [src]="instructor.avatar" class="avatar" alt="Instructor" />
        <span class="name">{{ instructor.name }}</span>
      </div>
    </div>
  `,
  styles: [
    `
      .instructor-sidebar {
        width: 220px;
        border-right: 1px solid var(--border);
        padding: var(--space-2);
        background: var(--surface);
      }

      .instructor-item {
        display: flex;
        align-items: center;
        padding: var(--space-2);
        cursor: grab;
        border-radius: var(--radius-2);
      }

      .instructor-item + .instructor-item {
        margin-top: var(--space-2);
      }

      .instructor-item:hover {
        background: var(--surface-2);
      }

      .avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        margin-right: var(--space-2);
      }

      .status {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: var(--space-2);
        background: var(--red-500, #ef4444);
      }

      .status.available {
        background: var(--green-500, #22c55e);
      }
    `,
  ],
})
export class InstructorSidebarComponent {
  instructors$: Observable<Instructor[]> = this.availability.getInstructors();

  constructor(private availability: InstructorAvailabilityService) {}
}

