import { ComponentFixture, TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { expect } from '@jest/globals';

import { ClientsListPageComponent } from './clients-list.page';

describe('ClientsListPageComponent', () => {
  let component: ClientsListPageComponent;
  let fixture: ComponentFixture<ClientsListPageComponent>;
  let router: jest.Mocked<Router>;

  beforeEach(async () => {
    const routerMock = {
      navigate: jest.fn(),
    } as Partial<jest.Mocked<Router>>;

    await TestBed.configureTestingModule({
      imports: [ClientsListPageComponent],
      providers: [{ provide: Router, useValue: routerMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(ClientsListPageComponent);
    component = fixture.componentInstance;
    router = TestBed.inject(Router) as jest.Mocked<Router>;
    fixture.detectChanges();
  });

  it('filters clients by search term', () => {
    component.searchControl.setValue('alice');
    expect(component.filteredClients.length).toBe(1);
    expect(component.filteredClients[0].name).toBe('Alice Johnson');
  });

  it('navigates to profile on row click', () => {
    const client = component.clients[0];
    component.navigateToProfile(client);
    expect(router.navigate).toHaveBeenCalledWith(['/clients', client.id, 'profile']);
  });
});

