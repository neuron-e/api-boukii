# Charmey export analysis (legacy vs current)

Date: 2026-03-05

## Scope
Compare:
- **Current export** (`CoursesBySeasonExport` + `CourseDetailsExport`) against current schema (`courses`, `course_dates`, ...).
- **Legacy export** (`CoursesBySeasonLegacyExport`) against legacy schema (`courses2`, `course_dates2`, ...).

Goal: identify what extra data can be extracted from legacy and include it in the legacy export.

## Data sources reviewed
- Current DB: `boukii_pro` (school_id=8, ESS Charmey)
- Legacy dump imported to: `boukii_legacy_tmp` from `D:\Descargas\quental_boukii (1).sql`

## Current DB snapshot (school 8)
- Courses: **15**
- Date range: **2024-10-11 -> 2025-03-16**
- Course dates: **485**
- Groups: **304**
- Subgroups: **652**
- Booking users: **4576**

## Legacy DB snapshot (school 8)
- Courses2: **50**
- Date range: **2023-09-11 -> 2024-03-24** (23-24 season)
- Course dates2 in season window: **43**
- Groups2: **344**
- Subgroups2: **593**
- Booking users2 in season window: **0** (for this dump/window)

## Legacy schema fields available (high-value)
From `courses2` and related tables, legacy has reliable data for:
- Course taxonomy:
  - `course_supertype_id` (collective/private)
  - `course_type_id` + `course_types.name`
  - `sport_id` + `sports.name`
- Availability / booking constraints:
  - `duration`, `duration_flexible`
  - `date_start_res`, `date_end_res`, `day_start_res`, `day_end_res`
  - `hour_min`, `hour_max`
  - `online`, `confirm_attendance`
- Context:
  - `station_id` + `stations.name`
  - `group_id` (legacy grouping key)
- Group structure:
  - total groups/subgroups
  - subgroup capacity (`SUM(course_groups_subgroups2.max_participants)`)
  - grouped degree ids/names
- Session-level booking aggregates (if present in dataset):
  - booked participants
  - booked amount
  - paid amount

## Changes implemented in legacy export
Enhanced `CoursesBySeasonLegacyExport` and `CoursesBySeasonLegacySheet`:
- Added joins for `course_types`, `sports`, `stations`.
- Added aggregated subqueries for:
  - group/subgroup counts and capacity
  - degree ids + degree names
  - booking stats by course/date/hour
- Expanded exported columns from **12** to **36**.

### New columns now exported
- Course type ID / name
- Sport name
- Station ID / station
- Duration / flexible duration
- Reservation start/end/day/hour constraints
- Legacy group ID
- Confirm attendance / online
- Groups count / subgroups count / subgroup total capacity
- Degree IDs / degree names
- Booked participants / booked amount / paid amount

## Technical validation
- Export class syntax validated (`php -l`) for both modified files.
- Runtime test via tinker against `boukii_legacy_tmp` succeeded:
  - sheet count: 1
  - row count: 50

## Notes for next step
1. If you want a strict "23-24 season" parameter in API for legacy, add an explicit `season_name`/`season_from-to` helper for legacy export endpoint (legacy DB has no usable `seasons` rows for school 8 in this dump).
2. If desired, include localized labels (FR/EN/ES/DE/IT) for new legacy columns.
3. If a different legacy dump contains booking rows for Charmey 23-24, the booking stats columns will populate automatically with the current implementation.
