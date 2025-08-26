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
  templateUrl: './clients-list.page.html',
  styleUrls: ['./clients-list.page.scss'],
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
