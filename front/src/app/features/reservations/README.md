# Reservations Feature

This module showcases a reservations list with mock data.

## Development
- Route is registered under `/reservations`.
- `ReservationsMockService` provides sample reservations.
- Filters allow combining reservation type, course, payment and search.
- Clicking a row opens `ReservationDetailComponent` inside a material dialog.

Run unit tests with:
```bash
npm test src/app/features/reservations/reservations-list/reservations-list.component.spec.ts
```

