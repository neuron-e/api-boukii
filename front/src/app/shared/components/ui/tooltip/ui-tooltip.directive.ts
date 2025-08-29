import { Directive, ElementRef, HostListener, Input, Renderer2 } from '@angular/core';

@Directive({
  standalone: true,
  selector: '[uiTooltip]'
})
export class UITooltipDirective {
  @Input('uiTooltip') text = '';
  private tooltip?: HTMLElement;
  constructor(private el: ElementRef<HTMLElement>, private r: Renderer2) {}

  @HostListener('mouseenter') onEnter() {
    if (!this.text) return;
    this.tooltip = this.r.createElement('div');
    this.tooltip.textContent = this.text;
    this.r.addClass(this.tooltip, 'ui-tooltip');
    this.r.appendChild(document.body, this.tooltip);
    const rect = this.el.nativeElement.getBoundingClientRect();
    const tt = this.tooltip.getBoundingClientRect();
    this.r.setStyle(this.tooltip, 'position', 'fixed');
    this.r.setStyle(this.tooltip, 'top', `${Math.max(0, rect.top - tt.height - 8)}px`);
    this.r.setStyle(this.tooltip, 'left', `${Math.max(8, rect.left)}px`);
  }
  @HostListener('mouseleave') onLeave() { this.destroy(); }
  @HostListener('click') onClick() { this.destroy(); }
  private destroy() { if (this.tooltip) { this.r.removeChild(document.body, this.tooltip); this.tooltip = undefined; } }
}

// Global styles for tooltip (can be moved to a CSS file if preferred)
const style = document.createElement('style');
style.textContent = `.ui-tooltip{background: var(--color-text-primary); color: var(--color-background); padding: 6px 8px; border-radius:6px; font-size:12px; box-shadow: var(--shadow-sm); z-index: 2000;}`;
document.head.appendChild(style);

