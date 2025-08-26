import { Component, OnInit, inject, HostListener } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { TranslatePipe } from '@shared/pipes/translate.pipe';
import { ClientsV5Service, GetClientsParams } from '@core/services/clients-v5.service';
import { ContextService } from '@core/services/context.service';

export interface ClientListItem {
  id: number;
  fullName: string;
  email: string;
  phone: string;
  utilizadores: number;
  utilizadoresDetails?: { name: string; age: number; sport: string }[];
  sportsSummary: string;
  status: 'active' | 'inactive' | 'blocked';
  signupDate: string;
}

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
        <input type="text" formControlName="search" placeholder="Search" />
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
            <th>{{ 'common.actions' | translate }}</th>
          </tr>
        </thead>
        <tbody *ngIf="!loading && clients.length > 0">
          <tr *ngFor="let client of clients" (click)="openPreview(client)">
            <td>{{ client.fullName }}</td>
            <td>{{ client.email }}</td>
            <td>{{ client.phone }}</td>
            <td>{{ client.utilizadores }}</td>
            <td>{{ client.sportsSummary }}</td>
            <td>{{ client.status }}</td>
            <td>{{ client.signupDate }}</td>
            <td>
              <button (click)="openPreview(client); $event.stopPropagation()">{{ 'common.view' | translate }}</button>
              <button (click)="editClient(client); $event.stopPropagation()">{{ 'common.edit' | translate }}</button>
              <button (click)="deleteClient(client); $event.stopPropagation()">{{ 'common.delete' | translate }}</button>
            </td>
          </tr>
        </tbody>
        <tbody *ngIf="loading">
          <tr *ngFor="let _ of skeletonRows">
            <td colspan="8" class="skeleton-row"></td>
          </tr>
        </tbody>
        <tbody *ngIf="!loading && clients.length === 0">
          <tr>
            <td colspan="8">
              <div class="empty-state">No clients found</div>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="pagination" *ngIf="!loading && totalPages > 1">
        <button (click)="goToPage(currentPage - 1)" [disabled]="currentPage === 1">Previous</button>
        <span>Page {{ currentPage }} of {{ totalPages }}</span>
        <button (click)="goToPage(currentPage + 1)" [disabled]="currentPage === totalPages">Next</button>
      </div>

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
              <li *ngFor="let u of (selectedClient?.utilizadoresDetails || [])">
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
      .skeleton-row {
        height: 1.5rem;
        background: linear-gradient(90deg, var(--surface-2) 25%, var(--surface-3) 50%, var(--surface-2) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
      }
      @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
      }
      .empty-state {
        text-align: center;
        padding: 1rem;
        color: var(--text-2);
      }
      .pagination {
        margin-top: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
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
    search: [''],
    sport_id: [''],
    active: [''],
  });

  clients: ClientListItem[] = [];
  selectedClient?: ClientListItem | null;
  loading = false;
  currentPage = 1;
  totalPages = 1;
  skeletonRows = Array.from({ length: 5 });

  ngOnInit(): void {
    const params = this.route.snapshot.queryParamMap;
    this.filtersForm.patchValue(
      {
        search: params.get('search') || '',
        sport_id: params.get('sport_id') || '',
        active: params.get('active') || '',
      },
      { emitEvent: false }
    );
    const pageParam = Number(params.get('page'));
    this.currentPage = !isNaN(pageParam) && pageParam > 0 ? pageParam : 1;

    this.loadClients();

    this.filtersForm.valueChanges.subscribe(() => {
      this.currentPage = 1;
      this.updateQueryParams();
      this.loadClients();
    });
  }

  openPreview(client: ClientListItem): void {
    this.selectedClient = client;
  }

  closePreview(): void {
    this.selectedClient = null;
  }

  editClient(client: ClientListItem): void {
    this.router.navigate(['/clients', client.id, 'edit']);
  }

  deleteClient(client: ClientListItem): void {
    if (confirm('Are you sure you want to delete this client?')) {
      // Placeholder for deletion logic
    }
  }

  @HostListener('document:keydown.escape', ['$event'])
  handleEscape(event: KeyboardEvent): void {
    if (this.selectedClient) {
      this.closePreview();
    }
  }

  private updateQueryParams(): void {
    const value = this.filtersForm.value;
    const queryParams: any = { page: this.currentPage };
    if (value.search) queryParams.search = value.search;
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
      search: value.search || undefined,
      sport_id: value.sport_id ? Number(value.sport_id) : undefined,
      active: value.active !== '' ? value.active === 'true' : undefined,
      page: this.currentPage,
    };
    this.loading = true;
    this.clientsService.getClients(params).subscribe((res) => {
      this.clients = res.data as ClientListItem[];
      this.totalPages = res.meta.pagination.totalPages;
      this.loading = false;
    });
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.currentPage = page;
    this.updateQueryParams();
    this.loadClients();
  }
}

