import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, Validators } from '@angular/forms';

import { TranslatePipe } from '@shared/pipes/translate.pipe';

@Component({
  selector: 'app-client-form-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  templateUrl: './client-form.page.html',
  styleUrls: ['./client-form.page.scss']
})
export class ClientFormPageComponent {
  private readonly fb = inject(FormBuilder);

  submitted = false;

  clientForm = this.fb.group({
    first_name: ['', Validators.required],
    last_name: ['', Validators.required],
    email: ['', [Validators.required, Validators.email]],
    phone: ['']
  });

  submit(): void {
    this.submitted = true;
    if (this.clientForm.invalid) {
      this.clientForm.markAllAsTouched();
      return;
    }

    // Placeholder for save logic
    console.log('Client form submitted', this.clientForm.value);
  }
}
