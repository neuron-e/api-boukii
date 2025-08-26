export interface Season {
  id: number;
  name: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
}

export const MOCK_SEASONS: Season[] = [
  {
    id: 1,
    name: 'Summer 2024',
    start_date: '2024-06-01',
    end_date: '2024-08-31',
    is_active: true
  },
  {
    id: 2,
    name: 'Winter 2024',
    start_date: '2024-12-01',
    end_date: '2025-02-28',
    is_active: false
  }
];
