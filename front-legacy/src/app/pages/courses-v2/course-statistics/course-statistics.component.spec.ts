import { ComponentFixture, TestBed } from '@angular/core/testing';

import { CourseStatisticsComponent } from './course-statistics.component';

describe('CourseStatisticsComponent', () => {
  let component: CourseStatisticsComponent;
  let fixture: ComponentFixture<CourseStatisticsComponent>;

  beforeEach(() => {
    TestBed.configureTestingModule({
      declarations: [CourseStatisticsComponent]
    });
    fixture = TestBed.createComponent(CourseStatisticsComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
