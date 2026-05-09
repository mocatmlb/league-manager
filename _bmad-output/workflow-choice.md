# BMad workflow choice — league-manager

**Owner:** Project (Mike) — recorded for AI agents and contributors.  
**Initial decision:** 2026-04-26  
**Revised:** 2026-04-26 — **full BMad Method track** is now the canonical default.

## Primary choice: **Full BMad Method track**

Product and substantial technical work follow the BMad Method phase order (after brownfield context in `project-context.md` and `docs/`):

1. **Analysis (as needed):** Brainstorm, product brief, domain research, market research — use when discovery is required before locking requirements.
2. **Planning:** **Create PRD** (`bmad-create-prd`) — required in **2-planning** for meaningful initiatives.
3. **Solutioning:** Optional **Create UX** → **Create Architecture** → **Create Epics and Stories** (architecture and epics/stories are required in **3-solutioning** per Method guidance).
4. **Gate:** **Check Implementation Readiness** before large implementation pushes.
5. **Implementation:** **Sprint Planning** → **Create Story** → **Dev Story** → **Code Review** (and related implementation skills); use **Correct Course** when scope shifts materially mid-cycle.

Agents should assume new epics/features go through this path unless an exception below applies.

## Rationale

- **Explicit product discipline:** PRD, UX (when used), architecture, and epics/stories keep implementation aligned with agreed scope and traceability.
- **Brownfield still fits:** Existing `docs/` and `_bmad-output/project-context.md` inform PRD/architecture; the Method track updates or supersedes slices of docs through BMad artifacts under `_bmad-output/planning-artifacts` and `_bmad-output/implementation-artifacts` per `_bmad/bmm/config.yaml`.

## Exceptions: **Lean mode** (use sparingly)

Use **Quick Dev** or a minimal story cycle **only** when:

- **Emergency production fix** with no scope for planning artifacts, or  
- **Trivial, non-behavioral change** (typo, comment, obvious one-line fix) that does not alter requirements or architecture.

For anything that changes user-visible behavior, data model, security, or integration contracts, default to the **full track** (at minimum: story + dev + review aligned to an existing epic/PRD when those already exist).

## Summary

| Mode | Use for |
|------|---------|
| **Full Method (default)** | Features, refactors that touch architecture, security-sensitive work, and any initiative that should have PRD → (UX) → architecture → epics/stories → readiness → sprint/story execution. |
| **Lean (exception)** | Approved hotfixes and trivial non-functional edits only. |

This file is the canonical answer to “PRD→architecture→stories→sprint vs context + Quick Dev/story cycle?” for this repo unless the team explicitly revises it.
