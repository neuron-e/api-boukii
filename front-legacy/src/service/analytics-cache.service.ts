import { Injectable } from '@angular/core';
import { BehaviorSubject, Observable } from 'rxjs';

export interface CacheEntry {
  key: string;
  data: any;
  timestamp: number;
  ttl: number; // Time to live in milliseconds
}

@Injectable({
  providedIn: 'root'
})
export class AnalyticsCacheService {

  private cache = new Map<string, CacheEntry>();
  private cacheSize = 0;
  private readonly maxSize = 100; // Máximo 100 entradas en cache
  private readonly defaultTtl = 5 * 60 * 1000; // 5 minutos por defecto

  // Observable para monitorear el estado del cache
  private cacheStatsSubject = new BehaviorSubject<any>({
    size: 0,
    hitRate: 0,
    totalHits: 0,
    totalMisses: 0
  });

  public cacheStats$ = this.cacheStatsSubject.asObservable();

  private stats = {
    hits: 0,
    misses: 0
  };

  get(key: string): any {
    const entry = this.cache.get(key);

    if (!entry) {
      this.stats.misses++;
      this.updateStats();
      return null;
    }

    // Verificar si ha expirado
    if (Date.now() > entry.timestamp + entry.ttl) {
      this.cache.delete(key);
      this.cacheSize--;
      this.stats.misses++;
      this.updateStats();
      return null;
    }

    this.stats.hits++;
    this.updateStats();
    return entry.data;
  }

  set(key: string, data: any, ttl: number = this.defaultTtl): void {
    // Si el cache está lleno, eliminar la entrada más antigua
    if (this.cacheSize >= this.maxSize) {
      this.evictOldest();
    }

    const entry: CacheEntry = {
      key,
      data,
      timestamp: Date.now(),
      ttl
    };

    this.cache.set(key, entry);
    this.cacheSize++;
    this.updateStats();
  }

  delete(key: string): boolean {
    const deleted = this.cache.delete(key);
    if (deleted) {
      this.cacheSize--;
      this.updateStats();
    }
    return deleted;
  }

  clear(): void {
    this.cache.clear();
    this.cacheSize = 0;
    this.stats.hits = 0;
    this.stats.misses = 0;
    this.updateStats();
  }

  has(key: string): boolean {
    const entry = this.cache.get(key);
    if (!entry) {
      return false;
    }

    // Verificar si ha expirado
    if (Date.now() > entry.timestamp + entry.ttl) {
      this.cache.delete(key);
      this.cacheSize--;
      return false;
    }

    return true;
  }

  getKeys(): string[] {
    return Array.from(this.cache.keys());
  }

  getSize(): number {
    return this.cacheSize;
  }

  private evictOldest(): void {
    let oldestKey: string | null = null;
    let oldestTimestamp = Date.now();

    for (const [key, entry] of this.cache) {
      if (entry.timestamp < oldestTimestamp) {
        oldestTimestamp = entry.timestamp;
        oldestKey = key;
      }
    }

    if (oldestKey) {
      this.cache.delete(oldestKey);
      this.cacheSize--;
    }
  }

  private updateStats(): void {
    const total = this.stats.hits + this.stats.misses;
    const hitRate = total > 0 ? (this.stats.hits / total) * 100 : 0;

    this.cacheStatsSubject.next({
      size: this.cacheSize,
      hitRate: Math.round(hitRate * 100) / 100,
      totalHits: this.stats.hits,
      totalMisses: this.stats.misses
    });
  }
}
