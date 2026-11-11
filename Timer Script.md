# Toastmasters Timer — Role Reference Card

This document defines the official Toastmasters timing rules that drive the color-card thresholds in this application. The Timer's script is reproduced below as the canonical source.

---

## Timing Thresholds by Role

| Role | Green (Min) | Yellow (Target) | Red (Max) | Qualification Rule |
|---|---|---|---|---|
| Table Topic | 1:00 | 1:30 | 2:00 | Must reach 1:00 to qualify |
| Ice Breaker Speech | 4:00 | 5:00 | 6:00 | Must reach 4:00 to qualify |
| Prepared Speech (standard) | 5:00 | 6:00 | 7:00 | Must reach 5:00 to qualify |
| Evaluator | 2:00 | 2:30 | 3:00 | Must reach 2:00 to qualify |
| Round Robin | — | — | 0:20 | No green/yellow; single red alert |

> **Qualification Grace Period:** A speaker who goes over the Red (max) threshold by up to 30 seconds is still considered qualified. Beyond 30 seconds over, the speech is recorded as out-of-time.

---

## Qualification Rule — Table Topics Exception

Table Topics has a **lower-bound-only rule**: a speaker must reach the Green mark (1:00) to qualify. There is **no grace period on the lower bound** — a speaker who stops before 1:00 does not qualify regardless of how close they were.

---

## Timer's Announcement Script

> *Read aloud when introduced by the Toastmaster of the Day:*

---

"Greetings Mr./Madam Toastmaster, fellow Toastmasters, and guests.

As Timer, I will time the Table Topics speakers, formal speeches, and the evaluations. I will also alert each speaker of the time they have left, using the green, yellow, and red cards, which denote specific times remaining.

**Table Topics speakers** should limit their remarks to no more than 2 minutes.

- At **1 minute**, I will raise the green card.
- At **1 minute and 30 seconds**, I will raise the yellow card.
- At **2 minutes**, I will raise the red card.

**Ice Breaker speeches** should be 4–6 minutes in length.

- At **4 minutes**, I will raise the green card.
- At **5 minutes**, I will raise the yellow card.
- At **6 minutes**, I will raise the red card.

**Most other prepared speeches** should be 5–7 minutes in length.

- At **5 minutes**, I will raise the green card.
- At **6 minutes**, I will raise the yellow card.
- At **7 minutes**, I will raise the red card.

**Individual evaluations** should be between 2–3 minutes.

- At **2 minutes**, I will raise the green card.
- At **2 minutes and 30 seconds**, I will raise the yellow card.
- At **3 minutes**, I will raise the red card.

Thank you, Mr./Madam Toastmaster."

---

## How These Rules Are Implemented

Each speaker in the application is assigned an **Event Type** that stores the green, yellow, and red threshold values (in seconds). The JavaScript stopwatch monitors elapsed time and triggers a full-screen background color change at each threshold automatically — no manual card-raising required.

See [`IMPLEMENTATION_NOTES.md`](IMPLEMENTATION_NOTES.md) for technical details.