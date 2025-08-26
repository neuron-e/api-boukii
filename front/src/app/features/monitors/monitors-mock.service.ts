import { Injectable } from '@angular/core';

export interface Monitor {
  id: number;
  name: string;
  sports: string[];
  levels: string[];
  status: 'active' | 'inactive';
}

@Injectable({ providedIn: 'root' })
export class MonitorsMockService {
  private monitors: Monitor[] = [
    { id: 1, name: 'Alice Johnson', sports: ['Tennis', 'Swimming'], levels: ['Beginner', 'Advanced'], status: 'active' },
    { id: 2, name: 'Bob Smith', sports: ['Basketball'], levels: ['Intermediate'], status: 'inactive' },
    { id: 3, name: 'Carol Davis', sports: ['Soccer', 'Running'], levels: ['Beginner'], status: 'active' },
    { id: 4, name: 'David Wilson', sports: ['Tennis'], levels: ['Advanced'], status: 'inactive' },
    { id: 5, name: 'Eve Thompson', sports: ['Swimming'], levels: ['Intermediate', 'Advanced'], status: 'active' },
  ];

  getMonitors(): Monitor[] {
    return this.monitors;
  }
}

