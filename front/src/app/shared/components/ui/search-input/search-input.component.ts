import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormControl } from '@angular/forms';

@Component({
  selector: 'ui-search',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule],
  template: `
    <div class="ui-search">
      <svg class="search-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <circle cx="11" cy="11" r="8"></circle>
        <path d="m21 21-4.35-4.35"></path>
      </svg>
      <input
        class="search-input"
        type="text"
        [placeholder]="placeholder || 'Buscar'"
        [formControl]="inputControl"
        (keyup.enter)="search.emit(inputControl.value || '')"
        [attr.aria-label]="ariaLabel || placeholder || 'Buscar'"
      />
    </div>
  `,
  styles: [
    `
      .ui-search { position: relative; width: 100%; max-width: 420px; }
      .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #94a3b8; pointer-events: none; }
      .search-input {
        width: 100%; height: var(--ctrl-h); padding: 0 12px 0 40px; box-sizing: border-box;
        border: 1px solid #e2e8f0; border-radius: var(--ctrl-radius);
        background: #f9fafb; color: #1e293b;
        transition: all var(--duration-fast) var(--ease-out);
        box-shadow: none;
      }
      .search-input:hover { border-color: #cbd5e1; }
      .search-input::placeholder { color: #94a3b8; }
      .search-input:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-primary) 12%, transparent); }
    `,
  ],
})
export class UISearchInputComponent implements OnInit {
  // bound control always defined for template typing
  inputControl: FormControl<string | null> = new FormControl('');

  @Input() set control(ctrl: FormControl<string | null> | undefined) {
    if (ctrl) this.inputControl = ctrl;
  }
  get control(): FormControl<string | null> { return this.inputControl; }
  @Input() placeholder?: string;
  @Input() ariaLabel?: string;
  @Output() search = new EventEmitter<string>();

  ngOnInit(): void {
    // inputControl already initialized
  }
}
