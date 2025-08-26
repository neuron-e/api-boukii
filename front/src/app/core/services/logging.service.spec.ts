import { TestBed } from '@angular/core/testing';
import { LoggingService, LogPayload } from './logging.service';
import { ApiService } from './api.service';
import { ConfigService } from './config.service';

describe('LoggingService', () => {
  let service: LoggingService;
  let api: { postWithHeaders: jest.Mock };
  let config: { getRuntimeConfig: jest.Mock; getAppVersion: jest.Mock };

  beforeEach(() => {
    api = { postWithHeaders: jest.fn().mockResolvedValue(undefined) };
    config = { getRuntimeConfig: jest.fn(), getAppVersion: jest.fn() };

    (global as any).location = { href: 'http://test.local' };
    (global as any).navigator = { userAgent: 'jest' };

    TestBed.configureTestingModule({
      providers: [
        LoggingService,
        { provide: ApiService, useValue: api },
        { provide: ConfigService, useValue: config },
      ],
    });

    service = TestBed.inject(LoggingService);
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('logs to console in dev without network', () => {
    service.configureEnvironment('development', false);
    config.getRuntimeConfig.mockReturnValue({ logging: { enabled: true } });
    config.getAppVersion.mockReturnValue('1.0.0');

    const spy = jest.spyOn(console, 'info').mockImplementation(() => {});
    service.info('hello', { x: 1 });

    expect(api.postWithHeaders).not.toHaveBeenCalled();
    expect(spy).toHaveBeenCalledWith(
      expect.objectContaining({
        level: 'info',
        message: 'hello',
        env: 'development',
        context: { x: 1 },
      })
    );
  });

  it('sends logs to API in production with headers', () => {
    service.configureEnvironment('production', true);
    config.getRuntimeConfig.mockReturnValue({ logging: { enabled: true } });
    config.getAppVersion.mockReturnValue('2.3.4');

    service.error('boom');

    expect(api.postWithHeaders).toHaveBeenCalledTimes(1);
    const [url, payload, headers] = api.postWithHeaders.mock.calls[0];
    expect(url).toBe('/logs');
    expect((payload as LogPayload).level).toBe('error');
    expect(headers['X-App-Version']).toBe('2.3.4');
    expect(headers['X-Env']).toBe('production');
    expect(new Date((payload as LogPayload).timestamp).toISOString()).toBe(
      (payload as LogPayload).timestamp
    );
  });

  it('can force network logging in dev', () => {
    service.configureEnvironment('development', false);
    config.getRuntimeConfig.mockReturnValue({
      logging: { enabled: true, forceNetworkInDev: true },
    });

    const spy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    service.warn('check');

    expect(spy).toHaveBeenCalled();
    expect(api.postWithHeaders).toHaveBeenCalled();
  });

  it('respects logging.enabled === false', () => {
    service.configureEnvironment('production', true);
    config.getRuntimeConfig.mockReturnValue({ logging: { enabled: false } });

    service.error('ignored');

    expect(api.postWithHeaders).not.toHaveBeenCalled();
  });

  it('swallows network errors', async () => {
    service.configureEnvironment('production', true);
    config.getRuntimeConfig.mockReturnValue({ logging: { enabled: true } });
    api.postWithHeaders.mockRejectedValueOnce(new Error('fail'));

    expect(() => service.error('boom')).not.toThrow();
  });
});

