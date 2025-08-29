import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

export interface BreadcrumbItem { label: string; link?: string | any[]; }

@Component({
  selector: 'ui-breadcrumb',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <nav class="ui-breadcrumb" aria-label="breadcrumb">
      <ng-container *ngFor="let item of items; let last = last">
        <a *ngIf="!last && item.link" [routerLink]="item.link" class="crumb">{{ item.label }}</a>
        <span *ngIf="!last && !item.link" class="crumb">{{ item.label }}</span>
        <span *ngIf="!last" class="sep">/</span>
        <span *ngIf="last" class="crumb current" aria-current="page">{{ item.label }}</span>
      </ng-container>
    </nav>
  `,
  styles: [
    `
      .ui-breadcrumb { display: inline-flex; align-items: center; gap: var(--space-2); color: var(--color-text-secondary); font-size: var(--text-sm); }
      .crumb { color: inherit; text-decoration: none; }
      .crumb:hover { color: var(--color-text-primary); }
      .current { color: var(--color-text-primary); font-weight: 600; }
      .sep { color: var(--color-text-tertiary); }
    `,
  ],
})
export class UIBreadcrumbComponent { @Input() items: BreadcrumbItem[] = []; }

