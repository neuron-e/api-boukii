import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-page-layout',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page-container">
      <ng-content></ng-content>
    </div>
  `,
  styles: [`
    .page-container {
      @apply p-4 max-w-7xl mx-auto;
    }
    
    :host {
      @apply block w-full h-full;
    }
  `]
})
export class PageLayoutComponent {}