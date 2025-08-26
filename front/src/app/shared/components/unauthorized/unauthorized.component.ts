import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-unauthorized',
  standalone: true,
  imports: [CommonModule, RouterLink],
  template: `
    <div class="unauthorized-page">
      <div class="unauthorized-container">
        <div class="unauthorized-card">
          <div class="error-icon icon-active">
            🚫
          </div>
          <h1 class="error-title">Unauthorized Access</h1>
          <p class="error-message">
            You don't have permission to access this resource.
          </p>
          <p class="error-description">
            Please contact your administrator if you believe this is an error.
          </p>
            <div class="error-actions">
              <button routerLink="/dashboard" class="action-button btn-accent">
                Go to Dashboard
              </button>
              <button routerLink="/auth/login" class="action-button btn-accent-outline">
                Login Again
              </button>
            </div>
        </div>
      </div>
    </div>
  `,
  styles: [`
    .unauthorized-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 1rem;
    }

    .unauthorized-container {
      width: 100%;
      max-width: 400px;
    }

    .unauthorized-card {
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
export class UnauthorizedComponent {}