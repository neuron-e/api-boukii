import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'ui-avatar',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="ui-avatar" [style.width.px]="size" [style.height.px]="size">
      <img *ngIf="src" [src]="src" [alt]="alt || name || 'Avatar'" (error)="onError()" />
      <span *ngIf="!src || errored" class="initials">{{ initials }}</span>
    </div>
  `,
  styles: [
    `
      .ui-avatar { border-radius: 50%; overflow: hidden; display: grid; place-items: center; background: var(--color-ski-blue); color: white; font-weight: 600; border: 1px solid var(--color-border); }
      img { width: 100%; height: 100%; object-fit: cover; display: block; }
      .initials { font-size: 0.8em; }
    `,
  ],
})
export class UIAvatarComponent {
  @Input() src?: string | null;
  @Input() name?: string;
  @Input() alt?: string;
  @Input() size = 36;
  errored = false;
  get initials(): string { return (this.name || '?').split(' ').map(w => w[0]).join('').slice(0,2).toUpperCase(); }
  onError() { this.errored = true; }
}
