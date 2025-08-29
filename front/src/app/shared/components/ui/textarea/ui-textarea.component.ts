import { Component, Input, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';

@Component({
  selector: 'ui-textarea',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <label class="ui-textarea" [class.full]="fullWidth">
      <span *ngIf="label" class="label">{{ label }}</span>
      <textarea
        class="control"
        rows="rows"
        [formControl]="inputControl"
        [placeholder]="placeholder || ''"
        [attr.aria-label]="ariaLabel || label || placeholder || 'Textarea'"
      ></textarea>
    </label>
  `,
  styles: [
    `
      .ui-textarea { display: inline-flex; flex-direction: column; gap: var(--space-2); }
      .ui-textarea.full { width: 100%; }
      .label { font-size: var(--text-xs); color: var(--color-text-secondary); font-weight: 500; }
      .control {
        width: 100%; min-height: 80px; padding: var(--space-3); box-sizing: border-box; resize: vertical;
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
export class UITextareaComponent implements OnInit {
  inputControl: FormControl<string | null> = new FormControl('');
  @Input() set control(ctrl: FormControl<string | null> | undefined) { if (ctrl) this.inputControl = ctrl; }
  get control(): FormControl<string | null> { return this.inputControl; }
  @Input() label?: string;
  @Input() placeholder?: string;
  @Input() ariaLabel?: string;
  @Input() fullWidth = false;
  @Input() rows = 4;
  ngOnInit(): void {}
}

