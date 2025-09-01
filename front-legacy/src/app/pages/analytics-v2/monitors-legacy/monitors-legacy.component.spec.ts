import { ComponentFixture, TestBed } from '@angular/core/testing';

import { MonitorsLegacyComponent } from './monitors-legacy.component';

describe('MonitorsLegacyComponent', () => {
  let component: MonitorsLegacyComponent;
  let fixture: ComponentFixture<MonitorsLegacyComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [MonitorsLegacyComponent]
    });
    fixture = TestBed.createComponent(MonitorsLegacyComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
