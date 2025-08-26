import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-no-access',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="no-access-page">
      <div class="no-access-container">
        <div class="no-access-card">
          <div class="error-icon icon-active">
            🔒
          </div>
          <h1 class="error-title">Access Denied</h1>
          <p class="error-message">
            You don't have the required permissions to access this feature.
          </p>
          <p class="error-description">
            Your account may need additional permissions or you may need to select a different school/season context.
          </p>
            <div class="error-actions">
              <button routerLink="/dashboard" class="action-button btn-accent">
                Return to Dashboard
              </button>
              <button routerLink="/select-school" class="action-button btn-accent-outline">
                Change School
              </button>
            </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .no-access-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 1rem;
    }

    .no-access-container {
      width: 100%;
      max-width: 400px;
    }

    .no-access-card {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      text-align: center;
    }

    .error-icon {
      font-size: 4rem;
      margin-bottom: 1rem;
    }

    .error-title {
      font-size: 1.5rem;
      font-weight: 600;
      margin: 0 0 1rem 0;
      color: #1f2937;
    }

    .error-message {
      font-size: 1rem;
      color: #374151;
      margin: 0 0 0.5rem 0;
    }

    .error-description {
      font-size: 0.875rem;
      color: #6b7280;
      margin: 0 0 2rem 0;
      line-height: 1.5;
    }

    .error-actions {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .action-button {
      padding: 0.75rem 1.5rem;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.2s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }


    @media (min-width: 480px) {
      .error-actions {
        flex-direction: row;
        justify-content: center;
      }

      .action-button {
        min-width: 120px;
      }
    }
  `]
})
export class NoAccessComponent {}