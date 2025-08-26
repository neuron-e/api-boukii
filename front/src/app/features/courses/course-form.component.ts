import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import {
  FormArray,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';

@Component({
  selector: 'app-course-form',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  templateUrl: './course-form.component.html',
  styleUrls: ['./course-form.component.scss'],
})
export class CourseFormComponent {
  private readonly fb = inject(FormBuilder);

  sports = ['Ski', 'Snowboard'];
  types: Array<'collective' | 'private'> = ['collective', 'private'];
  levels = ['Beginner', 'Intermediate', 'Advanced'];
  extras = ['Equipment', 'Insurance', 'Lunch'];

  currentStep = 0;
  lastStep = 5;

  courseForm = this.fb.group({
    sport: ['', Validators.required],
    type: ['collective', Validators.required],
    name: ['', Validators.required],
    description: ['', Validators.required],
    flexible: [false],
    date: ['', Validators.required],
    groupSize: [null],
    level: [''],
    extras: this.fb.array(this.extras.map(() => this.fb.control(false))),
    translated: [''],
  });

  constructor() {
    this.courseForm
      .get('type')!
      .valueChanges.subscribe((t: string | null) => {
        if (!t || (t !== 'collective' && t !== 'private')) return;
        const groupSize = this.courseForm.get('groupSize')!;
        const level = this.courseForm.get('level')!;
        if (t === 'collective') {
          groupSize.addValidators(Validators.required);
          level.addValidators(Validators.required);
        } else {
          groupSize.clearValidators();
          level.clearValidators();
          groupSize.reset();
          level.reset();
        }
        groupSize.updateValueAndValidity();
        level.updateValueAndValidity();
      });
  }

  get extrasArray(): FormArray {
    return this.courseForm.get('extras') as FormArray;
  }

  nextStep(): void {
    if (!this.isStepValid(this.currentStep)) {
      this.markStepTouched(this.currentStep);
      return;
    }
    this.currentStep++;
    if (
      this.currentStep === 3 &&
      this.courseForm.get('type')!.value !== 'collective'
    ) {
      this.currentStep++;
    }
  }

  prevStep(): void {
    this.currentStep--;
    if (
      this.currentStep === 3 &&
      this.courseForm.get('type')!.value !== 'collective'
    ) {
      this.currentStep--;
    }
  }

  isStepValid(step: number): boolean {
    switch (step) {
      case 0:
        return (
          this.courseForm.get('sport')!.valid &&
          this.courseForm.get('type')!.valid
        );
      case 1:
        return (
          this.courseForm.get('name')!.valid &&
          this.courseForm.get('description')!.valid
        );
      case 2:
        return this.courseForm.get('date')!.valid;
      case 3:
        if (this.courseForm.get('type')!.value !== 'collective') {
          return true;
        }
        return (
          this.courseForm.get('groupSize')!.valid &&
          this.courseForm.get('level')!.valid
        );
      default:
        return true;
    }
  }

  markStepTouched(step: number): void {
    const controls = this.getStepControls(step);
    controls.forEach((name) => this.courseForm.get(name)?.markAsTouched());
  }

  getStepControls(step: number): string[] {
    switch (step) {
      case 0:
        return ['sport', 'type'];
      case 1:
        return ['name', 'description'];
      case 2:
        return ['date'];
      case 3:
        return ['groupSize', 'level'];
      default:
        return [];
    }
  }

  translate(): void {
    const desc = this.courseForm.get('description')!.value;
    this.courseForm.get('translated')!.setValue(desc);
  }

  save(): void {
    console.log(this.courseForm.value);
  }
}

