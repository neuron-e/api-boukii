import { Component, Input, Output, EventEmitter, ChangeDetectionStrategy, forwardRef, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';

export type TextFieldType = 'text' | 'email' | 'password' | 'tel' | 'url' | 'search';

@Component({
  selector: 'ui-text-field',
  standalone: true,
  imports: [CommonModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => TextFieldComponent),
      multi: true
    }
  ],
  template: `
    <div class="field-container" [class.field-error]="hasError">
      @if (label) {
        <label [for]="inputId" class="field-label">
          {{ label }}
          @if (required) {
            <span class="required-indicator" aria-label="Required">*</span>
          }
        </label>
      }
      
      <div class="input-container">
        <input
          [id]="inputId"
          [type]="type"
          [placeholder]="placeholder"
          [disabled]="disabled"
          [readonly]="readonly"
          [required]="required"
          class="input-field"
          [value]="value()"
          (input)="onInput($event)"
          (blur)="onBlur()"
          (focus)="onFocus()"
          [attr.aria-describedby]="ariaDescribedBy"
          [attr.aria-invalid]="hasError"
          [attr.autocomplete]="autocomplete"
        />
        
        @if (suffixIcon) {
          <div class="input-suffix">
            <ng-content select="[slot=suffix-icon]"></ng-content>
          </div>
        }
      </div>
      
      @if (hint || errorMessage) {
        <div class="field-message" [id]="messageId" aria-live="polite">
          @if (hasError && errorMessage) {
            <span class="error-message">{{ errorMessage }}</span>
          } @else if (hint) {
            <span class="hint-message">{{ hint }}</span>
          }
        </div>
      }
    </div>
  `,
  styles: [`
    .field-container {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .field-label {
      font-size: 14px;
      font-weight: 500;
      color: var(--text-1);
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .required-indicator {
      color: var(--danger);
      font-weight: 700;
    }

    .input-container {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-field {
      width: 100%;
      padding: 12px 16px;
      border: 1px solid var(--border);
      border-radius: 8px;
      background-color: var(--surface);
      color: var(--text-1);
      font-family: inherit;
      font-size: 16px;
      transition: all 0.2s ease;
      outline: none;
    }

    .input-field::placeholder {
      color: var(--muted);
    }

    .input-field:focus {
      border-color: var(--brand-500);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .input-field:disabled {
      background-color: var(--surface-2);
      color: var(--muted);
      cursor: not-allowed;
    }

    .input-field:readonly {
      background-color: var(--surface-2);
    }

    .field-error .input-field {
      border-color: var(--danger);
    }

    .field-error .input-field:focus {
      border-color: var(--danger);
      box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
    }

    .input-suffix {
      position: absolute;
      right: 12px;
      display: flex;
      align-items: center;
      color: var(--muted);
    }

    .field-message {
      font-size: 14px;
      line-height: 1.4;
    }

    .error-message {
      color: var(--danger);
    }

    .hint-message {
      color: var(--muted);
    }
  `]
})
export class TextFieldComponent implements ControlValueAccessor {
  @Input() label: string = '';
  @Input() placeholder: string = '';
  @Input() type: TextFieldType = 'text';
  @Input() disabled: boolean = false;
  @Input() readonly: boolean = false;
  @Input() required: boolean = false;
  @Input() hint: string = '';
  @Input() errorMessage: string = '';
  @Input() suffixIcon: boolean = false;
  @Input() autocomplete: string = '';

  @Output() valueChange = new EventEmitter<string>();
  @Output() focus = new EventEmitter<FocusEvent>();
  @Output() blur = new EventEmitter<FocusEvent>();

  public value = signal<string>('');
  public inputId = `text-field-${Math.random().toString(36).substring(2, 15)}`;
  public messageId = `${this.inputId}-message`;

  private onChange = (_value: string) => {};
  private onTouched = () => {};

  public get hasError(): boolean {
    return !!this.errorMessage;
  }

  public get ariaDescribedBy(): string | null {
    return (this.hint || this.errorMessage) ? this.messageId : null;
  }

  public onInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const newValue = target.value;
    this.value.set(newValue);
    this.onChange(newValue);
    this.valueChange.emit(newValue);
  }

  public onFocus(): void {
    this.focus.emit();
  }

  public onBlur(): void {
    this.onTouched();
    this.blur.emit();
  }

  // ControlValueAccessor implementation
  public writeValue(value: string): void {
    this.value.set(value || '');
  }

  public registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  public registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  public setDisabledState(isDisabled: boolean): void {
    this.disabled = isDisabled;
  }
}