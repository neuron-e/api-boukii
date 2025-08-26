import { Component, Inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { Monitor } from './monitors-mock.service';

@Component({
  selector: 'app-monitor-profile',
  standalone: true,
  imports: [CommonModule, MatDialogModule, MatButtonModule],
  template: `
    <h2 mat-dialog-title>{{ data.name }}</h2>
    <div mat-dialog-content>
      <p><strong>Sports:</strong> {{ data.sports.join(', ') }}</p>
      <p><strong>Levels:</strong> {{ data.levels.join(', ') }}</p>
      <p><strong>Status:</strong> {{ data.status }}</p>
    </div>
    <div mat-dialog-actions>
      <button mat-button mat-dialog-close>Close</button>
    </div>
  `,
})
export class MonitorProfileComponent {
  constructor(@Inject(MAT_DIALOG_DATA) public data: Monitor) {}
}

