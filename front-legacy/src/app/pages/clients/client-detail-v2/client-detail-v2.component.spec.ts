import { ComponentFixture, TestBed } from '@angular/core/testing';

import { ClientDetailV2Component } from './client-detail-v2.component';

describe('ClientDetailV2Component', () => {
  let component: ClientDetailV2Component;
  let fixture: ComponentFixture<ClientDetailV2Component>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [ClientDetailV2Component]
    });
    fixture = TestBed.createComponent(ClientDetailV2Component);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
