import './commands';

// Stub de matchMedia con soporte para prefers-reduced-motion
if (!('matchMedia' in window)) {
  // @ts-expect-error cypress env
  window.matchMedia = (q: string) => ({
    matches: q.includes('prefers-reduced-motion'),
    media: q,
    onchange: null,
    addListener: () => {}, removeListener: () => {},
    addEventListener: () => {}, removeEventListener: () => {},
    dispatchEvent: () => false,
  });
}

// Silenciar ruidos de navegador sin ocultar errores reales
Cypress.on('uncaught:exception', (err) => {
  const m = err?.message || '';
  if (
    m.includes('ResizeObserver loop') ||
    m.includes('Script error') ||
    m.includes('Failed to fetch')
  ) return false;
  return true;
});
beforeEach(() => {
  // 1) CRITICAL for CI: runtime-config primero de todo (algunas pantallas lo leen antes de bootstrap)
  cy.intercept('GET', '/assets/config/runtime-config.json', { 
    fixture: 'config/runtime-config.json',
    delay: 0 // No artificial delay in CI for faster, more deterministic tests
  }).as('runtime');

  // 2) inyectar CSS para desactivar animaciones/transiciones (evita flakes de layout en headless)
  cy.document().then((doc) => {
    const style = doc.createElement('style');
    style.innerHTML = `
      *, *::before, *::after { transition: none !important; animation: none !important; }
      html:focus-within { scroll-behavior: auto !important; }
    `;
    doc.head.appendChild(style);
  });

  // 3) tema por defecto coherente con la app
  window.localStorage.setItem('theme', 'light');
  document.documentElement.classList.remove('dark');
  document.body.classList.remove('dark');

  // 4) Basic auth endpoints with deterministic timing for CI
  cy.intercept('GET', '**/me', { fixture: 'auth/me.json', delay: 0 }).as('me');
  cy.intercept('GET', '**/context', { fixture: 'auth/context.json', delay: 0 }).as('context');
  cy.intercept('GET', '**/feature-flags', { fixture: 'auth/feature-flags.json', delay: 0 }).as('flags');

  // 5) Data endpoints with consistent timing
  cy.intercept('GET', '**/schools*', { fixture: 'schools/list.json', delay: 0 }).as('schools');
  cy.intercept('GET', '**/seasons*', { fixture: 'seasons/list.json', delay: 0 }).as('seasons');
  cy.intercept('GET', '**/clients*', { fixture: 'clients/list.json', delay: 0 }).as('clients');
  cy.intercept('GET', '**/clients/*', { fixture: 'clients/detail.json', delay: 0 }).as('clientDetail');

  // 6) Improved fallback with deterministic responses by route pattern
  cy.intercept({ method: /GET|POST|PUT|PATCH|DELETE/, url: '**/api/**' }, (req) => {
    // More specific route-based responses for better test reliability
    const ok = { ok: true, timestamp: Date.now() };
    
    if (req.url.includes('/clients')) {
      return req.reply({ 
        statusCode: 200, 
        body: { data: [], meta: { total: 0, page: 1, limit: 10 }, ...ok },
        delay: 0
      });
    }
    
    if (req.url.includes('/auth/')) {
      return req.reply({ 
        statusCode: 200, 
        body: { success: true, data: {}, ...ok },
        delay: 0
      });
    }
    
    // Default fallback
    return req.reply({ 
      statusCode: 200, 
      body: { success: true, data: {}, ...ok },
      delay: 0
    });
  }).as('apiFallback');
});
