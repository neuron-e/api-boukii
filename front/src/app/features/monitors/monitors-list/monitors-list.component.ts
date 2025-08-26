import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTableModule } from '@angular/material/table';
import { MatSelectModule } from '@angular/material/select';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatButtonModule } from '@angular/material/button';
import { MonitorsMockService, Monitor } from '../monitors-mock.service';
import { MonitorProfileComponent } from '../monitor-profile/monitor-profile.component';

@Component({
  selector: 'app-monitors-list',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    MatTabsModule,
    MatTableModule,
    MatSelectModule,
    MatDialogModule,
    MatButtonModule,
  ],
  templateUrl: './monitors-list.component.html',
  styleUrls: ['./monitors-list.component.scss'],
})
export class MonitorsListComponent {
  displayedColumns = ['name', 'sports', 'levels', 'status'];
  monitors: Monitor[] = this.monitorsService.getMonitors();
  statusFilter: 'active' | 'inactive' | 'all' = 'active';
  selectedSport = '';

  constructor(
    private monitorsService: MonitorsMockService,
    private dialog: MatDialog,
  ) {}

  get sports(): string[] {
    return Array.from(new Set(this.monitors.flatMap((m) => m.sports)));
  }

  get filteredMonitors(): Monitor[] {
    return this.monitors.filter(
      (m) =>
        (this.statusFilter === 'all' || m.status === this.statusFilter) &&
        (!this.selectedSport || m.sports.includes(this.selectedSport)),
    );
  }

  onTabChange(index: number): void {
    this.statusFilter = index === 0 ? 'active' : index === 1 ? 'inactive' : 'all';
  }

  openMonitor(monitor: Monitor): void {
    this.dialog.open(MonitorProfileComponent, { data: monitor });
  }
}

