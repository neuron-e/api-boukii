import { Component, Input, Optional, Inject, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute } from '@angular/router';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { MonitorsMockService, Monitor } from '../monitors-mock.service';

interface MonitorDetails extends Monitor {
  email?: string;
  phone?: string;
  salary?: Record<string, number>;
}

@Component({
  selector: 'app-monitor-profile',
  standalone: true,
  imports: [
    CommonModule,
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatTableModule,
  ],
  templateUrl: './monitor-profile.component.html',
  styleUrls: ['./monitor-profile.component.scss'],
})
export class MonitorProfileComponent implements OnInit {
  @Input() monitor?: MonitorDetails;

  form!: FormGroup;

  seasons = ['winter', 'spring', 'summer', 'autumn'];
  salaryColumns = ['season', 'amount'];

  availabilityColumns = ['day', 'hours'];
  availabilityData = [
    { day: 'Mon', hours: '9-12, 15-18' },
    { day: 'Tue', hours: '9-12' },
    { day: 'Wed', hours: '9-12, 15-18' },
    { day: 'Thu', hours: '10-14' },
    { day: 'Fri', hours: '9-12' },
  ];

  stats = { hours: 120, rating: 4.5 };

  allSports = ['Tennis', 'Swimming', 'Basketball', 'Soccer', 'Running'];
  allLevels = ['Beginner', 'Intermediate', 'Advanced'];

  constructor(
    private fb: FormBuilder,
    private monitorsService: MonitorsMockService,
    @Optional() private route?: ActivatedRoute,
    @Optional() @Inject(MAT_DIALOG_DATA) data?: MonitorDetails,
  ) {
    if (data) {
      this.monitor = data;
    }
  }

  ngOnInit(): void {
    if (!this.monitor && this.route) {
      const id = Number(this.route.snapshot.paramMap.get('id'));
      this.monitor = this.monitorsService
        .getMonitors()
        .find((m) => m.id === id);
    }

    this.form = this.fb.group({
      name: [this.monitor?.name || ''],
      email: [this.monitor?.email || ''],
      phone: [this.monitor?.phone || ''],
      sports: [this.monitor?.sports || []],
      levels: [this.monitor?.levels || []],
      salary: this.fb.group(
        this.seasons.reduce((group, s) => {
          group[s] = [this.monitor?.salary?.[s] || 0];
          return group;
        }, {} as Record<string, any>),
      ),
    });
  }

  get salaryGroup(): FormGroup {
    return this.form.get('salary') as FormGroup;
  }
}

