-- Migration 024: Replace generic rules KB entries with fully indexed 2026 D8 rules
-- Removes the 3 placeholder 'rules' entries from migration 021 and replaces them
-- with one entry per indexed rule, split per digestibility review recommendations.
-- Sort order: 1xxx = Section 1, 2xxx = Section 2, 3xxx = Section 3, 9xxx = Appendix

DELETE FROM knowledge_base WHERE category = 'rules';

INSERT INTO knowledge_base (category, title, content, is_active, sort_order) VALUES

-- ═══════════════════════════════════════════════════════════════════════════
-- SECTION 1: SEASON PRELIMINARIES
-- ═══════════════════════════════════════════════════════════════════════════

('rules', '1.1 Official League Rules',
'The official copy of the league rules is the digital copy posted on the District 8 Travel League website (district8travelleague.com). Printed or saved copies are not considered official.',
1, 1100),

('rules', '1.2 General Meetings',
'There are 2 mandatory preseason meetings: a rule interpretation meeting and a scheduling workshop. The agenda for each meeting is communicated in advance. Both meetings are mandatory for team managers.',
1, 1200),

('rules', '1.3 League Officials',
'The designated league officials are:\n- Mike O\'Connell — Division Director\n- Jennifer Bertollini — Umpire Assignor, (315) 254-5840\n- Jack Kaplan — Umpire in Chief\n- Victor Brouse — Assistant District Administrator\n- Dan Cavallo — District Administrator',
1, 1300),

('rules', '1.4 League Fees',
'League fee is $450 per team. This covers: 3 dozen baseballs and Little League patches per team. It also covers league-wide costs including website hosting, meeting space rental, umpire assignment fees, and misc supplies for the program coordinator.',
1, 1400),

('rules', '1.5 League Website',
'The official league website is district8travelleague.com. It is the single source of truth for schedules, game results, and standings. Registered managers and league officials can also submit schedule change requests and report scores through the website.',
1, 1500),

('rules', '1.6 Player Rosters',
'Recommended roster size is a minimum of 14 players and a maximum of 18 players per team.',
1, 1600),

('rules', '1.7 Managers and Coaches',
'Each team must have one adult manager (18+) and may have up to two adult assistant coaches on the roster. Additional volunteers are allowed but may not be on the field or in the dugout. All coaches and volunteers must be approved by their local Little League and must have passed a Little League-approved background check.\n\nThe manager and coaching staff must be accurately disclosed at registration. Failure to do so results in disqualification. Any mid-season change to manager or coaches must be reported to the Division Director within 24 hours.',
1, 1700),

('rules', '1.8 Player Eligibility',
'Any player who meets the Little League Age Eligibility requirements and lives within the boundaries of their Little League is eligible to be selected to a team roster.',
1, 1800),

('rules', '1.9 Divisions of Play',
'The league has three divisions based on age:\n- INTERMEDIATE (50/70 diamond): Ages 11–13\n- JUNIORS: Ages 12–15 (15-year-olds require board approval; restricted to regular season only and may not pitch)\n- SENIORS: Ages 12–16 (12-year-olds require board approval; restricted to regular season only and may not pitch)',
1, 1900),

('rules', '1.10 Minimum Teams per Division & Division Combinations',
'Each division requires at least 4 registered teams to operate separately. If a division has fewer than 4 teams, divisions are combined as follows:\n- Intermediate + Junior → combined as Junior Baseball (Junior rules and bat rules apply; Intermediate teams playing each other may use the 50/70 diamond)\n- Junior + Senior → combined as Senior Baseball (Senior rules and bat rules apply)\n- All divisions combined → Senior Baseball (Intermediate and Senior teams may NOT be scheduled against each other)',
1, 1101),

('rules', '1.11 Team Rosters',
'Rosters are maintained by each team. Teams do not need to submit a roster in advance but must produce one on request. Required information on the roster: player\'s full name, league playing age, uniform number, and home address. Roster additions and subtractions may be made at any time during the season.',
1, 1110),

('rules', '1.12 Team Uniforms',
'Each player must wear: shirt, pants, and stockings/stirrups (peds, ankle socks, or no socks are NOT permitted). Caps are required. Jerseys must display the District 8 Travel League logo, identify the sponsoring league, and show a uniform number on the back. Shirts must be tucked in at all times. Player names are recommended but not required. Variances may be requested with a valid reason.',
1, 1120),

('rules', '1.13 Managers and Coaches Dress Code',
'Managers and coaches must dress appropriately. Not permitted: open-toe shoes or sandals, gym shorts without a District 8 or Little League logo, tank tops, or t-shirts with profane/vulgar/objectionable content. Permitted: dress shorts and jean shorts (if no holes, stains, or excessive wear).',
1, 1130),

('rules', '1.14 Fields',
'Each team must provide a home field when designated as the home team. Requirements: properly lined field, grass cut to a reasonable height, regulation-size bases, outfield fence at least 4 feet high and safely constructed, foul poles extending at least 6 feet above the fence, and a protective fence in front of the dugout (even if temporary). Poor or unsafe conditions may result in cancellation at the umpire\'s discretion.',
1, 1140),

('rules', '1.15.1–1.15.2 Umpire Fee Schedule',
'Umpires are paid officials and must be paid before the game starts.\n\nIntermediate Baseball:\n- 1 umpire: $70 total ($35 per team)\n- 2 umpires: $50 each (each team pays one umpire)\n\nJunior & Senior Baseball:\n- 1 umpire: $100 total ($50 per team)\n- 2 umpires: $80 each (each team pays one umpire)',
1, 1151),

('rules', '1.15.3 No-Show Umpires',
'If an assigned umpire does not show, adult volunteer umpires may be used at the discretion of both coaches. Volunteer umpires are not trained or certified and should not be paid.',
1, 1153),

('rules', '1.15.4–1.15.5 Forfeit and No-Contest Umpire Fees',
'Forfeit (one team no-show): Umpires in attendance are entitled to a full game fee, payable by the forfeiting team.\n\nNo-Contest (both teams no-show or both fail lineup requirements): Umpires are entitled to a full game fee, payable by both scheduled teams.\n\nException: If the no-contest is caused by a scheduling error by the Umpire Assignor or Division Director, the scheduled teams are not subject to the fee and the game may be rescheduled.',
1, 1155),

('rules', '1.16.1 Catcher\'s Gear',
'Each team must have a full set of catcher\'s gear available: chest protector, shin guards, and mask with a dangling throat guard. A mask without a dangling throat protector is NOT permitted. A hockey-style helmet/mask must have a throat protector properly attached. If a team does not have one, they may borrow from the opposing team.',
1, 1161),

('rules', '1.16.2 Batting Helmets',
'Every batter-runner on the field must wear a NOCSAE-approved, undamaged batting helmet. Damaged helmets may not be used.',
1, 1162),

('rules', '1.16.3 Bat Rules by Division',
'Bat standards by division (bats must display manufacturer certification markings and must not be explicitly prohibited by Little League Baseball):\n\n- Intermediate Baseball: USABat Standard or BBCOR (or wood)\n- Junior Baseball: USABat Standard or BBCOR (or wood)\n- Senior Baseball: BBCOR Standard ONLY (or wood) — USABat bats are NOT permitted in Senior Baseball\n\n2026 Change: Pine tar and similar adhesive substances are now permitted at all levels. Batters may apply pine tar to the grip area of the bat.',
1, 1163),

('rules', '1.16.4 Shoes and Cleats',
'Both rubber and metal spikes are permitted at all levels.',
1, 1164),

('rules', '1.16.5 Bases',
'Little League Baseball prohibits anchored bases. Breakaway bases must be used. If a school field does not have breakaway bases, throw-down bases may be substituted. If throw-down bases are also unavailable, the host team must submit a written request to District 8 explaining the reason for non-compliance.',
1, 1165),

('rules', '1.16.6 Equipment Violations',
'Illegal or non-compliant equipment (bats, facemasks, skullcaps) will be removed from the game. Managers and coaches will be advised of the regulations before enforcement.',
1, 1166),

-- ═══════════════════════════════════════════════════════════════════════════
-- SECTION 2: THE SEASON
-- ═══════════════════════════════════════════════════════════════════════════

('rules', '2.1 Regular Season',
'The regular season provides a 12–16 game schedule. A playoff tournament may follow at the conclusion of the regular season.',
1, 2100),

('rules', '2.2 League Champions',
'A regular season champion is declared at the end of the season. Key eligibility rule: a team must complete at least 80% of their original schedule to be eligible for the championship.\n\nIf teams are tied: co-champions may be declared if both have completed 80% of their schedule. If only one team has reached 80%, the other team(s) have 7 days from the last regular season game to reach 80%. After that, only teams at or above 80% are eligible, evaluated by winning percentage. A maximum of two sets of awards will be distributed. If more than two teams are tied, no awards may be distributed.',
1, 2200),

('rules', '2.3.1 Post Season Format',
'At the Division Director\'s discretion, a short playoff may follow the regular season (approximately one week). The format — game count, structure, and division alignment — is determined by the Division Director. All regular season rules remain in effect during the post season, except pitching rules follow Tournament Pitching Rules as prescribed in the Little League Rulebook.',
1, 2310),

('rules', '2.3.2 Playoff Seeding and Tiebreakers',
'Tiebreakers for playoff positioning, applied in order:\n1. Head-to-head record between tied teams\n2. Fewest runs allowed head-to-head\n3. Fewest runs allowed overall\n4. Coin flip\n\nImportant: For playoff seeding and tiebreaking, un-played or unreported games count as a 7-0 loss for BOTH teams.',
1, 2320),

('rules', '2.4.1 Official Schedule',
'The official schedule is maintained on district8travelleague.com. All other copies — including those made by managers or umpires — are unofficial. All umpire assignments, no-show fees, and forfeit fees are based on the official schedule. Discrepancies must be reported to the Division Director.',
1, 2410),

('rules', '2.4.2 Over-Scheduling Objective',
'The program intentionally over-schedules the regular season (12–20 games) knowing that cancellations will occur, reducing the need to reschedule postponed games.',
1, 2420),

('rules', '2.4.3 Scheduling Parameters',
'Team managers create their schedule at the preseason scheduling workshop. Parameters:\n- First game: no earlier than Memorial Day, Monday May 25, 2026 (tentative)\n- Must have 1 game scheduled by Saturday, June 6, 2026 (tentative)\n- At least 1 game per week starting June 7, 2026 — with 1 weekday game (Mon–Thu) required each week\n- Must have 4 games scheduled by Saturday, June 27, 2026 (tentative)\n- All regular season games must be scheduled by Sunday, August 2, 2026 (tentative)\n- Games after the regular season deadline require Division Director approval',
1, 2430),

('rules', '2.4.4 Game Day Cancellations and Changes',
'Any cancellation or change on the day of the game must be reported by the HOME TEAM no later than 1.5 hours before the scheduled start time. The home team must notify: (1) the visiting team and (2) the Umpire Assignor.\n\n2026 Umpire Assignor: Jennifer Bertollini — (315) 254-5840 (cell)',
1, 2440),

('rules', '2.4.5 Acceptable Reasons for Cancellations and Schedule Changes',
'Only the following reasons are accepted for cancellations or schedule changes:\n1. Weather: inclement weather or poor field conditions resulting from inclement weather\n2. Conflicts: field conflicts or other conflicts resulting in insufficient players to field a lineup (even with substitutes)\n3. Location or time change only: changes that do not alter the game date but result in a new time or location on the same date',
1, 2450),

('rules', '2.5 Re-Scheduling Policy',
'After a cancellation, teams have 72 hours to agree on a reschedule date. The rescheduled game must be played within 2 weeks of the original date. If no agreement is reached, the Division Director assigns a date. During the final 2 weeks of the season, cancelled games must be rescheduled on the next available date.\n\nAll changes must be submitted through district8travelleague.com and are not official until approved by the Program Coordinator. Changes submitted through other means will not be considered.\n\nLimitation: Under "Cancellation Due to Conflicts," each team gets one (1) reschedule attempt. Games cancelled more than once for non-weather reasons will not be approved for further rescheduling. Weather cancellations may be rescheduled as needed.',
1, 2500),

('rules', '2.5.3–2.5.4 No-Shows, Forfeits, and No Contests',
'Forfeit (one team fails to appear or meet lineup requirements after the grace period): The home plate umpire declares a forfeit with a score of 7-0.\n\nNo Contest (both teams fail to appear or meet lineup requirements after the grace period): The game is declared a No Contest — it is neither played nor eligible for rescheduling.',
1, 2530),

('rules', '2.6 Game Start Times',
'All games start at the scheduled time. Teams must plan warm-ups accordingly. A game may begin early with umpire-in-chief approval if both teams are ready.\n\n- Weekdays (Mon–Fri): No game may start before 6:00 PM\n- Weekends (Sat–Sun): Games may be scheduled at any time. Sites shared by multiple teams must use these start times: 9:00 AM, 12:00 PM, 3:00 PM, or 6:00 PM\n- Sunday restriction: No regular season Sunday game should be scheduled before 11:00 AM (out of respect for religious observance)',
1, 2600),

('rules', '2.6.3 Grace Period',
'A built-in grace period of 15 minutes applies ONLY when the entire visiting team is delayed in arriving. All other delays are at the umpire\'s discretion.',
1, 2630),

('rules', '2.7 Curfew and Time Limits',
'No new inning may start after 8:25 PM. The book rule determines whether it is a completed game. Once a game starts, the umpire-in-chief determines whether sufficient daylight remains to continue safely.\n\nLighted fields: local curfew rules apply. In a doubleheader, no new inning starts within 15 minutes of the 2nd game\'s start time; the first game must be completed or halted by the 2nd game\'s published start time.\n\nWeekend multi-game sites: preceding games must be halted 15 minutes before the next game\'s start time.\n\nTime limits: there are no explicit time limits.',
1, 2700),

('rules', '2.8 Game Reporting',
'The manager of the WINNING team must report the score on the same day as the game, no later than 24 hours after the game concludes. Scores are reported via district8travelleague.com.\n\nPenalty: Any unreported game counts as a 7-0 forfeit loss for BOTH teams for playoff seeding purposes.',
1, 2800),

('rules', '2.9 Game Result Certification',
'At the end of each game, each team\'s scorebook must be certified (signed) by the opposing manager. The certification must clearly show the final score, the winner, and the date(s) played.\n\n2026 Change: The requirement for the Umpire-in-Chief to sign the scorebook for forfeited games has been removed.',
1, 2900),

('rules', '2.10 Lineup Requirements',
'A team\'s lineup must meet all of the following:\n- Minimum players to play: 8 (may include substitutes/borrowed players)\n- Minimum roster players in lineup: 6\n- Maximum substitutes allowed: 3\n- Maximum lineup size with substitutes: 10\n- Borrowing of players is permitted\n- Borrowed players cannot pitch\n- Late-arriving players may be added to the lineup',
1, 2010),

('rules', '2.11.1 Substitute Player Eligibility',
'Any player on any team may serve as a substitute on another team, with the following restrictions:\n- A player may NOT substitute if their regular team has a game on the same day. Exception: players "borrowed" directly from the opposing team are allowed.\n- A team may borrow a player from their opponent, provided doing so does not reduce the lending team below 9 players.\n- Substitute players may NOT pitch.',
1, 2111),

('rules', '2.11.2 Substitute Player Lineup Limits',
'Teams using substitute players must follow these limits:\n- Maximum of 3 substitutes per game\n- At least 6 regular roster players must be in the lineup at all times\n- Substitute players may not bat lower than 6th in the batting order\n- Substitute players may not be slotted with another player if slotted batting order is in use',
1, 2112),

('rules', '2.11.3 Substitute Player Playing Requirements',
'Substitutes in the lineup have the following protections:\n- Minimum Play (Defense): Substitute players must be in the starting lineup and cannot be removed defensively before the completion of the 5th inning, except for illness or injury.\n- Equal Play Clause: No regular player in attendance may play fewer innings than any substitute player.',
1, 2113),

('rules', '2.12 Movement of Players Between Divisions',
'A player from one division may serve as a substitute in another division, provided that player is age-eligible for the division in which they are participating.',
1, 2120),

('rules', '2.13 Pitch Count Certification',
'Each team must maintain a separate paper pitch count log. At the conclusion of each game, the log must be certified (signed) by the opposing manager or acting manager.\n\nDiscrepancies: The home plate umpire has authority to resolve discrepancies, provided the resolution is consistent with league rules. If unresolvable, the Division Director decides.',
1, 2300),

('rules', '2.14 Player and Coach Conduct — Overview and Penalties',
'Players and coaches must conduct themselves in a sportsmanlike manner at all times. Unacceptable conduct may result in a minimum 1-game suspension up to permanent expulsion.\n\nPenalty schedule:\n\nArguing / Foul Language:\n- 1st offense: Ejection + 1-game suspension\n- 2nd offense: Ejection + 3-game suspension\n- 3rd offense: Expulsion\n\nEquipment Throwing (before or after ejection):\n- 1st offense: Ejection + 1-game suspension\n- 2nd offense: Expulsion\n\nFighting or Inciting an Altercation:\n- 1st offense: Ejection + 3-game suspension\n- 2nd offense: Expulsion\n\nTrash Talk ("Jawing"):\n- 1st offense: Warning (no ejection)\n- 2nd offense: Ejection + 1-game suspension\n- 3rd offense: Ejection + 3-game suspension\n- 4th offense: Expulsion',
1, 2140),

('rules', '2.14.1–2.14.2 Ejection Reporting and Conduct Penalties',
'Ejection Reporting: Team managers must report every ejection (including their own) to the Division Director.\n\nConduct Penalties: The Division Director may review all infractions and modify any punishments or suspensions at their discretion. Each infraction is reviewed in consultation with the District 8 umpire crew chief.',
1, 2141),

('rules', '2.14.3 Conduct Unbecoming of a Manager or Coach',
'Managers, coaches, and team officials are held to a higher standard than players. Conduct unbecoming includes but is not limited to: excessive or persistent arguing with umpires; disparaging or sarcastic comments directed at opposing players, coaches, or umpires; deliberately running up the score to embarrass an opponent; failing to control unsportsmanlike behavior in their own dugout; or any behavior that prioritizes personal ego over player well-being.\n\nThe Division Director may issue a formal warning, mandate a cooling-off suspension, or recommend removal from the program based on a single sufficiently egregious incident. If a manager is making the game worse for the kids, that is sufficient grounds for action.',
1, 2143),

('rules', '2.15 Game Protests',
'Protests may only be lodged for alleged misinterpretation of a rule. Judgment calls (ball, strike, fair, foul, out, safe, proximity) CANNOT be protested.\n\nProcedure: The protest must be lodged immediately — before the next pitch. Umpires must sign the scorebook and note the status of the hitter and all base runners. The protest must be submitted in writing (email acceptable) to the Division Director within 24 hours, citing the specific circumstances and the specific LLB rule number(s).',
1, 2150),

-- ═══════════════════════════════════════════════════════════════════════════
-- SECTION 3: HIGHLIGHTED PLAYING RULES
-- ═══════════════════════════════════════════════════════════════════════════

('rules', '3.1 General Playing Rules',
'All Little League playing rules are in full force as prescribed in the Little League Rulebook, unless otherwise noted in the District 8 local rules. The rules in Section 3 cover local variations and rules of particular importance.',
1, 3100),

('rules', '3.2 Game Preliminaries (Pre-Game Plate Meeting)',
'Before each game, a plate meeting occurs between umpires and both managers. Topics covered:\n- Local ground rules\n- Lineup card exchange\n- Slide rule\n- Mercy rule\n- Pitching rules (ineligible pitchers declared)\n- Intentional walk rule\n- Courtesy runners\n- Slotted batting order and substitute runner\n- Proper conduct review\n- Time limit and darkness rules\n- Equipment rules\n- Minimum play rules\n- Declaration of substitute players\n- Balks\n- Jewelry (permitted per LLB regulations)',
1, 3200),

('rules', '3.3 Mercy Rule',
'The mercy rule ends the game early when one team leads by a large margin. Minimum play is NOT required in games shortened by the mercy rule. (LLB Rule 4.10(e))\n\nTier 1 — After 4 complete innings (3½ if home team leads): 15+ run lead → trailing manager concedes\nTier 2 — After 5 complete innings (4½ if home team leads): 10+ run lead → trailing manager concedes\nTier 3 — After 6+ complete innings (5½+ if home team leads): 8+ run lead → trailing manager concedes',
1, 3300),

('rules', '3.4 Intentional Walk Rule',
'The defense may intentionally walk a batter by announcing the intent to the plate umpire before or during the at-bat. The defensive manager must request and be granted "time" before declaring.\n\nRules:\n- Any player may be intentionally walked a maximum of one (1) time per game (the defense may still pitch around that player in later at-bats)\n- The ball is dead upon declaration; no runners may advance unless forced by the batter\'s award\n- The number of balls needed to complete the walk (based on the count at time of declaration) is added to the pitcher\'s pitch count',
1, 3400),

('rules', '3.5 Minimum Play Rule',
'Every player in attendance must play. Minimum play requirements:\n\n12 players or fewer in the lineup: Each player must play a minimum of 2 innings (6 consecutive defensive outs) in the field AND bat at least once.\n\n13 players or more in the lineup: Each player must play 2 innings or 6 defensive outs (not required to be consecutive) in the field AND bat at least once.\n\nNote: Minimum play is NOT required in games shortened by the mercy rule.',
1, 3500),

('rules', '3.6 Batters Box Rule',
'Summary: Batters must keep at least one foot in the batter\'s box at all times during the at-bat.\n\nExceptions (batter may leave the box):\n- On a swing, slap, or check swing\n- When forced out of the box by a pitch\n- When attempting a drag bunt\n- When the catcher does not catch the pitched ball\n- When a play has been attempted\n- When time has been called\n- When the pitcher leaves the dirt area or moves more than 5 feet from the pitcher\'s plate after receiving the ball, or the catcher leaves the catcher\'s box\n- On a 3-ball pitch the batter believes is a ball\n\nPenalty: Umpire warns the batter first. After one warning, each additional violation results in a called strike (any number of strikes may be called on a batter).',
1, 3600),

('rules', '3.7 Defensive Substitution Rule',
'Any defensive player OTHER THAN the pitcher may be substituted freely throughout the game. Defensive substitutions do not affect the batting order. Substitute players cannot be removed defensively before the completion of the 5th inning, except for illness or injury.',
1, 3700),

('rules', '3.8 Batting Order — Which Format to Use',
'Coaches must use one of two batting order formats based on attendance:\n- 10 players or fewer: must use Continuous Batting Order\n- 11 players or more: manager\'s choice — Continuous or Slotted Batting Order\n\nThe format must be declared at lineup submission and cannot be changed after the game starts.',
1, 3800),

('rules', '3.8.1 Continuous Batting Order',
'In a continuous batting order, every player in the lineup bats in a fixed sequence, independent of defensive alignment.\n\nRules:\n- One player per position in the batting order\n- Independent of defensive substitutions\n- Late-arriving players are added to the end of the lineup\n- Removed players (including ejections): their spot in the order is skipped without penalty\n- Courtesy runners are permitted only for the pitcher or catcher with two outs (see Rule 3.9)',
1, 3810),

('rules', '3.8.2.A Slotted Batting Order — Definition and Basic Mechanics',
'A slotted batting order is a system where up to 2 players share one batting slot and rotate at-bats. This allows more players to participate offensively.\n\nBasics:\n- Up to 10 batting slots may be used (coach chooses 9 or 10 at lineup submission — cannot change mid-game)\n- Each slot may hold 1 or 2 players\n- Players in the same slot are interchangeable: either may bat or run for the other when that slot comes up; coach decides who bats\n- Independent of defensive alignment — neither, one, or both players in a slot may be playing defense at any given time\n- No requirement to alternate between the two players in a slot\n- Courtesy runners are permitted only for the pitcher or catcher with two outs (see Rule 3.9)',
1, 3821),

('rules', '3.8.2.B Slotted Batting Order — Slot Management',
'Managing slot changes during the game:\n\nLate-arriving players: Added to a slot currently occupied by only one player.\n\nRemoved players: When a player occupying a slot alone is removed, a player from the next slot containing two players shall be moved to fill the vacant slot.',
1, 3822),

('rules', '3.8.2.C Slotted Batting Order — Four-Consecutive-Game Rule and Penalty',
'Restriction: No individual player may be slotted with another player for four (4) consecutive games.\n\nPenalty for violation: The manager must bat the offending player alone in a slot for the team\'s next three (3) consecutive games.\n\nIf the violation occurs at season\'s end:\n- If the team is in the playoffs: the team is suspended for the entire playoffs\n- If the team is not in the playoffs: the manager is suspended for the first three games of the following year\n- If the manager does not return: the penalty transfers to the host league',
1, 3823),

('rules', '3.9 Courtesy Runner',
'A courtesy runner is permitted only for the pitcher or catcher when they reach base with two outs. Purpose: to speed up play.\n\nTiming: The courtesy runner must enter immediately when the pitcher or catcher reaches base with 2 outs, or immediately after the 2nd out is recorded. The substitution must occur before the first pitch to the next batter.\n\nSelection:\n- Continuous batting order: the courtesy runner must not be scheduled to bat within the next 4 hitters\n- Slotted batting order: if the catcher is slotted with another player, that slotted partner must serve as the courtesy runner; if the catcher is alone in their slot, the courtesy runner must not be scheduled to bat within the next 4 hitters\n\nRecommended practice: use the player who made the last out.',
1, 3900),

('rules', '3.10.1 Offensive Time Outs',
'One (1) offensive time out is permitted per inning. The requesting team may confer with either the batter or a base runner. All time outs must be requested from and granted by an umpire.',
1, 3101),

('rules', '3.10.2 Mound Visits (Defensive Time Out)',
'A mound visit is limited to the coach/manager, pitcher, and catcher. No other players may participate.\n\nLimits:\n- 1 visit per inning — a 2nd visit triggers a mandatory pitching change\n- 2 visits per pitcher per game — a 3rd visit triggers a mandatory pitching change\n- A pitching change does NOT count as a mound visit in that inning\n- The coach/manager may proceed only to the foul line\n- The home plate umpire enforces the time limit\n\n2026 Change: In Intermediate, Junior, and Senior divisions, remaining mound visits do NOT prevent a pitcher who has been removed from the mound from returning as pitcher later in the game.',
1, 3102),

('rules', '3.10.3 Late Inning Mound Visits',
'Late inning rules apply within 30 minutes of curfew, or in the final inning if the umpire declares the game will end before 7 innings due to lighting:\n- Coach/manager may proceed only to the foul line for any visit, including pitching changes\n- Time is limited to 20 seconds',
1, 3103),

('rules', '3.10.4 Injury Time Out',
'A coach or manager may confer with any player after an injury to assess their condition. The umpire must be informed. This conference does NOT count as a time out or mound visit.',
1, 3104),

('rules', '3.11 Delay of Game',
'No manager, coach, or player may use tactics designed to delay the game. First infraction: warning. Subsequent infractions: ejection or other disciplinary action as determined by the Division Director.',
1, 3110),

('rules', '3.12.1 Official Scorer and Scorebook',
'The home team scorer is the official scorer. Both teams are encouraged to keep scorebooks and verify them with each other at the end of each inning.',
1, 3121),

('rules', '3.12.2 Smoking',
'Smoking is prohibited on the bench, field, and in the dugout for all umpires, managers, coaches, and scorers.',
1, 3122),

('rules', '3.12.3 Dugout Access',
'Only the manager, coaches, and players are permitted on the bench or in the dugout. See Rule 3.12.9 for scorekeeper conditions.',
1, 3123),

('rules', '3.12.4 Electronic Devices in the Dugout',
'Electronic devices (phones, tablets) may be used in the dugout only by approved managers and coaches. Devices may NOT be used on the field during the game.\n\nException: A device may be used in conference with game officials solely to reference league or Little League rules.',
1, 3124),

('rules', '3.12.5 Field Location Designation',
'The home team designates which field will be used. If the game is relocated, the originally designated home team retains home team status.',
1, 3125),

('rules', '3.12.6 Casts',
'Casts of any type may NOT be worn during a game.',
1, 3126),

('rules', '3.12.7 Jewelry',
'Jewelry is permitted per Little League Baseball regulations (2026 LLB Rule 1.10, Note). Players wear jewelry at their own risk.',
1, 3127),

('rules', '3.12.8 Base Coaches',
'Two adult base coaches are permitted, provided at least one approved rostered coach remains in the dugout for supervision. Players serving as base coaches must wear a batting helmet.',
1, 3128),

('rules', '3.12.9 Scorekeeper in Dugout',
'An adult scorekeeper may be in the dugout only if ALL THREE of the following conditions are met:\n1. The scorekeeper is an approved volunteer of the Little League, AND\n2. The scorekeeper is 18 years of age or older, AND\n3. Fewer than 3 adult coaches are currently in the dugout',
1, 3129),

-- ═══════════════════════════════════════════════════════════════════════════
-- APPENDIX A: 2026 LITTLE LEAGUE RULEBOOK CHANGES
-- ═══════════════════════════════════════════════════════════════════════════

('rules', 'A.1 2026 Change: Regulation IV(i) — Courtesy Runner and Mandatory Play',
'When using the continuous batting order, a courtesy runner may be used for the pitcher and/or catcher of record before those players have completed their mandatory play requirements. See Rule 3.5 (Minimum Play) and Rule 3.9 (Courtesy Runner).',
1, 9010),

('rules', 'A.2 2026 Change: Rule 1.10 Note 2 — Pine Tar Now Permitted',
'Pine tar and similar adhesive substances are now permitted at all levels of Little League Baseball and Softball. Batters may apply pine tar to the grip area of the bat.',
1, 9020),

('rules', 'A.3 2026 Change: Rule 1.10 A.R.2 — Batter Products',
'Thumb protectors are now explicitly permitted. Choke-knobs and choke-up assists remain prohibited.',
1, 9030),

('rules', 'A.4 2026 Change: Rule 1.11(a)(3) — Pitcher Sleeves',
'The "neoprene" distinction has been removed. All pitcher sleeves are now covered by this rule regardless of material.',
1, 9040),

('rules', 'A.5 2026 Change: Rules 3.04 / 7.14(b) / T-3(d) — Courtesy Runner Placement',
'Clarifies where runners are placed when courtesy running for both the pitcher and catcher simultaneously with 2 outs.',
1, 9050),

('rules', 'A.6 2026 Change: Rule 4.04 Note 2 — Injured Player / Continuous Batting Order',
'Provides guidance for a player who cannot complete a plate appearance or reach base due to injury, illness, or ejection when using the continuous batting order in the regular season.',
1, 9060),

('rules', 'A.7 2026 Change: Rule 4.18 — Forfeited Games Scorebook',
'The requirement for the Umpire-in-Chief to sign the scorebook for forfeited games has been removed.',
1, 9070),

('rules', 'A.8 2026 Change: Rule 6.06(d) — Illegal Bats',
'Reworded for consistency with Tournament Rule 3(b). No substantive change to the rule itself.',
1, 9080),

('rules', 'A.9 2026 Change: Rule 7.15(g) — Double First Base',
'On an uncaught third strike, both the fielder and the runner may each use either part of the double first base.',
1, 9090),

('rules', 'A.10 2026 Change: Rule 8.06(b) — Pitcher Return (Intermediate, Junior, Senior Only)',
'In Intermediate, Junior League, and Senior League divisions, remaining mound visits do NOT prevent a pitcher who has been removed from the mound from returning as pitcher later in the game.',
1, 9100);
