import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'ui-checkbox',
  standalone: true,
  imports: [CommonModule],
  template: `
    <label class="ui-checkbox">
      <input type="checkbox" class="control" [checked]="checked" (change)="onChange($event)" />
      <span class="box" aria-hidden="true"></span>
      <span class="label" *ngIf="label">{{ label }}</span>
    </label>
  `,
  styles: [
    `
      .ui-checkbox { display: inline-flex; align-items: center; gap: var(--space-2); cursor: pointer; }
      .control { position: absolute; opacity: 0; width: 0; height: 0; }
      .box { width: 16px; height: 16px; border-radius: 4px; border: 1px solid var(--color-border); background: var(--color-surface); display: inline-block; position: relative; }
      .control:focus + .box { outline: 2px solid var(--color-primary-focus); outline-offset: 2px; }
      .control:checked + .box { background: var(--color-primary); border-color: var(--color-primary); }
      .control:checked + .box::after { content: ''; position: absolute; left: 4px; top: 1px; width: 4px; height: 8px; border: solid white; border-width: 0 2px 2px 0; transform: rotate(45deg); }
      .label { color: var(--color-text-primary); font-size: var(--text-sm); }
    `,
  ],
})
export class UICheckboxComponent {
  @Input() checked = false;
  @Input() label?: string;
  @Output() checkedChange = new EventEmitter<boolean>();
  onChange(e: Event) { this.checkedChange.emit((e.target as HTMLInputElement).checked); }
}

