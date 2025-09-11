import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

@Component({
  selector: 'app-loader',
  standalone: true,
  imports: [CommonModule, MatProgressSpinnerModule],
  template: `
    <div class="loader-container" [class]="containerClass">
      <mat-spinner [diameter]="size" [color]="color"></mat-spinner>
      <p *ngIf="message" class="loader-message">{{ message }}</p>
    </div>
  `,
  styles: [`
    .loader-container {
      @apply flex flex-col items-center justify-center py-8;
    }
    
    .loader-message {
      @apply mt-4 text-gray-600 text-center;
    }
  `]
})
export class LoaderComponent {
  @Input() size: number = 40;
  @Input() color: 'primary' | 'accent' | 'warn' = 'primary';
  @Input() message: string = '';
  @Input() containerClass: string = '';
}