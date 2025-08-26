export interface Season {
  id: number;
  name: string;
  year: number;
  active: boolean;
}

export const SEASONS: Season[] = [
  { id: 1, name: 'Summer', year: 2024, active: true },
  { id: 2, name: 'Winter', year: 2023, active: false }
];
