-- Seed initial Blue knowledge base entries
-- Run this after migration 020 to give the chatbot a starting knowledge base

INSERT INTO knowledge_base (category, title, content, is_active, sort_order) VALUES

('website_guide', 'How to view your team schedule',
'To view your team schedule: 1) Log into your coach account. 2) Click "My Schedule" in the Coach Tools dropdown. 3) You will see all your upcoming games with dates, times, and locations. 4) You can filter by date range using the calendar picker.',
1, 10),

('website_guide', 'How to input game scores',
'To input scores: 1) Log into your coach account. 2) Click "Score Input" in the Coach Tools dropdown. 3) Find the game you want to score. 4) Enter the runs for both home and away teams. 5) Click Submit. Scores cannot be changed after submission without admin assistance.',
1, 20),

('website_guide', 'How to request a schedule change',
'To request a schedule change: 1) Log into your coach account. 2) Click "Schedule Changes" in the Coach Tools dropdown. 3) Select the game you want to change. 4) Choose what you want to change (date, time, or location). 5) Enter your requested new date/time/location. 6) Submit for admin review. The request will be approved or denied by a league administrator.',
1, 30),

('website_guide', 'How to view league contacts',
'To view league contacts: 1) Log into your coach account. 2) Click "Contacts" in the Coach Tools dropdown. 3) You will see league officials, board members, and other coaches. Contact information includes names, roles, and email addresses.',
1, 40),

('website_guide', 'How to view league rules',
'To view league rules: 1) Log into your coach account. 2) Click "Rules" in the Coach Tools dropdown. 3) You will find Little League official rules and any local District 8 rules and policies.',
1, 50),

('website_guide', 'How to update your profile',
'To update your profile: 1) Log into your account. 2) Click your username in the top-right corner. 3) Select "Profile" from the dropdown. 4) You can update your name, phone number, and password. 5) Click Save to apply changes.',
1, 60),

('faq', 'What is the District 8 Travel League?',
'The District 8 Travel League is a youth baseball and softball organization serving multiple communities. We organize seasonal travel baseball and softball programs for youth players, with divisions based on age and skill level.',
1, 100),

('faq', 'How do I register my team?',
'Team registration is handled through the website. Log into your admin account and navigate to the Teams section to register a new team. You will need to provide team name, league name, manager contact information, and division preferences. Teams must be approved by an administrator before appearing on the schedule.',
1, 110),

('faq', 'When is the season?',
'Season dates vary by program. Check the current season information on the website homepage or in your coach dashboard. Each season has a defined start and end date set by the league administration.',
1, 120),

('rules', 'Little League Official Rules',
'The official Little League rules apply to all District 8 Travel League games. Key points: games are 6 innings (or time limit), all players bat in the lineup (continuous batting order), and each player must play at least 2 defensive innings per game. For complete rules, refer to the official Little League rulebook.',
1, 200),

('rules', 'District 8 Local Rules - Pitch Count',
'District 8 follows Little League pitch count regulations: Ages 7-8: 50 pitches max per day. Ages 9-10: 75 pitches max per day. Ages 11-12: 85 pitches max per day. Ages 13-14: 95 pitches max per day. Required rest: 1-20 pitches = 0 days rest, 21-35 = 1 day, 36-50 = 2 days, 51-65 = 3 days, 66+ = 4 days.',
1, 210),

('rules', 'District 8 Local Rules - Weather Policy',
'If lightning is visible or thunder is heard, all games must be suspended immediately. Players and spectators must seek shelter. Games may resume 30 minutes after the last lightning strike or thunder clap. Game cancellations due to weather will be posted on the website and communicated via email.',
1, 220),

('contacts', 'League Officials - How to find them',
'League officials and contacts can be found in the Contacts section of the coach portal. This includes board members, division coordinators, and league administrators. For urgent matters, contact the league commissioner through the email listed on the contacts page.',
1, 300);
