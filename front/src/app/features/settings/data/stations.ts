export interface Station {
  id: number;
  name: string;
}

export const MOCK_STATIONS: Station[] = [
  { id: 1, name: 'Station One' },
  { id: 2, name: 'Station Two' },
  { id: 3, name: 'Station Three' }
];

export const MOCK_SELECTED_STATION_IDS: number[] = [1, 3];
