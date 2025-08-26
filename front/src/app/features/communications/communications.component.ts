import { Component, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { TranslatePipe } from '@shared/pipes/translate.pipe';

interface SentEmail {
  recipients: string;
  subject: string;
  date: string;
}

@Component({
  selector: 'app-communications',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  template: `
    <div class="page">
      <div class="page-header">
        <h1>{{ 'communications.title' | translate }}</h1>
      </div>

      <div class="card">
        <form [formGroup]="emailForm" class="stack">
          <div class="row">
            <label>
              <input type="checkbox" formControlName="sendToClients" />
              {{ 'communications.sendToClients' | translate }}
            </label>
            <label>
              <input type="checkbox" formControlName="sendToMonitors" />
              {{ 'communications.sendToMonitors' | translate }}
            </label>
          </div>

          <label class="field">
            <span>{{ 'communications.subject' | translate }}</span>
            <input type="text" formControlName="subject" />
          </label>

          <div
            class="body-editor"
            contenteditable="true"
            (input)="onBodyInput($event)"
            [innerText]="emailForm.get('body')?.value"
          ></div>

          <div class="row">
            <button type="button" class="btn btn--primary" (click)="sendEmail()">
              {{ 'communications.send' | translate }}
            </button>
            <button type="button" class="btn" (click)="clearForm()">
              {{ 'communications.clear' | translate }}
            </button>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>{{ 'communications.sent' | translate }}</h2>
        <table>
          <thead>
            <tr>
              <th>{{ 'communications.recipients' | translate }}</th>
              <th>{{ 'communications.subject' | translate }}</th>
              <th>{{ 'communications.date' | translate }}</th>
            </tr>
          </thead>
          <tbody>
            <tr *ngFor="let email of sentEmails">
              <td>{{ email.recipients }}</td>
              <td>{{ email.subject }}</td>
              <td>{{ email.date }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  `,
  styles: [
    `
      h1 {
        font-size: var(--fs-24);
        color: var(--text-1);
        margin: 0;
      }

      h2 {
        font-size: var(--fs-18);
        color: var(--text-1);
        margin: 0 0 16px;
      }

      label {
        font-size: var(--fs-14);
        color: var(--text-2);
        display: flex;
        align-items: center;
        gap: 4px;
      }

      input[type='text'] {
        padding: 6px 8px;
        font-size: var(--fs-14);
      }

      .body-editor {
        min-height: 120px;
        border: 1px solid var(--border);
        padding: 8px;
        font-size: var(--fs-14);
        color: var(--text-1);
      }

      table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--fs-14);
      }

      th,
      td {
        text-align: left;
        padding: 8px;
      }

      th {
        color: var(--text-2);
      }

      td {
        color: var(--text-1);
      }
    `,
  ],
})
export class CommunicationsComponent {
  private readonly fb = inject(FormBuilder);

  emailForm = this.fb.group({
    sendToClients: false,
    sendToMonitors: false,
    subject: '',
    body: '',
  });

  sentEmails: SentEmail[] = [
    { recipients: 'Clientes', subject: 'Bienvenida', date: '2024-06-01' },
    { recipients: 'Monitores', subject: 'Reunión de equipo', date: '2024-06-02' },
  ];

  onBodyInput(event: Event): void {
    const value = (event.target as HTMLElement).innerText;
    this.emailForm.get('body')?.setValue(value);
  }

  sendEmail(): void {
    const { sendToClients, sendToMonitors, subject } = this.emailForm.value;
    const recipients = [
      sendToClients ? 'Clientes' : '',
      sendToMonitors ? 'Monitores' : '',
    ]
      .filter(Boolean)
      .join(', ') || '—';

    this.sentEmails = [
      ...this.sentEmails,
      { recipients, subject: subject ?? '', date: new Date().toLocaleDateString() },
    ];
    this.clearForm();
  }

  clearForm(): void {
    this.emailForm.reset();
  }
}

