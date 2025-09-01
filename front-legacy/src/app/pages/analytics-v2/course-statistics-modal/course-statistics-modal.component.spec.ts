import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CourseStatisticsModalComponent } from './course-statistics-modal.component';

describe('CourseStatisticsModalComponent', () => {
  let component: CourseStatisticsModalComponent;
  let fixture: ComponentFixture<CourseStatisticsModalComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [CourseStatisticsModalComponent]
    });
    fixture = TestBed.createComponent(CourseStatisticsModalComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
