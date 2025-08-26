import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';

interface ChatMessage {
  sender: 'admin' | 'monitor';
  text: string;
}

interface Instructor {
  id: number;
  name: string;
  messages: ChatMessage[];
}

@Component({
  selector: 'app-chat',
  standalone: true,
  imports: [CommonModule, FormsModule],
  template: `
    <div class="page">
      <div class="page-header">
        <h1>Chat</h1>
      </div>

      <div class="chat-container card">
        <aside class="instructors-list">
          <div
            class="instructor"
            *ngFor="let instructor of instructors"
            [class.active]="instructor === selectedInstructor"
            (click)="selectInstructor(instructor)"
          >
            {{ instructor.name }}
          </div>
        </aside>

        <div class="chat-thread">
          <div class="messages">
            <div
              *ngFor="let msg of selectedInstructor.messages"
              class="message"
              [class.admin]="msg.sender === 'admin'"
              [class.monitor]="msg.sender === 'monitor'"
            >
              {{ msg.text }}
            </div>
          </div>

          <form class="input-area" (submit)="sendMessage(); $event.preventDefault()">
            <input
              type="text"
              name="message"
              [(ngModel)]="newMessage"
              placeholder="Escribe un mensaje"
            />
            <button type="submit" class="btn btn--primary">Enviar</button>
          </form>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      .chat-container {
        display: flex;
        height: 500px;
      }

      .instructors-list {
        width: 200px;
        border-right: 1px solid var(--border);
        background: var(--surface-2);
        overflow-y: auto;
      }

      .instructor {
        padding: 12px;
        cursor: pointer;
        border-bottom: 1px solid var(--border);
      }

      .instructor.active {
        background: var(--surface);
        font-weight: var(--font-weight-semibold);
      }

      .chat-thread {
        flex: 1;
        display: flex;
        flex-direction: column;
      }

      .messages {
        flex: 1;
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        overflow-y: auto;
      }

      .message {
        padding: 8px 12px;
        border-radius: var(--radius-lg);
        max-width: 70%;
      }

      .message.admin {
        margin-left: auto;
        background: var(--brand-500);
        color: var(--active-contrast);
      }

      .message.monitor {
        margin-right: auto;
        background: var(--surface-2);
        color: var(--text-1);
      }

      .input-area {
        display: flex;
        gap: 8px;
        padding: 12px;
        border-top: 1px solid var(--border);
      }

      .input-area input {
        flex: 1;
        padding: 8px;
        border: 1px solid var(--border);
        border-radius: var(--radius-base);
        background: var(--surface);
        color: var(--text-1);
      }
    `,
  ],
})
export class ChatComponent {
  instructors: Instructor[] = [
    {
      id: 1,
      name: 'Juan Pérez',
      messages: [
        { sender: 'monitor', text: 'Hola, ¿necesitas algo?' },
        { sender: 'admin', text: 'Todo bien, gracias.' },
      ],
    },
    {
      id: 2,
      name: 'María López',
      messages: [
        { sender: 'monitor', text: 'Clase completada.' },
        { sender: 'admin', text: 'Perfecto, gracias por avisar.' },
      ],
    },
    {
      id: 3,
      name: 'Carlos García',
      messages: [],
    },
  ];

  selectedInstructor: Instructor = this.instructors[0];
  newMessage = '';

  selectInstructor(instructor: Instructor): void {
    this.selectedInstructor = instructor;
  }

  sendMessage(): void {
    const text = this.newMessage.trim();
    if (!text) return;
    this.selectedInstructor.messages.push({ sender: 'admin', text });
    // Mock reply
    this.selectedInstructor.messages.push({ sender: 'monitor', text: 'Recibido.' });
    this.newMessage = '';
  }
}

