import { ComponentFixture, TestBed } from '@angular/core/testing';

import { PointageComponent } from './pointage.component';

describe('PointageComponent', () => {
  let component: PointageComponent;
  let fixture: ComponentFixture<PointageComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [PointageComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(PointageComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
