import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'ui-switch',
  standalone: true,
  imports: [CommonModule],
  template: `
    <button class="ui-switch" role="switch" [attr.aria-checked]="checked" (click)="toggle()">
      <span class="thumb"></span>
    </button>
  `,
  styles: [
    `
      .ui-switch { width: 44px; height: 24px; border-radius: 999px; border: 1px solid var(--color-border); background: var(--color-switch-background, var(--color-surface-elevated)); position: relative; padding: 0; cursor: pointer; }
      .thumb { position: absolute; top: 1px; left: 1px; width: 20px; height: 20px; border-radius: 999px; background: var(--color-surface); box-shadow: var(--shadow-xs); transition: transform var(--duration-fast) var(--ease-out); }
      .ui-switch[aria-checked='true'] { background: color-mix(in srgb, var(--color-primary) 50%, transparent); border-color: var(--color-primary); }
      .ui-switch[aria-checked='true'] .thumb { transform: translateX(20px); background: white; }
    `,
  ],
})
export class UISwitchComponent {
  @Input() checked = false;
  @Output() checkedChange = new EventEmitter<boolean>();
  toggle() { this.checked = !this.checked; this.checkedChange.emit(this.checked); }
}

