import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';

@Component({
  selector: 'app-communications',
  standalone: true,
  imports: [CommonModule],
  template: `
    <div class="page">
      <div class="page-header">
        <h1>Comunicación</h1>
      </div>
      <p>Página de comunicaciones</p>
    </div>
  `,
})
export class CommunicationsComponent {}
