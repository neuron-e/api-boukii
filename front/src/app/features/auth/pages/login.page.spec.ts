import { expect, jest } from '@jest/globals';
import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { LoginPage } from './login.page';
import { AuthV5Service } from '../../../core/services/auth-v5.service';
import { TranslationService } from '../../../core/services/translation.service';
import { ToastService } from '../../../core/services/toast.service';

class MockTranslationService {
  instant(k: string) { return k; }
  currentLanguage() { return 'en'; }
  get(k: string) { return k; }
}

describe('LoginPage', () => {
  let router: { navigate: jest.Mock };
  let authService: { isAuthenticated: () => boolean; checkUser: jest.Mock };
  let toast: { error: jest.Mock; success: jest.Mock };

  beforeEach(async () => {
    router = { navigate: jest.fn() };
    authService = { isAuthenticated: () => false, checkUser: jest.fn() };
    toast = { error: jest.fn(), success: jest.fn() };

    await TestBed.configureTestingModule({
      imports: [LoginPage],
      providers: [
        { provide: TranslationService, useClass: MockTranslationService },
        { provide: AuthV5Service, useValue: authService },
        { provide: Router, useValue: router },
        { provide: ToastService, useValue: toast }
      ]
    }).compileComponents();
  });

  it('renders translated title and form', () => {
    const fixture = TestBed.createComponent(LoginPage);
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.querySelector('h1')?.textContent).toContain('auth.login.title');
    expect(compiled.querySelector('form')).toBeTruthy();
  });

  it('redirects monitor users to the teach app', () => {
    authService.checkUser.mockReturnValue(of({ success: true, data: { user: { type: 'monitor' }, schools: [], temp_token: 't' } }));
    const fixture = TestBed.createComponent(LoginPage);
    const component = fixture.componentInstance;
    fixture.detectChanges();
    component.loginForm.setValue({ email: 'm@b.com', password: 'secret' });
    component.onSubmit();
    expect(router.navigate).toHaveBeenCalledWith(['/teach']);
  });

  it('redirects client users to the client app', () => {
    authService.checkUser.mockReturnValue(of({ success: true, data: { user: { type: 'client' }, schools: [], temp_token: 't' } }));
    const fixture = TestBed.createComponent(LoginPage);
    const component = fixture.componentInstance;
    fixture.detectChanges();
    component.loginForm.setValue({ email: 'c@b.com', password: 'secret' });
    component.onSubmit();
    expect(router.navigate).toHaveBeenCalledWith(['/client']);
  });
});
