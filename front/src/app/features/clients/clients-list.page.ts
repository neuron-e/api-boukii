import { Component, OnInit, inject, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder } from '@angular/forms';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { ClientsV5Service, Client, GetClientsParams } from '@core/services/clients-v5.service';
import { ContextService } from '@core/services/context.service';

@Component({
  selector: 'app-clients-list-page',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, TranslatePipe],
  template: `
    <div class="page">
      <div class="page-header">
        <h1>{{ 'clients.title' | translate }}</h1>
      </div>

      <form [formGroup]="filtersForm" class="filters">
        <input type="text" formControlName="q" placeholder="Search" />
        <input type="number" formControlName="sport_id" placeholder="Sport ID" />
        <select formControlName="active">
          <option value="">All</option>
          <option value="true">Active</option>
          <option value="false">Inactive</option>
        </select>
      </form>

      <table>
        <thead>
          <tr>
            <th>{{ 'clients.fullName' | translate }}</th>
            <th>{{ 'clients.email' | translate }}</th>
            <th>{{ 'clients.phone' | translate }}</th>
            <th>{{ 'clients.utilizadores' | translate }}</th>
            <th>{{ 'clients.sportsSummary' | translate }}</th>
            <th>{{ 'clients.status' | translate }}</th>
            <th>{{ 'clients.signupDate' | translate }}</th>
          </tr>
        </thead>
        <tbody>
          <tr *ngFor="let client of clients" (click)="openPreview(client)">
            <td>{{ client.fullName }}</td>
            <td>{{ client.email }}</td>
            <td>{{ client.phone }}</td>
            <td>{{ client.utilizadores }}</td>
            <td>{{ client.sportsSummary }}</td>
            <td>{{ client.status }}</td>
            <td>{{ client.signupDate }}</td>
          </tr>
        </tbody>
      </table>

      <div class="preview-overlay" *ngIf="selectedClient" (click)="closePreview()">
        <div class="preview-drawer" (click)="$event.stopPropagation()">
          <h2>{{ selectedClient.fullName }}</h2>
          <div class="contact">
            <p>Email: {{ selectedClient.email }}</p>
            <p>Phone: {{ selectedClient.phone }}</p>
          </div>
          <div class="utilizadores">
            <h3>Utilizadores</h3>
            <ul>
              <li *ngFor="let u of (selectedClient.utilizadores || [])">
                {{ u.name }} - {{ u.age }} - {{ u.sport }}
              </li>
            </ul>
          </div>
          <div class="actions">
            <button class="btn">Ver ficha</button>
            <button class="btn">Nueva reserva</button>
          </div>
        </div>
      </div>
    </div>
  `,
  styles: [
    `
      table {
        width: 100%;
      }
      th, td {
        padding: 8px;
        text-align: left;
      }
      form.filters {
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
      }
      .preview-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        display: flex;
        justify-content: flex-end;
      }
      .preview-drawer {
        width: 320px;
        background: var(--surface);
        color: var(--text-1);
        padding: var(--space-4);
        box-shadow: var(--elev-2);
        display: flex;
        flex-direction: column;
        gap: var(--space-3);
      }
      .actions {
        display: flex;
        gap: var(--space-2);
        margin-top: var(--space-4);
      }
      .btn {
        padding: var(--space-2) var(--space-4);
        background: var(--brand-500);
        color: var(--surface);
        border: none;
        border-radius: var(--radius-8);
        cursor: pointer;
      }
    `,
  ],
})
export class ClientsListPageComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly clientsService = inject(ClientsV5Service);
  private readonly contextService = inject(ContextService);

  filtersForm = this.fb.group({
    q: [''],
    sport_id: [''],
    active: [''],
  });

  clients: Client[] = [];
  selectedClient: Client | null = null;

  ngOnInit(): void {
    const params = this.route.snapshot.queryParamMap;
    this.filtersForm.patchValue(
      {
        q: params.get('q') || '',
        sport_id: params.get('sport_id') || '',
        active: params.get('active') || '',
      },
      { emitEvent: false }
    );

    this.loadClients();

    this.filtersForm.valueChanges.subscribe(() => {
      this.updateQueryParams();
      this.loadClients();
    });
  }

  openPreview(client: Client): void {
    this.selectedClient = client;
  }

  closePreview(): void {
    this.selectedClient = null;
  }

  @HostListener('document:keydown.escape', ['$event'])
  handleEscape(event: KeyboardEvent): void {
    if (this.selectedClient) {
      this.closePreview();
    }
  }

  private updateQueryParams(): void {
    const value = this.filtersForm.value;
    const queryParams: any = {};
    if (value.q) queryParams.q = value.q;
    if (value.sport_id) queryParams.sport_id = value.sport_id;
    if (value.active) queryParams.active = value.active;
    this.router.navigate([], {
      queryParams,
      queryParamsHandling: 'merge',
    });
  }

  private loadClients(): void {
    const value = this.filtersForm.value;
    const params: GetClientsParams = {
      school_id: this.contextService.schoolId() || 0,
      q: value.q || undefined,
      sport_id: value.sport_id ? Number(value.sport_id) : undefined,
      active: value.active !== '' ? value.active === 'true' : undefined,
    };
    this.clientsService.getClients(params).subscribe((res) => {
      this.clients = res.data;
    });
  }
}

