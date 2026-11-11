# Toastmasters Timer — Technical Design Document

*Covers: Docker configuration, database schema, backend components, and frontend architecture.*

---

## 1. System Overview

The application is a three-tier web system:

- **Presentation Layer** — PHP-rendered HTML pages with vanilla JavaScript for stopwatch logic and AJAX interactions
- **Application Layer** — PHP 8.2 running on Apache inside Docker
- **Data Layer** — MySQL 8.0 in a separate Docker container

All timing thresholds are stored in the database and passed to the client at page load. The stopwatch runs entirely client-side for real-time accuracy.

---

## 2. Docker Configuration

**File:** `docker-compose.yml`

### Problem Solved

PHP was attempting to connect to MySQL before MySQL had finished initializing, causing intermittent connection errors on first startup.

### Solution

Added MySQL health checks and proper service dependencies:

| Setting | Value | Purpose |
|---|---|---|
| Health check command | `mysqladmin ping` | Detects MySQL readiness |
| Check interval | 10 seconds | Polling frequency |
| Timeout | 5 seconds | Max wait per check |
| Retries | 5 | Attempts before marking unhealthy |
| Start period | 15 seconds | Grace period for MySQL initialization |
| Web service dependency | `db: condition: service_healthy` | Blocks web start until DB is ready |
| Web restart policy | `on-failure:3` | Retries if connection fails on start |

---

## 3. Database Schema

### 3.1 `meetings` Table

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(255) | NOT NULL | Meeting name or number |
| `date` | DATE | NOT NULL | Meeting date |
| `location` | VARCHAR(255) | NULL | Venue or platform |
| `theme` | VARCHAR(255) | NULL | Meeting theme |
| `created_at` | TIMESTAMP | DEFAULT NOW | Record creation time |

### 3.2 `speakers` Table

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | Primary key |
| `meeting_id` | INT | FK → meetings.id | Owning meeting |
| `name` | VARCHAR(255) | NOT NULL | Speaker name |
| `role` | VARCHAR(255) | NOT NULL | Role label |
| `event_type_id` | INT | FK → event_types.id, NULL | Linked event type (optional) |
| `min_time` | INT | NOT NULL | Green threshold (seconds) |
| `target_time` | INT | NOT NULL | Yellow threshold (seconds) |
| `max_time` | INT | NOT NULL | Red threshold (seconds) |
| `final_time` | INT | NULL | Recorded elapsed time (seconds) |
| `sequence` | INT | DEFAULT 0 | Display sort order |

### 3.3 `event_types` Table

| Column | Type | Constraints | Description |
|---|---|---|---|
| `id` | INT | PK, AUTO_INCREMENT | Primary key |
| `meeting_id` | INT | FK → meetings.id, NULL | Owning meeting (NULL = global) |
| `name` | VARCHAR(255) | NOT NULL | Event type label |
| `min_time` | INT | NOT NULL | Green threshold (seconds) |
| `target_time` | INT | NOT NULL | Yellow threshold (seconds) |
| `max_time` | INT | NOT NULL | Red threshold (seconds) |
| `is_default` | TINYINT(1) | DEFAULT 0 | 1 = system default |
| `created_at` | TIMESTAMP | DEFAULT NOW | Record creation time |

### 3.4 Seed Data

The schema seeds the following global defaults on first run:

| Event Type | Green | Yellow | Red |
|---|---|---|---|
| Prepared Speech | 5:00 | 6:00 | 7:00 |
| Evaluator | 2:00 | 2:30 | 3:00 |
| Table Topic | 1:00 | 1:30 | 2:00 |
| Round Robin | — | — | 0:20 |
| Ice Breaker | 4:00 | 5:00 | 6:00 |

---

## 4. Backend Components

### 4.1 `src/db.php`

- Establishes PDO connection to MySQL
- Provides shared query helper functions used across all pages

### 4.2 `src/event_types.php`

AJAX endpoint and helper functions for the Event Types system:

| Function | Description |
|---|---|
| `getEventTypes($meeting_id)` | Fetch all event types for a meeting (includes global defaults) |
| `createEventType($data)` | Insert a new event type record |
| `updateEventType($id, $data)` | Update name and thresholds for an event type |
| `deleteEventType($id)` | Remove a user-created event type |

AJAX actions handled: `get`, `create`, `update`, `delete`

---

## 5. Frontend Components

### 5.1 `meeting.php` — Roster Management

**Responsibilities:**
- Display meeting header (name, date, day-of-week, theme, venue)
- Render speaker roster with sequence numbers and timing details
- Handle Add Speaker, Edit Speaker, Delete Speaker forms
- Host the Manage Events modal for custom event type CRUD
- Provide "Clear All Times" action

**Key UI behaviours:**
- Event Type dropdown → auto-populates min/target/max fields via JavaScript
- Sequence number input → triggers reorder AJAX call with conflict resolution
- Inline quick-edit → overlays the speaker row without a page reload

### 5.2 `timer.php` — Full-screen Stopwatch

**Responsibilities:**
- Render the full-screen stopwatch display for a selected speaker
- Drive color-card transitions (Green → Yellow → Red) by comparing elapsed seconds to stored thresholds
- Save final time to the database via AJAX on Stop

**Timer logic:**

```
if (elapsed >= max_time):
    background = RED
elif (elapsed >= target_time):
    background = YELLOW
elif (elapsed >= min_time):
    background = GREEN
else:
    background = NEUTRAL (dark)
```

**Round Robin exception:**
```
if role == "Round Robin":
    show RED at 20 seconds only
    suppress GREEN and YELLOW
```

**Data flow:**
1. PHP queries `speakers JOIN event_types` to fetch thresholds
2. Thresholds are embedded as JavaScript variables at page load
3. JS `setInterval` checks elapsed time every 500ms
4. On Stop: AJAX `POST` to `timer.php?action=save` with `speaker_id` and `final_time`

### 5.3 `report.php` — A4 Timer Report

**Responsibilities:**
- Query all speakers for the meeting ordered by phase and sequence
- Render an indexed report with two sections
- Apply out-of-time highlighting based on qualification logic
- Support A4 print via `@media print` CSS

**Report sections:**

| Section | Roles Included |
|---|---|
| Section 1 — Speeches & Topics | Prepared Speech, Ice Breaker, Table Topic |
| Section 2 — Evaluations | Evaluator, General Evaluator, TTE |

**Qualification logic:**

```
qualified = (final_time >= min_time) AND (final_time <= max_time + 30)

Table Topics exception:
  lower bound is strict — final_time MUST be >= min_time
  no grace period on the lower bound
```

### 5.4 `index.php` — Meeting List

- Lists all meetings in reverse chronological order
- Provides entry points to meeting setup, timer, and report pages

---

## 6. Key Design Decisions

| Decision | Rationale |
|---|---|
| Client-side stopwatch (JS) | Server-round-trip timing would introduce drift; JS `Date.now()` is accurate and lag-free |
| No JavaScript framework | Avoids build pipeline dependency; ensures the app works on any machine with Docker |
| CSS `@media print` for report | Avoids PDF generation libraries; browser print produces reliable A4 output |
| Sequence-number reordering | Up/Down buttons are too slow for real-meeting agenda changes; numeric input is instant |
| `event_type_id` nullable | Speakers can have custom thresholds without being linked to an event type — backward compatible |
| Docker health checks | Eliminates race condition where PHP connects before MySQL is ready |

---

## 7. Testing Checklist

- [ ] `docker compose up -d` starts without errors
- [ ] Application is accessible at `http://localhost:8080`
- [ ] Meeting can be created, edited, and deleted
- [ ] Speakers can be added, edited, reordered, and deleted
- [ ] Event Type dropdown auto-populates timing fields
- [ ] Custom Event Type can be created and assigned to a speaker
- [ ] Stopwatch color changes fire at correct threshold times
- [ ] Round Robin shows no color until 20-second red threshold
- [ ] Timer Stop saves the recorded time to the database
- [ ] Report displays correct section groupings
- [ ] Out-of-time highlighting appears for speakers over max + 30s
- [ ] Report prints cleanly on A4 paper
- [ ] "Clear All Times" resets times but retains roster

---

## 8. Migration Notes

For environments upgrading from v1.x:

1. The `event_types` table is new — apply `schema.sql` to add it
2. Existing speakers will continue to work; `event_type_id` defaults to NULL
3. Speakers can optionally be linked to event types retroactively
4. The `sequence` column on `speakers` may need to be added manually if upgrading from the earliest schema version
