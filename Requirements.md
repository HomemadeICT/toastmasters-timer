

---

# Toastmasters Timer Web App — Business Requirements Document

*Version 2.0 — Collaboratively designed with Google Antigravity AI*

---

## 1. Project Purpose

The Toastmasters Timer Web App is a **browser-based meeting timing tool** designed to help the Timer role at a Toastmasters meeting:

- Track the elapsed time for each speaker against their role-specific thresholds
- Signal the speaker and room using full-screen color cards (Green / Yellow / Red)
- Generate an accurate, indexed Timer report at the end of the meeting

The application is deployed locally via Docker and accessed through a browser on a projector-connected laptop, making it visible to the entire meeting room.

---

## 2. Glossary

| Term | Definition |
|---|---|
| **Timer** | The Toastmasters role responsible for tracking speaker times and issuing color signals |
| **Prepared Speech** | A formal speech delivered from a structured Toastmasters program (typically 5–7 min) |
| **Table Topic** | A 1–2 minute impromptu speech |
| **Evaluator** | A member who evaluates a Prepared Speech (2–3 min) |
| **Round Robin** | A rapid warm-up exercise where each participant speaks for up to 20 seconds |
| **Green Card** | Signals that the speaker has reached the minimum time threshold |
| **Yellow Card** | Signals that the speaker has reached the target time |
| **Red Card** | Signals that the speaker has reached the maximum time limit |
| **TMOD** | Toastmaster of the Day — the meeting chairperson |
| **GE** | General Evaluator — evaluates the overall meeting |
| **TTE** | Table Topics Evaluator |

---

## 3. Stakeholders

| Role | Responsibility |
|---|---|
| **Timer** | Primary end-user; operates the stopwatch during the meeting |
| **TMOD** | May configure the meeting roster before the meeting begins |
| **Meeting Club** | Benefits from accurate time reporting and consistent signals |

---

## 4. Tech Stack

### Required

| Technology | Version | Purpose |
|---|---|---|
| PHP | 8.2 | Server-side logic and HTML rendering |
| MySQL / MariaDB | 8.0 | Data persistence |
| Apache | 2.4 | Web server |
| Docker | Latest | Containerized deployment |
| Docker Compose | v2 | Service orchestration |

### Permitted

| Technology | Purpose |
|---|---|
| JavaScript (vanilla) | Client-side stopwatch and AJAX interactions |
| CSS (vanilla) | UI styling and print layout |

---

## 5. Deployment Requirements

The application must start with a **single command**:

```bash
docker compose up -d
```

Access point: `http://localhost:8080`

### Docker Services

| Container | Image | Purpose |
|---|---|---|
| `web` | PHP 8.2 + Apache | Application server |
| `db` | MySQL 8.0 | Database |

### Database Credentials (Development)

```
DB_HOST=db
DB_NAME=toast_timer
DB_USER=toast
DB_PASSWORD=toast123
```

---

## 6. Functional Requirements

### FR-1 — Meeting Management

| ID | Requirement |
|---|---|
| FR-1.1 | User can create a meeting with a name, date, and optional location/venue |
| FR-1.2 | System auto-calculates and displays the day-of-week from the selected date |
| FR-1.3 | User can edit meeting details at any time |
| FR-1.4 | User can apply "Clear All Times" to reset recorded times while retaining the roster |

---

### FR-2 — Speaker Roster Management

| ID | Requirement |
|---|---|
| FR-2.1 | User can add speakers with: name, role, and timing thresholds (green/yellow/red) |
| FR-2.2 | User can assign speakers to a pre-defined or custom Event Type to auto-populate thresholds |
| FR-2.3 | User can edit speaker details inline without a page reload |
| FR-2.4 | User can reorder speakers by entering a sequence number; conflicts resolve automatically |
| FR-2.5 | User can delete a speaker from the roster |

---

### FR-3 — Event Types (Custom Roles)

| ID | Requirement |
|---|---|
| FR-3.1 | System ships with built-in event types: Prepared Speech, Evaluator, Table Topic, Round Robin |
| FR-3.2 | User can create custom event types with a name and green/yellow/red thresholds |
| FR-3.3 | User can delete a custom event type |
| FR-3.4 | Event type thresholds auto-populate speaker timing fields when selected |

---

### FR-4 — Stopwatch Timer

| ID | Requirement |
|---|---|
| FR-4.1 | Each speaker has a dedicated full-screen stopwatch page |
| FR-4.2 | The stopwatch displays elapsed time in `MM:SS` format |
| FR-4.3 | Timer runs client-side using JavaScript for real-time accuracy |
| FR-4.4 | The entire page background changes to Green, Yellow, and Red at role-specific thresholds |
| FR-4.5 | Round Robin mode shows no color signal until the 20-second red threshold |
| FR-4.6 | User can Start, Stop, and Reset the timer |
| FR-4.7 | Stopping the timer saves the final time to the database via AJAX |

---

### FR-5 — Timer Report

| ID | Requirement |
|---|---|
| FR-5.1 | The report page displays all speakers with their role, timing thresholds, and recorded time |
| FR-5.2 | The report is **indexed by phase**: Section 1 = Prepared Speeches + Table Topics; Section 2 = Evaluations |
| FR-5.3 | Speakers who exceeded the Red threshold by more than 30 seconds are highlighted as out-of-time |
| FR-5.4 | The report is formatted for A4 paper printing via CSS `@media print` |
| FR-5.5 | The report can be printed with a single browser print action |

---

## 7. Non-Functional Requirements

| ID | Requirement |
|---|---|
| NFR-1 | The application must be usable by a non-technical user with no training beyond a 2-minute briefing |
| NFR-2 | The timer display must be legible from a minimum of 5 metres on a standard projector |
| NFR-3 | Color threshold changes must occur within 1 second of the elapsed time crossing a threshold |
| NFR-4 | The application must start cleanly on any Docker-capable machine with no manual database setup |
| NFR-5 | The application must not require internet access during a live meeting |
| NFR-6 | The codebase must remain framework-free to ensure long-term maintainability without a build pipeline |

---

## 8. Database Schema

### `meetings`

| Column | Type | Description |
|---|---|---|
| `id` | INT PK | Auto-increment primary key |
| `name` | VARCHAR | Meeting name/number |
| `date` | DATE | Meeting date |
| `location` | VARCHAR | Venue or platform (optional) |
| `theme` | VARCHAR | Meeting theme (optional) |
| `created_at` | TIMESTAMP | Record creation time |

### `speakers`

| Column | Type | Description |
|---|---|---|
| `id` | INT PK | Auto-increment primary key |
| `meeting_id` | INT FK | Foreign key to `meetings` |
| `name` | VARCHAR | Speaker name |
| `role` | VARCHAR | Role label |
| `event_type_id` | INT FK | Foreign key to `event_types` (nullable) |
| `min_time` | INT | Green threshold in seconds |
| `target_time` | INT | Yellow threshold in seconds |
| `max_time` | INT | Red threshold in seconds |
| `final_time` | INT | Recorded elapsed time in seconds |
| `sequence` | INT | Display order in roster |

### `event_types`

| Column | Type | Description |
|---|---|---|
| `id` | INT PK | Auto-increment primary key |
| `meeting_id` | INT FK | Owning meeting (NULL = global default) |
| `name` | VARCHAR | Event type label |
| `min_time` | INT | Green threshold in seconds |
| `target_time` | INT | Yellow threshold in seconds |
| `max_time` | INT | Red threshold in seconds |
| `is_default` | TINYINT | 1 = system default, 0 = user-created |

---

## 9. File Structure

```
toastmasters-timer/
│
├── docker-compose.yml
├── Dockerfile
│
├── index.php                  # Meeting list and entry point
├── meeting.php                # Roster management
├── timer.php                  # Full-screen stopwatch
├── report.php                 # A4 timer report
│
├── src/
│   ├── db.php                 # Database connection and helpers
│   └── event_types.php        # Custom event type management
│
├── css/                       # Stylesheets
├── js/                        # Stopwatch and UI JavaScript
│
└── sql/
    └── schema.sql             # Schema and seed data
```

---

## 10. Out of Scope

The following are **intentionally excluded** to keep the application simple and maintainable:

- User authentication or access control
- Multi-club or multi-meeting-room support
- Email or notification delivery
- Speech evaluation tools or scoring
- Cloud hosting or SaaS deployment
- Complex JavaScript frameworks or build pipelines

---

## 11. Success Criteria

The system is considered successful if all of the following are true:

- [ ] The application starts with `docker compose up -d` on any Docker-capable machine
- [ ] A non-technical user can set up a meeting roster in under 5 minutes
- [ ] The full-screen color card changes are visible on a projector from 5+ metres
- [ ] Timer stopping saves the recorded time to the database without a page refresh
- [ ] The report displays correctly grouped by phase and prints cleanly on A4 paper
- [ ] Round Robin mode shows no color signal until the 20-second threshold
- [ ] Custom event types can be created and assigned to speakers

---

## 12. Version History

| Version | Date | Summary |
|---|---|---|
| v1.0 | 2025-03 | Initial release — meeting setup, stopwatch, basic report |
| v1.1 | 2025-03 | Full-screen color cards, inline editing, A4 print report |
| v1.2 | 2025-03 | Round Robin mode, numeric sequence reordering |
| v1.3 | 2025-04 | Custom Event Types system |
| v1.4 | 2025-04 | Speech qualification logic, 30-second grace period |
| v2.0 | 2026-04 | Indexed report (phases), OBS shift button, UI conflict resolution |
