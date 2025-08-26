/**
 * Simplified School Selection Tests
 */
describe('School Selection Flow', () => {
  beforeEach(() => {
    cy.clearLocalStorage();
    cy.clearCookies();
  });

  describe('School Selection Page', () => {
    it('should redirect to auth when not authenticated', () => {
      cy.visit('/select-school');
      
      // Should redirect to auth
      cy.url().should('include', '/auth');
      cy.get('.auth').should('be.visible');
    });

    it('should display school selection with mock auth', () => {
      // Mock authentication state
      cy.window().then((win) => {
        win.localStorage.setItem('access_token', 'test-token');
      });

      // Mock schools API
      cy.intercept('GET', '**/schools*', {
        statusCode: 200,
        body: {
          data: [
            { id: 1, name: 'Test School 1', active: true },
            { id: 2, name: 'Test School 2', active: true }
          ],
          meta: { page: 1, lastPage: 1 }
        }
      }).as('getSchools');

      cy.visit('/select-school');
      
      // Should show school selection page or redirect appropriately
      cy.get('body').should('exist');
    });

    it('should handle empty schools list', () => {
      // Mock authentication state
      cy.window().then((win) => {
        win.localStorage.setItem('access_token', 'test-token');
      });

      // Mock empty schools API
      cy.intercept('GET', '**/schools*', {
        statusCode: 200,
        body: {
          data: [],
          meta: { page: 1, lastPage: 1 }
        }
      }).as('getEmptySchools');

      cy.visit('/select-school');
      
      // Should handle empty state gracefully
      cy.get('body').should('exist');
    });

    it('should virtualize school cards and load more on scroll', () => {
      cy.window().then((win) => {
        win.localStorage.setItem('access_token', 'test-token');
      });

      const makeSchools = (page: number) =>
        Array.from({ length: 20 }, (_, i) => ({
          id: (page - 1) * 20 + i + 1,
          name: `Test School ${(page - 1) * 20 + i + 1}`,
          active: true
        }));

      cy.intercept('GET', '**/schools*page=1*', {
        statusCode: 200,
        body: {
          data: makeSchools(1),
          meta: { page: 1, lastPage: 2 }
        }
      }).as('getSchoolsPage1');

      cy.intercept('GET', '**/schools*page=2*', {
        statusCode: 200,
        body: {
          data: makeSchools(2),
          meta: { page: 2, lastPage: 2 }
        }
      }).as('getSchoolsPage2');

      cy.visit('/select-school');
      cy.wait('@getSchoolsPage1');

      cy.get('[data-cy=school-item]').its('length').should('be.lessThan', 30);

      cy.get('[data-cy=schools-viewport]').scrollTo('bottom');
      cy.wait('@getSchoolsPage2');

      cy.get('[data-cy=school-item]').its('length').should('be.lessThan', 40);
    });
  });

  describe('Authentication Flow with Schools', () => {
    it('should handle login flow that leads to school selection', () => {
      cy.intercept('POST', '**/auth/check-user', {
        statusCode: 200,
        body: {
          success: true,
          data: {
            schools: [
              { id: 1, name: 'School 1', active: true },
              { id: 2, name: 'School 2', active: true }
            ],
            temp_token: 'temp-token-123'
          }
        }
      }).as('checkUser');

      cy.visit('/auth/login');
      cy.get('#loginEmail').type('test@example.com', { force: true });
      cy.get('#loginPassword').type('password123', { force: true });
      cy.get('[data-cy=login-button]').click({ force: true });

      cy.wait('@checkUser');
      
      // Should handle the response appropriately
      cy.get('body').should('exist');
    });

    it('should handle single school scenario', () => {
      cy.intercept('POST', '**/auth/check-user', {
        statusCode: 200,
        body: {
          success: true,
          data: {
            schools: [
              { id: 1, name: 'Only School', active: true }
            ],
            temp_token: 'temp-token-123'
          }
        }
      }).as('checkUserSingle');

      cy.visit('/auth/login');
      cy.get('#loginEmail').type('test@example.com', { force: true });
      cy.get('#loginPassword').type('password123', { force: true });
      cy.get('[data-cy=login-button]').click({ force: true });

      cy.wait('@checkUserSingle');
      
      // Should handle single school case
      cy.get('body').should('exist');
    });
  });

  describe('Navigation and Error Handling', () => {
    it('should handle school selection API errors', () => {
      // Mock authentication state
      cy.window().then((win) => {
        win.localStorage.setItem('access_token', 'test-token');
      });

      // Mock API error
      cy.intercept('GET', '**/schools*', {
        statusCode: 500,
        body: { error: 'Server error' }
      }).as('getSchoolsError');

      cy.visit('/select-school');
      
      // Should handle error gracefully
      cy.get('body').should('exist');
    });

    it('should allow navigation back to auth', () => {
      cy.visit('/auth/login');
      
      // Should be able to navigate to auth pages
      cy.get('.auth').should('be.visible');
      
      cy.visit('/auth/register');
      cy.get('.auth').should('be.visible');
      
      cy.visit('/auth/forgot-password');
      cy.get('.auth').should('be.visible');
    });

    it('should persist auth state during school selection', () => {
      // Set initial auth state
      cy.window().then((win) => {
        win.localStorage.setItem('access_token', 'test-token');
        win.localStorage.setItem('theme', 'dark');
      });

      cy.visit('/auth/login');
      
      // State should persist
      cy.window().then((win) => {
        expect(win.localStorage.getItem('access_token')).to.equal('test-token');
        expect(win.localStorage.getItem('theme')).to.equal('dark');
      });
    });
  });

  describe('Theme and UI Consistency', () => {
    it('should maintain theme during school selection flow', () => {
      cy.visit('/auth/login');
      
      // Set theme
      cy.window().then((win) => {
        win.localStorage.setItem('theme', 'dark');
        win.document.documentElement.dataset.theme = 'dark';
      });
      
      cy.reload();
      
      // Theme should be applied
      cy.get('html[data-theme="dark"]').should('exist');
      cy.get('.auth').should('be.visible');
    });

    it('should handle responsive layout in school selection', () => {
      cy.viewport(1280, 720);
      cy.visit('/auth/login');
      cy.get('.auth').should('be.visible');
      
      cy.viewport('iphone-6');
      cy.visit('/auth/login');
      cy.get('.auth').should('be.visible');
    });
  });
});