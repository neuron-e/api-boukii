import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

export interface SelectOption { value: string; label: string; }

@Component({
  selector: 'ui-select',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <label class="ui-select" [class.full]="fullWidth">
      <span *ngIf="label" class="label">{{ label }}</span>
      <select class="control" [(ngModel)]="model" (ngModelChange)="valueChange.emit($event)" [attr.aria-label]="ariaLabel || label || 'Select'">
        <option *ngIf="placeholder" value="" disabled selected hidden>{{ placeholder }}</option>
        <option *ngFor="let opt of options" [value]="opt.value">{{ opt.label }}</option>
      </select>
    </label>
  `,
  styles: [
    `
      .ui-select { display: inline-flex; flex-direction: column; gap: var(--space-2); }
      .ui-select.full { width: 100%; }
      .label { font-size: var(--text-xs); color: #64748b; font-weight: 500; }
      .control {
        height: 40px; padding: 0 32px 0 var(--space-3); box-sizing: border-box; min-width: 160px;
        border: 1px solid #e2e8f0; border-radius: 8px; appearance: none;
        background: #ffffff; color: #1e293b;
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
        background-position: right 12px center; background-repeat: no-repeat; background-size: 16px;
        transition: all var(--duration-fast) var(--ease-out);
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
      }
      .control:hover { border-color: #cbd5e1; }
      .control:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent); }
    `,
  ],
})
export class UISelectComponent implements OnInit {
  @Input() options: SelectOption[] = [];
  @Input() placeholder?: string;
  @Input() label?: string;
  @Input() ariaLabel?: string;
  @Input() fullWidth = false;
  @Input() value: string | null = null;
  @Output() valueChange = new EventEmitter<string>();
  model: string | null = null;
  ngOnInit(): void { this.model = this.value ?? null; }
}
