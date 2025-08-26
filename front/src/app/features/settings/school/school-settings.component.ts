import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule } from '@angular/forms';
import { SettingsService } from '../settings.service';
import { TranslatePipe } from '@shared/pipes/translate.pipe';

@Component({
  selector: 'app-school-settings',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  templateUrl: './school-settings.component.html',
  styleUrl: './school-settings.component.scss'
})
export class SchoolSettingsComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly settingsService = inject(SettingsService);

  readonly form: FormGroup = this.fb.group({
    name: [''],
    email: [''],
    contact_phone: [''],
    address: [''],
    logo: ['']
  });

  ngOnInit(): void {
    this.settingsService.getMockSchool().subscribe(school => {
      this.form.patchValue(school);
    });
  }

  save(): void {
    console.log(this.form.value);
  }
}
