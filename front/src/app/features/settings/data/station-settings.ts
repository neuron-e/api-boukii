export interface StationSetting {
  id: number;
  key: string;
  value: string;
}

export const STATION_SETTINGS: StationSetting[] = [
  { id: 1, key: 'timezone', value: 'UTC' },
  { id: 2, key: 'currency', value: 'EUR' }
];
