# Toastmasters Timer — Development Process

*Built with Google Antigravity Agentic AI | Human-in-the-loop workflow*

---

## 1. Requirement Analysis

**Inputs reviewed:**

| Input | Purpose |
|---|---|
| `Requirements.md` | Core features, tech stack constraints, success criteria |
| `Timer Script.md` | Official Toastmasters timing rules and role thresholds |
| `1175-time-record-sheet-ff.pdf` | Official Timer report format and column layout |

**User-supplied additions beyond initial requirements:**

- Full-screen color cards (entire browser window as the signal, not a small badge)
- Inline speaker editing without page reload
- A4-formatted print report with out-of-time highlighting
- Round Robin session mode (20-second single-alert)
- Manual numeric sequence control for speaker reordering
- Custom Event Types system for non-standard meeting segments
- Report indexing by phase (Speeches + Topics first, Evaluations second)

---

## 2. Planning

### v1.0 Plan — Initial Scope

- Docker environment with PHP + MySQL
- Meeting creation and speaker roster
- JavaScript stopwatch per speaker
- Color-card signals by role thresholds
- Basic timer report

### v2.0 Plan — Expanded Scope

Scope additions identified through iterative use in real meetings:

| Addition | Driver |
|---|---|
| Full-screen color cards | Projector visibility requirement |
| Inline quick edit | Live meeting speed requirement |
| Numeric sequence reorder | Last-minute agenda change handling |
| Round Robin mode | Specific meeting segment requirement |
| Custom Event Types | Non-standard segment support |
| Speech qualification logic + grace period | Toastmasters rule compliance |
| Report phase indexing | Timer's live report delivery workflow |

---

## 3. Implementation Phases

### Phase 1 — Foundation

- [x] Initialize Docker environment (`Dockerfile`, `docker-compose.yml`)
- [x] Configure MySQL health checks and service dependency order
- [x] Set up database schema (`meetings`, `speakers` tables)
- [x] Seed default event types and sample meeting data
- [x] Implement `src/db.php` PDO connection layer

### Phase 2 — Core Features

- [x] Build meeting management page (`meeting.php`)
- [x] Implement Add / Edit / Delete speaker forms
- [x] Build full-screen stopwatch (`timer.php`)
- [x] Implement Green → Yellow → Red color transitions
- [x] AJAX save of final time on timer stop
- [x] Build A4 print report (`report.php`)

### Phase 3 — Tuning and Polish

- [x] Implement Round Robin mode (20s neutral → single red alert)
- [x] Add numeric sequence reordering with conflict resolution
- [x] Add "Clear All Times" roster template function
- [x] Auto-calculate day-of-week from meeting date
- [x] Inline quick-edit interface for live meeting speed
- [x] OBS shift button for streaming overlay control

### Phase 4 — Event Types and Qualification

- [x] Design and implement `event_types` database table
- [x] Build `src/event_types.php` AJAX handler
- [x] Add Manage Events modal to meeting management
- [x] Implement event type auto-population in speaker forms
- [x] Implement speech qualification logic with 30-second grace period
- [x] Implement Table Topics lower-bound strict rule

### Phase 5 — Report Enhancement

- [x] Index report by phase (Section 1: Speeches + Topics, Section 2: Evaluations)
- [x] Add sequence numbering within each phase
- [x] Ensure Timer can read report in delivery order without searching

---

## 4. Key Design Decisions

| Decision | Rationale |
|---|---|
| **Client-side JS stopwatch** | Server-round-trip timing introduces drift. JS `Date.now()` is accurate and independent of network |
| **No JavaScript framework** | Avoids a build pipeline requirement. The app runs anywhere Docker runs, with no Node.js dependency |
| **CSS `@media print` for A4 report** | Avoids PDF generation libraries. Browser print to A4 is reliable and zero-dependency |
| **Numeric sequence reordering** | Up/Down buttons are too slow for real-meeting order changes. Typing a number is instant and conflict-resolving |
| **`event_type_id` nullable on speakers** | Speakers can retain custom thresholds without being linked to an event type — ensures backward compatibility |
| **Docker MySQL health checks** | Prevents the PHP container from crashing on startup due to MySQL not being ready |
| **Phase-indexed report** | Matches the Timer's actual live workflow — report Speeches + Topics first, Evaluations separately |

---

## 5. Validation Approach

| Phase | Method |
|---|---|
| Unit logic | Manual testing against known timing values |
| Docker startup | `docker compose up -d` on clean environments |
| Real meeting simulation | Test roster with all standard role types |
| Report printing | Browser print preview on A4 page size |
| Edge cases | Timer stop at exactly threshold, Round Robin at 20s boundary, Table Topics at 0:59 |

---

## 6. Collaboration Model

This project was built using a **human-in-the-loop agentic workflow** with Google Antigravity:

| Contributor | Responsibility |
|---|---|
| **Human (Developer)** | Toastmasters domain knowledge, timing rules, UX priorities, acceptance criteria, real-meeting validation |
| **AI Agent** | Code generation, implementation iteration, schema design, documentation drafting |

All product decisions were made by the human developer. The AI agent accelerated implementation speed and reduced boilerplate effort.
