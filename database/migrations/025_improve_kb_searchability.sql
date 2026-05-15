-- Migration 025: Improve KB entry titles and content for better keyword search matching
-- The LIKE-based search relies on natural-language terms appearing in title or content.
-- Entries with abstract titles (e.g. "1.9 Divisions of Play") won't surface for queries
-- like "age of juniors" or "how old to play seniors". This migration adds searchable
-- aliases and improves content phrasing on the most query-prone entries.

-- ─── 1.9 Divisions of Play — add "age eligibility" to title and content ───────
UPDATE knowledge_base
SET
    title   = '1.9 Divisions of Play — Age Eligibility by Division',
    content = 'Age requirements and division eligibility for the 2026 District 8 Travel League:\n\n- INTERMEDIATE (50/70 diamond): Ages 11–13\n- JUNIORS: Ages 12–15. 15-year-olds require board approval and are restricted to the regular season only — they may not pitch.\n- SENIORS: Ages 12–16. 12-year-olds require board approval and are restricted to the regular season only — they may not pitch.\n\nDivision placement is based on the player\'s Little League playing age as determined by the official Little League age eligibility rules.'
WHERE title = '1.9 Divisions of Play — Age Eligibility by Division'
   OR title = '1.9 Divisions of Play';

-- ─── 2.6 Game Start Times — "start time", "what time", "when do games start" ──
UPDATE knowledge_base
SET
    title   = '2.6 Game Start Times — Weekday and Weekend Rules',
    content = 'When do games start? All games start at the scheduled time. Teams must plan warm-ups accordingly.\n\n- Weekdays (Mon–Fri): Games may NOT start before 6:00 PM\n- Weekends (Sat–Sun): Games may be scheduled at any time. Sites shared by multiple teams must use these start times: 9:00 AM, 12:00 PM, 3:00 PM, or 6:00 PM\n- Sunday restriction: No regular season Sunday game should be scheduled before 11:00 AM out of respect for religious observance\n- A game may begin early with umpire-in-chief approval if both teams are ready'
WHERE title LIKE '2.6 Game Start Times%';

-- ─── 2.6.3 Grace Period — "late", "how long do we wait", "grace period" ────────
UPDATE knowledge_base
SET
    content = 'How long do you wait for a late team? A built-in grace period of 15 minutes applies ONLY when the entire visiting team is delayed in arriving. All other delays are at the umpire\'s discretion. After the grace period lapses, the game may be declared a forfeit.'
WHERE title LIKE '2.6.3 Grace Period%';

-- ─── 3.3 Mercy Rule — "run rule", "how many runs", "called game" ───────────────
UPDATE knowledge_base
SET
    title   = '3.3 Mercy Rule (Run Rule) — When a Game Ends Early',
    content = 'The mercy rule (also called the run rule) ends the game early when one team leads by a large margin. Minimum play is NOT required in games shortened by the mercy rule. (LLB Rule 4.10(e))\n\nTier 1 — After 4 complete innings (3½ if home team leads): 15+ run lead\nTier 2 — After 5 complete innings (4½ if home team leads): 10+ run lead\nTier 3 — After 6+ complete innings (5½+ if home team leads): 8+ run lead\n\nIn each case, the manager of the trailing team concedes the victory.'
WHERE title LIKE '3.3 Mercy Rule%';

-- ─── 1.15.1–1.15.2 Umpire fees — "how much", "umpire cost", "pay the ump" ─────
UPDATE knowledge_base
SET
    title   = '1.15.1–1.15.2 Umpire Fee Schedule — How Much to Pay',
    content = 'How much do umpires cost? Umpires must be paid before the game starts.\n\nIntermediate Baseball:\n- 1 umpire: $70 total ($35 per team)\n- 2 umpires: $50 each (each team pays one umpire)\n\nJunior Baseball:\n- 1 umpire: $100 total ($50 per team)\n- 2 umpires: $80 each (each team pays one umpire)\n\nSenior Baseball:\n- 1 umpire: $100 total ($50 per team)\n- 2 umpires: $80 each (each team pays one umpire)'
WHERE title LIKE '1.15.1%';

-- ─── 2.8 Game Reporting — "how do I report", "submit score", "score reporting" ─
UPDATE knowledge_base
SET
    title   = '2.8 Game Reporting — How and When to Submit Scores',
    content = 'How do I report a game score? The manager of the WINNING team must report the score on the same day as the game, no later than 24 hours after the game concludes. Scores are submitted via district8travelleague.com.\n\nPenalty: Any unreported game counts as a 7-0 forfeit loss for BOTH teams for playoff seeding purposes.'
WHERE title LIKE '2.8 Game Reporting%';

-- ─── 2.13 Pitch Count — "pitch count", "how many pitches", "pitching limit" ────
UPDATE knowledge_base
SET
    title   = '2.13 Pitch Count Certification — Tracking and Disputes',
    content = 'Pitch count tracking is required. Each team must maintain a separate paper pitch count log during the game. At the end of the game, the log must be signed by the opposing manager or acting manager.\n\nPitch count limits follow the official Little League Baseball pitch count rules (see the Little League Rulebook for limits by age group).\n\nDisputes: The home plate umpire resolves discrepancies. If unresolvable, the Division Director decides.'
WHERE title LIKE '2.13 Pitch Count%';

-- ─── 1.16.3 Bat Rules — "what bats are legal", "bbcor", "usabat", "wood bat" ──
UPDATE knowledge_base
SET
    title   = '1.16.3 Bat Rules — Legal Bats by Division',
    content = 'What bats are legal? Bat rules by division:\n\n- Intermediate Baseball: USABat Standard or BBCOR certified (or wood)\n- Junior Baseball: USABat Standard or BBCOR certified (or wood)\n- Senior Baseball: BBCOR certified ONLY (or wood) — USABat bats are NOT permitted in Senior Baseball\n\nBats must display the manufacturer certification marking. Any bat explicitly prohibited by Little League Baseball is not allowed.\n\n2026 Change: Pine tar and similar grip substances are now permitted at all levels. Batters may apply pine tar to the grip area.'
WHERE title LIKE '1.16.3 Bat Rules%';

-- ─── 2.5.3–2.5.4 Forfeit/No Contest — "forfeit", "no show", "didn't show up" ──
UPDATE knowledge_base
SET
    title   = '2.5.3–2.5.4 Forfeits and No-Contest — What Happens When a Team Doesn\'t Show',
    content = 'What happens if a team doesn\'t show up?\n\nForfeit (one team fails to appear or can\'t meet lineup requirements after the 15-minute grace period): The home plate umpire declares a forfeit. Score is recorded as 7-0.\n\nNo Contest (both teams fail to appear or both can\'t meet lineup requirements): The game is declared a No Contest — it is neither played nor eligible for rescheduling.'
WHERE title LIKE '2.5.3%';

-- ─── 3.5 Minimum Play — "playing time", "must play", "minimum innings" ─────────
UPDATE knowledge_base
SET
    title   = '3.5 Minimum Play Rule — Required Playing Time for Every Player',
    content = 'Every player in attendance must play. Minimum playing time requirements:\n\n12 players or fewer: Each player must play a minimum of 2 innings (6 consecutive defensive outs) in the field AND bat at least once.\n\n13 players or more: Each player must play 2 innings or 6 defensive outs (not required to be consecutive) in the field AND bat at least once.\n\nNote: Minimum play is NOT required in games shortened by the mercy rule. Substitute players have additional protections — see Rule 2.11.3.'
WHERE title LIKE '3.5 Minimum Play%';
