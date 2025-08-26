export interface SchoolSetting {
  id: number;
  name: string;
  value: string;
}

export const SCHOOL_SETTINGS: SchoolSetting[] = [
  { id: 1, name: 'name', value: 'Escuela Surf' },
  { id: 2, name: 'location', value: 'Playa Norte' }
];
