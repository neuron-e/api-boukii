import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { MatButtonModule } from '@angular/material/button';
import { MatIconModule } from '@angular/material/icon';

@Component({
  selector: 'app-empty-state',
  standalone: true,
  imports: [CommonModule, MatButtonModule, MatIconModule],
  template: `
    <div class="empty-state">
      <div class="empty-icon">
        <mat-icon [svgIcon]="icon" *ngIf="useSvgIcon; else regularIcon"></mat-icon>
        <ng-template #regularIcon>
          <mat-icon>{{ icon }}</mat-icon>
        </ng-template>
      </div>
      
      <h3 class="empty-title">{{ title }}</h3>
      
      <p *ngIf="description" class="empty-description">
        {{ description }}
      </p>
      
      <div *ngIf="showAction" class="empty-actions">
        <button 
          mat-raised-button 
          [color]="actionColor" 
          (click)="onActionClick()"
          [disabled]="actionDisabled">
          <mat-icon *ngIf="actionIcon">{{ actionIcon }}</mat-icon>
          {{ actionText }}
        </button>
        
        <button 
          *ngIf="secondaryActionText"
          mat-stroked-button
          (click)="onSecondaryActionClick()"
          class="ml-2">
          {{ secondaryActionText }}
        </button>
      </div>
    </div>
  `,
  styles: [`
    .empty-state {
      @apply flex flex-col items-center justify-center text-center py-12 px-4;
    }
    
    .empty-icon {
      @apply mb-6;
    }
    
    .empty-icon mat-icon {
      @apply text-6xl text-gray-400;
      font-size: 64px;
      width: 64px;
      height: 64px;
    }
    
    .empty-title {
      @apply text-xl font-semibold text-gray-900 mb-2;
    }
    
    .empty-description {
      @apply text-gray-600 mb-6 max-w-md;
    }
    
    .empty-actions {
      @apply flex flex-wrap justify-center gap-2;
    }
  `]
})
export class EmptyStateComponent {
  @Input() icon: string = 'inbox';
  @Input() useSvgIcon: boolean = false;
  @Input() title: string = 'No hay elementos';
  @Input() description: string = '';
  @Input() showAction: boolean = false;
  @Input() actionText: string = 'Crear nuevo';
  @Input() actionIcon: string = 'add';
  @Input() actionColor: 'primary' | 'accent' | 'warn' = 'primary';
  @Input() actionDisabled: boolean = false;
  @Input() secondaryActionText: string = '';
  
  @Output() actionClick = new EventEmitter<void>();
  @Output() secondaryActionClick = new EventEmitter<void>();
  
  onActionClick(): void {
    this.actionClick.emit();
  }
  
  onSecondaryActionClick(): void {
    this.secondaryActionClick.emit();
  }
}