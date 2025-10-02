# Booking Availability & Overbooking Controls

## 1. API Endpoints That Persist Bookings

### 1.1 Admin Panel (Angular)
- **POST /api/admin/bookings** → `App\Http\Controllers\Admin\BookingController@store`
  - Validates that every participant (`cart[*].client_id`) belongs to the main client.
  - Builds the “basket” summary and creates the parent `Booking` record inside a DB transaction.
  - For each cart line creates a `BookingUser`. For **collective** courses (`course_type = 1`) it runs a `lockForUpdate()` query on the matching `course_subgroups` table, counts active booking users, and only assigns a subgroup when `current_participants < max_participants`. If no subgroup has room the transaction is rolled back and the API responds with an error → this is the main overbooking guard at persistence time.
  - Extras and vouchers are persisted after the `BookingUser`s and a `BookingLog` entry is emitted.
- **POST /api/admin/bookings/update** → same controller, used by the admin editor. When collective bookings change date/subgroup it re-runs the same `lockForUpdate()` capacity check before applying the change.
- **Cancellation/Payments** (`bookings/cancel`, `bookings/refunds/{id}`, `bookings/payments/{id}`) adjust booking users and free capacity. Cancels keep tracking logs and flip status (`2 = cancelled`, `3 = partially cancelled`).

Auxiliary admin endpoints that front-end calls **before** hitting `store`:
- **POST /api/admin/courses/check-capacity** – loops subgroups, returns `available_slots`, flags `has_capacity`.
- **POST /api/admin/courses/check-availability** – uses `CourseSubgroup::getAvailableSubgroupsWithCapacity()` (cached 60s) to return subgroups with free seats for a `course_date_id`/`degree_id` pair.
- **POST /api/admin/courses/validate-booking** – iterates incoming cart, for collective lines collects subgroups with `hasAvailableSlots()` and indicates whether the booking is viable.
- **POST /api/admin/monitors/available`** and `POST /api/admin/monitors/available/{monitorId}` – expose monitor availability checks (see §2).

### 1.2 Public Booking Page (Slug iframe)
- **POST /slug/bookings** → `App\Http\Controllers\BookingPage\BookingController@store`
  - Creates the booking and booking users inside a transaction. When the payload already carries a `course_subgroup_id` it reuses that (which gives the monitor & degree). Unlike the admin flow **there is no server-side lock/capacity check at this point**; it assumes the front-end has pre-reserved a slot.
  - Extras and vouchers persisted analogously.
  - Methods `getMonitorsAvailable()` / `areMonitorsAvailable()` exist but are not wired into `store`.
- Supporting endpoints in `BookingPage\CourseController` provide availability matrices (`courses/availability/{id}`) and use the same monitor-availability helpers as the admin.

### 1.3 Generic REST API
- **POST /api/bookings** (`App\Http\Controllers\API\BookingAPIController@store`)
  - Thin wrapper around `BookingRepository::create()`; no guard rails. Consumers are expected to perform validation via the other capacity endpoints.

## 2. Monitor Availability Evaluation

### 2.1 Admin MonitorController (`App\Http\Controllers\Admin\MonitorController`)
- `POST /api/admin/monitors/available`
  - Filters monitors by:
    - Sport + minimum degree order (via `MonitorSportsDegree` + authorized degree pivot).
    - Whether any participant is an adult (`allow_adults` flag).
    - Participant language set (matches against monitor language slots 1–6).
    - Active assignment to the current school.
  - Final availability is computed by `Monitor::scopeAvailableBetween()` which excludes monitors that:
    - Already have booking users overlapping the requested `[startTime,endTime)` (status=1, booking status≠2).
    - Have non-working days (`monitor_nwds`) covering the slot.
    - Are already assigned to other `course_subgroups` whose course date overlaps the slot.
- `POST /api/admin/monitors/available/{id}` → wraps `Monitor::isMonitorBusy()` to double-check a specific monitor. `isMonitorBusy()` re-uses the same three sources (bookings, NWDs, subgroups) to answer.

### 2.2 Booking Page MonitorController & CourseController
- `POST /slug/monitors/available` duplicates the admin logic but works off the slug-authenticated school. It also considers client languages and adult flag.
- `BookingPage\CourseController@getDurationsAvailableByCourseDateAndStart` builds the list of start times/durations offered to the widget. For each candidate slot it calls the same monitor availability routines and only returns durations that still have at least one monitor free.

**Observation:** neither admin nor public `store()` re-validates monitor availability at persistence time; protection relies on the UI calling these endpoints just before confirming the booking.

## 3. Overbooking / Capacity Controls

### 3.1 Pre-flight Validation
- `CourseCapacityController` endpoints let the UI ask the backend for live capacity before calling `store()`:
  - `checkCapacity()` → bulk check for explicit subgroup IDs, returns `available_slots` and `has_capacity` per subgroup.
  - `checkAvailabilityByDate()` → cached view of subgroups for a date/degree, sums total available seats.
  - `validateBookingCapacity()` → runs through the incoming cart (only collective courses) and reports whether each line still has free seats.

### 3.2 Persistence-time Guards
- **Admin `BookingController@store` and `@update`**:
  - Wrap booking-user creation inside a DB transaction.
  - For collective lines: select candidate subgroups with `lockForUpdate()`, compute the current participant count (status=1, booking status≠2), assign the first subgroup with spare slots, or abort the transaction if none available. This removes the race condition that previously caused duplicated seats.
  - Logs every attempt (`BOOKING_CONCURRENCY_*`) to aid forensic investigations.
- **Cancellation flows** free up capacity by marking booking users `status=2`; logging includes the freed subgroup.
- **CourseSubgroup model** centralises availability helpers:
  - `hasAvailableSlots()` and `getAvailableSlotsCount()` run direct count queries (filtered by booking status) and can be cached (`cacheQuery`).
  - `getAvailableSubgroupsWithCapacity()` (used by capacity endpoints) caches results for 60s and returns `available_slots` plus metadata.

### 3.3 Public Booking Page
- Relies on the same helper methods to expose availability but **does not re-check capacity when persisting**. If two clients submit the same slot concurrently the second submission will succeed unless the front-end has refreshed the availability between the reservation steps. This is an area to tighten.

### 3.4 Generic API
- The REST `BookingAPIController@store` is essentially a bare create — consumers must call the capacity & monitor endpoints explicitly; no guards are enforced here.

## 4. Key Takeaways
- Admin workflow combines pre-flight capacity checks with pessimistic locking when inserting booking users; this is the main protection against overbooking.
- Monitor availability is evaluated through dedicated endpoints that filter by sport/degree/language/age constraints and ensure no clashes with existing bookings, non-working days, or parallel course assignments.
- BookingPage endpoints share most of the availability logic but missing transactional capacity enforcement at `store()` — this should be addressed if overbooking has been observed on the public flow.
- None of the flows re-check monitor availability during persistence; if monitor assignment needs to be guaranteed, similar locking or validation would need to be added server-side.

