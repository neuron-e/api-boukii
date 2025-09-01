import { ComponentFixture, TestBed } from '@angular/core/testing';

import { KpisCardComponent } from './kpis-card.component';

describe('KpisCardComponent', () => {
  let component: KpisCardComponent;
  let fixture: ComponentFixture<KpisCardComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [KpisCardComponent]
    });
    fixture = TestBed.createComponent(KpisCardComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
