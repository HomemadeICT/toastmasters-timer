# Toastmasters Timer — Meeting Timing Web App

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)
![Status](https://img.shields.io/badge/Status-Production--Ready-brightgreen)

> A Dockerized web application that lets a Toastmasters Timer run live meeting timing, manage a speaker roster, and generate a print-ready A4 report — all from a browser on a projector screen.

---

## The Problem It Solves

Live Toastmasters meetings are time-sensitive and frequently chaotic. Agendas shift at the last minute, speakers swap positions, and the Timer needs to signal the entire room clearly without losing focus. Paper timing sheets introduce errors, and generic stopwatch apps have no concept of Toastmasters roles or thresholds.

This tool was built to solve all three problems: **roster flexibility**, **room-visible color signaling**, and **automatic reporting**.

---

## Screenshots

| Meeting setup | Roster & reordering |
|---|---|
| ![Create a meeting](docs/screenshots/meetings.png) | ![Speaker roster](docs/screenshots/roster.png) |

**Full-screen colour cards** — the whole tab changes colour at each role's thresholds, readable from across the room:

| Green | Yellow | Red |
|---|---|---|
| ![Green card](docs/screenshots/timer-green.png) | ![Yellow card](docs/screenshots/timer-yellow.png) | ![Red card](docs/screenshots/timer-red.png) |

**Print-ready A4 report:**

![Timer report](docs/screenshots/report.png)

---

## Key Features

| Feature | Description |
|---|---|
| 🎨 **Full-screen Color Cards** | The entire browser tab turns Green, Yellow, or Red at role-specific thresholds — visible from across a large room |
| 🔢 **Conflict-free Reordering** | Type a sequence number to move any speaker; conflicts resolve automatically |
| ⏱ **Round Robin Mode** | 20-second limit with a single clean red cue — no premature alerts |
| 📄 **A4 Print Report** | One-click report with out-of-time highlighting, formatted for A4 paper |
| ✏️ **Inline Quick Edit** | Edit speaker name, role, or timing thresholds without a page reload |
| 🗓 **Auto Day-of-Week** | Calculates and displays the correct weekday from the selected meeting date |
| 🔄 **Reusable Roster** | "Clear All Times" resets a meeting's recorded times while keeping the speaker list |
| 🗂 **Custom Event Types** | Create and save custom roles with unique timing thresholds (e.g., Q&A Session) |
| 📋 **Indexed Report** | Report groups Prepared Speeches and Table Topics first, Evaluations second — matching the Timer's live report flow |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────┐
│                  Browser (Client)                │
│   meeting.php │ timer.php │ report.php           │
│   Vanilla JS  │  AJAX     │  CSS @media print    │
└──────────────────────┬──────────────────────────┘
                       │ HTTP / AJAX
┌──────────────────────▼──────────────────────────┐
│              PHP 8.2 + Apache (Docker)           │
│   index.php  │  src/db.php  │  src/event_types  │
└──────────────────────┬──────────────────────────┘
                       │ PDO / MySQL
┌──────────────────────▼──────────────────────────┐
│              MySQL 8.0 (Docker)                  │
│   meetings  │  speakers  │  event_types          │
└─────────────────────────────────────────────────┘
```

**Design principles:**
- No framework dependencies — pure PHP, JS, and CSS for maximum longevity
- Client-side stopwatch for real-time accuracy independent of network latency
- All timing thresholds driven by database-stored role definitions
- Docker Compose ensures a one-command, repeatable deployment

---

## Timing Rules

| Role | Green | Yellow | Red |
|---|---|---|---|
| Prepared Speech (standard) | 5:00 | 6:00 | 7:00 |
| Ice Breaker Speech | 4:00 | 5:00 | 6:00 |
| Table Topic | 1:00 | 1:30 | 2:00 |
| Evaluator | 2:00 | 2:30 | 3:00 |
| Round Robin | — | — | 0:20 |
| Custom (user-defined) | configurable | configurable | configurable |

---

## One-Command Setup

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) with the Compose plugin, installed and running

### Start the App

```bash
docker compose up -d
```

The defaults work out of the box for local use. Before exposing the app beyond your machine, copy `.env.example` to `.env` and set your own database passwords; Compose picks the file up automatically. The database port is bound to `127.0.0.1` only.

Open in browser: [http://localhost:8080](http://localhost:8080)

### Stop the App

```bash
docker compose down
```

### Reset the Database

```bash
docker compose down -v
docker compose up -d
```

---

## Repository Map

```
toastmasters-timer/
│
├── docker-compose.yml       # Service orchestration
├── Dockerfile               # PHP + Apache image
│
├── index.php                # Entry point and meeting list
├── meeting.php              # Roster management and meeting editor
├── timer.php                # Full-screen stopwatch and color signaling
├── report.php               # Printable A4 timer report
│
├── src/
│   ├── db.php               # Database connection and query helpers
│   └── event_types.php      # Custom event type CRUD + AJAX handlers
│
├── css/                     # Application stylesheets
├── js/                      # Stopwatch and UI logic
│
└── sql/
    └── schema.sql           # Database schema and seed data
```

---

## Workflow

1. **Before the meeting** — Open the meeting, verify or adjust the speaker roster and sequence order.
2. **During Table Topics / Speeches** — Navigate to the Timer page. Start the stopwatch; the screen signals green, yellow, and red automatically.
3. **During Evaluations** — Same flow with evaluator-specific thresholds.
4. **Timer's Report** — Open the Report page; copy or print the indexed, role-grouped report.

---

## Project Story

The real story is how this tool was shaped by live meeting constraints and iterative tuning. See [`DEVELOPMENT_STORY.md`](DEVELOPMENT_STORY.md) for the full narrative.

---

## Technical Implementation

For database schema, component design decisions, and the custom event types system, see [`IMPLEMENTATION_NOTES.md`](IMPLEMENTATION_NOTES.md).

## Docs

- DevOps & Production checklist: [DEVOPS.md](DEVOPS.md)
- Ubuntu beginner setup & developer workflow: [UBUNTU-BEGINNER.md](UBUNTU-BEGINNER.md)

---

## AI Collaboration

Built as a **human-in-the-loop project** with Google Antigravity. I supplied the Toastmasters domain knowledge — timing rules, meeting workflow, UX constraints, and acceptance criteria. The AI agent accelerated implementation and iteration cycles.

The design decisions, workflow priorities, and real-meeting validation were entirely human-driven.

---

## Disclaimer

This is an independent, community-built tool. It is not affiliated with or endorsed by Toastmasters International; "Toastmasters" and its logo are trademarks of Toastmasters International.

---

## License

MIT — see [`LICENSE`](LICENSE).
