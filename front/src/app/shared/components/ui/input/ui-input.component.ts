import { Component, Input, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';

@Component({
  selector: 'ui-input',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <label class="ui-input" [class.full]="fullWidth">
      <span *ngIf="label" class="label">{{ label }}</span>
      <input
        class="control"
        [type]="type"
        [formControl]="inputControl"
        [placeholder]="placeholder || ''"
        [attr.aria-label]="ariaLabel || label || placeholder || 'Input'"
        [required]="required"
      />
    </label>
  `,
  styles: [
    `
      .ui-input { display: inline-flex; flex-direction: column; gap: var(--space-2); }
      .ui-input.full { width: 100%; }
      .label { font-size: var(--text-xs); color: var(--color-text-secondary); font-weight: 500; }
      .control {
        width: 100%; height: 40px; padding: 0 var(--space-3); box-sizing: border-box;
        border: 1px solid var(--color-border); border-radius: 8px;
        background: var(--color-surface); color: var(--color-text-primary);
        transition: all var(--duration-fast) var(--ease-out);
      }
      .control::placeholder { color: var(--color-text-tertiary); }
      .control:hover { border-color: var(--color-border); }
      .control:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent); }
    `,
  ],
})
export class UIInputComponent implements OnInit {
  inputControl: FormControl<string | null> = new FormControl('');
  @Input() set control(ctrl: FormControl<string | null> | undefined) { if (ctrl) this.inputControl = ctrl; }
  get control(): FormControl<string | null> { return this.inputControl; }
  @Input() label?: string;
  @Input() placeholder?: string;
  @Input() ariaLabel?: string;
  @Input() type: string = 'text';
  @Input() fullWidth = false;
  @Input() required = false;
  ngOnInit(): void {}
}
