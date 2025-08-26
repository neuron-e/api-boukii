import { UiStore } from './ui.store';

describe('UiStore theme persistence', () => {
  beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove('dark');
    document.body.classList.remove('dark');
  });

  it('setTheme persists to localStorage and applies class', () => {
    const store = new UiStore();
    store.setTheme('dark');
    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.body.classList.contains('dark')).toBe(true);
  });

  it('toggleTheme persists changes', () => {
    const store = new UiStore();
    store.setTheme('light');
    store.toggleTheme();
    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.body.classList.contains('dark')).toBe(true);
  });

  it('initTheme applies stored theme', () => {
    localStorage.setItem('theme', 'dark');
    const store = new UiStore();
    store.initTheme();
    expect(document.body.classList.contains('dark')).toBe(true);
  });
});
