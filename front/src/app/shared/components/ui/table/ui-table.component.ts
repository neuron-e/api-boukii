import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'ui-table',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="ui-table-wrapper">
      <ng-content />
    </div>
  `,
  styles: [
    `
      .ui-table-wrapper {
        background: var(--color-surface);
        border-radius: 12px;
        border: 1px solid var(--color-border);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
      }
      /* Let pages control table visuals; provide only the wrapper */
    `,
  ],
})
export class UITableComponent {}
