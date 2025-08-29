import { Component, EventEmitter, Input, Output, HostListener, ElementRef } from '@angular/core';
import { CommonModule } from '@angular/common';

export interface SegmentedOption { value: string; label: string; }

@Component({
  selector: 'ui-segmented-toggle',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div
      class="segmented"
      role="radiogroup"
      [attr.aria-label]="ariaLabel || 'Segmented toggle'"
      (keydown)="onKeydown($event)"
    >
      <button
        *ngFor="let opt of options; let i = index"
        class="segmented-item"
        [class.active]="opt.value === value"
        role="radio"
        [attr.aria-checked]="opt.value === value"
        [attr.tabindex]="opt.value === value ? 0 : -1"
        (click)="select(opt.value, i)"
      >
        {{ opt.label }}
      </button>
    </div>
  `,
  styles: [
    `
      .segmented {
        display: inline-flex;
        align-items: center;
        height: var(--ctrl-h);
        border: 1px solid #e2e8f0;
        border-radius: var(--ctrl-radius);
        background: var(--color-surface-elevated);
        padding: 2px;
        box-sizing: border-box;
      }
      .segmented-item {
        appearance: none;
        border: none;
        background: transparent;
        color: var(--color-text-secondary);
        height: calc(var(--ctrl-h) - 4px);
        padding: 0 12px;
        border-radius: calc(var(--ctrl-radius) - 2px);
        cursor: pointer;
        font: 500 14px/1 var(--font-family-sans);
        transition: background-color 150ms ease, color 150ms ease, box-shadow 150ms ease;
      }
      .segmented-item:hover { background: #ffffff; color: var(--color-text-primary); }
      .segmented-item:focus-visible { outline: 2px solid var(--color-primary-focus); outline-offset: 2px; }
      .segmented-item.active { background: #ffffff; color: var(--color-text-primary); box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    `,
  ],
})
export class SegmentedToggleComponent {
  @Input() options: SegmentedOption[] = [];
  @Input() value!: string;
  @Input() ariaLabel?: string;
  @Output() valueChange = new EventEmitter<string>();

  constructor(private host: ElementRef<HTMLElement>) {}

  select(val: string, index: number) {
    if (val !== this.value) {
      this.value = val;
      this.valueChange.emit(val);
    }
    this.focusIndex(index);
  }

  private get currentIndex(): number {
    return Math.max(0, this.options.findIndex(o => o.value === this.value));
  }

  private focusIndex(i: number) {
    const btns = this.host.nativeElement.querySelectorAll<HTMLButtonElement>('.segmented-item');
    if (btns[i]) btns[i].focus();
  }

  @HostListener('keydown', ['$event'])
  onKeydown(e: KeyboardEvent) {
    if (!this.options.length) return;
    const idx = this.currentIndex;
    if (e.key === 'ArrowRight') {
      const next = (idx + 1) % this.options.length;
      this.select(this.options[next].value, next);
      e.preventDefault();
    } else if (e.key === 'ArrowLeft') {
      const prev = (idx - 1 + this.options.length) % this.options.length;
      this.select(this.options[prev].value, prev);
      e.preventDefault();
    } else if (e.key === 'Home') {
      this.select(this.options[0].value, 0);
      e.preventDefault();
    } else if (e.key === 'End') {
      const last = this.options.length - 1;
      this.select(this.options[last].value, last);
      e.preventDefault();
    } else if (e.key === ' ' || e.key === 'Enter') {
      // noop, click already handled; ensure no page scroll
      e.preventDefault();
    }
  }
}

