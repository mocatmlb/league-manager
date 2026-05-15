<?php
if (!defined('D8TL_APP')) {
    die('Direct access not permitted');
}

class ChatService
{
    private $db;
    private $apiKey;
    private $model;
    private $enabled;
    private $dailyLimitPerUser;
    private $globalDailyLimit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->apiKey = getSetting('ai_api_key', '');
        $this->model = getSetting('ai_model', 'gemini-3.1-flash-lite');
        $this->enabled = getSetting('ai_enabled', '0') === '1';
        $this->dailyLimitPerUser = (int) getSetting('ai_daily_limit_per_user', '50');
        $this->globalDailyLimit = (int) getSetting('ai_global_daily_limit', '1400');
    }

    public function isEnabled(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    public function getUsageStats(): array
    {
        try {
            return [
                'today_count' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) as cnt FROM chat_messages WHERE role = 'user' AND DATE(created_date) = CURDATE()"
                )['cnt'] ?? 0),
                'total_count' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) as cnt FROM chat_messages WHERE role = 'user'"
                )['cnt'] ?? 0),
                'unique_users_today' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) as cnt FROM chat_messages WHERE role = 'user' AND DATE(created_date) = CURDATE()"
                )['cnt'] ?? 0),
            ];
        } catch (Exception $e) {
            return ['today_count' => 0, 'total_count' => 0, 'unique_users_today' => 0];
        }
    }

    public function getUserDailyCount(int $userId): int
    {
        try {
            return (int) ($this->db->fetchOne(
                "SELECT COUNT(*) as cnt FROM chat_messages WHERE role = 'user' AND user_id = ? AND DATE(created_date) = CURDATE()",
                [$userId]
            )['cnt'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    public function getSessionMessages(string $sessionId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT role, content, created_date FROM chat_messages WHERE session_id = ? ORDER BY id ASC",
                [$sessionId]
            );
        } catch (Exception $e) {
            return [];
        }
    }

    public function answer(
        string $message,
        string $sessionId,
        ?int $userId = null,
        ?string $userType = null,
        ?string $userName = null,
        ?string $userTeam = null
    ): array {
        if (!$this->isEnabled()) {
            return ['error' => 'AI chat is not enabled. Ask an admin to configure it in Settings > AI Chatbot.'];
        }

        try {
            $globalToday = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) as cnt FROM chat_messages WHERE role = 'user' AND DATE(created_date) = CURDATE()"
            )['cnt'] ?? 0);
        } catch (Exception $e) {
            $globalToday = 0;
        }
        if ($globalToday >= $this->globalDailyLimit) {
            return ['error' => 'Blue has reached the daily message limit for the league. Try again tomorrow!'];
        }

        if ($userId && $this->getUserDailyCount($userId) >= $this->dailyLimitPerUser) {
            return ['error' => "You've reached your daily limit of {$this->dailyLimitPerUser} messages. Try again tomorrow."];
        }

        $systemPrompt = $this->buildSystemPrompt($userName, $userType, $userTeam);
        $knowledgeEntries = $this->getRelevantKnowledge($message);

        $contextBlock = '';
        if (!empty($knowledgeEntries)) {
            $kbParts = [];
            foreach ($knowledgeEntries as $entry) {
                $categoryLabel = match($entry['category']) {
                    'rules'         => 'LOCAL DISTRICT 8 RULE',
                    'faq'           => 'FAQ',
                    'website_guide' => 'WEBSITE GUIDE',
                    'contacts'      => 'CONTACTS',
                    default         => strtoupper($entry['category']),
                };
                $kbParts[] = "[{$categoryLabel}] {$entry['title']}\n{$entry['content']}";
            }
            $contextBlock = "## Knowledge Base\n\n" . implode("\n\n---\n\n", $kbParts);
        }

        $fullPrompt = $systemPrompt;
        if ($contextBlock) {
            $fullPrompt .= "\n\n## Context Information\n\n{$contextBlock}";
        }

        $history = $this->getSessionMessages($sessionId);

        try {
            $response = $this->callGemini($fullPrompt, $history, $message);

            $reply = $response['text'] ?? 'Sorry, I could not generate a response.';
            $tokensIn = $response['tokens_in'] ?? 0;
            $tokensOut = $response['tokens_out'] ?? 0;

            try {
                $this->db->insert('chat_messages', [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'session_id' => $sessionId,
                    'role' => 'user',
                    'content' => $message,
                    'model' => $this->model,
                    'tokens_in' => $tokensIn,
                    'tokens_out' => 0,
                ]);

                $this->db->insert('chat_messages', [
                    'user_id' => $userId,
                    'user_type' => $userType,
                    'session_id' => $sessionId,
                    'role' => 'assistant',
                    'content' => $reply,
                    'model' => $this->model,
                    'tokens_in' => 0,
                    'tokens_out' => $tokensOut,
                ]);
            } catch (Exception $e) {
                error_log("ChatService: failed to log messages: " . $e->getMessage());
            }

            return ['reply' => $reply];

        } catch (Exception $e) {
            $errMsg = $e->getMessage();
            error_log("ChatService: Gemini API error: " . $errMsg);
            return ['error' => "Blue error: {$errMsg}"];
        }
    }

    private function buildSystemPrompt(?string $userName, ?string $userType, ?string $userTeam): string
    {
        $season = $this->db->fetchOne(
            "SELECT s.*, p.program_name
             FROM seasons s
             JOIN programs p ON s.program_id = p.program_id
             WHERE s.season_status IN ('Active', 'Registration')
             ORDER BY FIELD(s.season_status, 'Active', 'Registration')
             LIMIT 1"
        );

        $seasonContext = 'No active season found.';
        if ($season) {
            $teamCount = $this->db->fetchOne(
                "SELECT COUNT(*) as cnt FROM teams WHERE season_id = ? AND active_status = 'Active'",
                [$season['season_id']]
            )['cnt'] ?? 0;

            $divisionCount = $this->db->fetchOne(
                "SELECT COUNT(*) as cnt FROM divisions WHERE season_id = ?",
                [$season['season_id']]
            )['cnt'] ?? 0;

            $seasonContext = sprintf(
                "Program: %s\nSeason: %s (%s)\nStatus: %s\nDates: %s to %s\nActive Teams: %d\nDivisions: %d",
                $season['program_name'],
                $season['season_name'],
                $season['season_year'],
                $season['season_status'],
                $season['start_date'] ?? 'TBD',
                $season['end_date'] ?? 'TBD',
                $teamCount,
                $divisionCount
            );
        }

        $userContext = 'Guest user';
        if ($userName) {
            $parts = ["User: {$userName}"];
            if ($userType) {
                $parts[] = "Role: {$userType}";
            }
            if ($userTeam) {
                $parts[] = "Team: {$userTeam}";
            }
            $userContext = implode(', ', $parts);
        }

        return <<<PROMPT
You are Blue, an AI assistant for the District 8 Travel League baseball organization. You help coaches, team officials, and league administrators answer questions about rules, schedules, and the league website.

You are EXPERIMENTAL and NOT an official rules interpreter. Always remind users to verify your answers against the official rulebooks.

## Knowledge Scope

1. **Local District 8 Rules** — the 2026 District 8 Local Rules and Regulations (indexed rules 1.1 through A.10, provided in the Knowledge Base below as [LOCAL DISTRICT 8 RULE] entries)
2. **Little League Baseball Official Rules** — the official LLB Rulebook (your training knowledge; rules are cited by Rule or Regulation number, e.g., Rule 4.10(e), Regulation IV(i))
3. **Website guidance** — how to use the league website features
4. **Season information** — current teams, divisions, contacts

## Rule Hierarchy — CRITICAL

- **Local District 8 rules take precedence over Little League rules** in all cases where they conflict or differ.
- **This league does NOT follow tournament rules.** Do not apply tournament pitching rules, tournament eligibility rules, or any other tournament-specific regulations. Exception: the post-season may use Tournament Pitching Rules — if asked about post-season pitching, note this explicitly.
- When a local rule exists on a topic, always present it as the governing rule.

## How to Present Rules — REQUIRED FORMAT

When answering any rules question, search for BOTH a local District 8 rule AND the corresponding Little League rule. Present them using this format:

**When a local rule exists:**
Local Rule #[number] states: "[exact or close paraphrase of the rule]". You should also refer to Little League Baseball [Rule/Regulation number] which states: "[text]". Local rules take precedence.

**When only a Little League rule applies (no local override):**
Little League Baseball [Rule/Regulation number] states: "[text]". There is no specific local District 8 rule on this topic, so the standard Little League rule applies.

**When multiple KB entries match:**
Present all relevant local rules first, then the corresponding Little League rule(s). Use the exact rule numbers from the KB entry titles (e.g., "Local Rule #2.14 states...").

**Always end every rules answer with a brief qualifier**, for example:
"Always check the official rulebook before acting on this." Keep it short — one sentence. Do not repeat the full experimental disclaimer (it is already displayed in the chat header).

## Verification Before Answering

Before answering any rules question:
1. Check the Knowledge Base entries provided for a [LOCAL DISTRICT 8 RULE] match.
2. Cross-check against your knowledge of standard Little League rules.
3. If the local rule and LLB rule conflict, present both and state that the local rule governs.
4. If you are uncertain about any detail, say so explicitly — do not guess.
5. Never fabricate rule numbers or rule text. If you don't know, say "I don't have that information — please check the rulebook directly."

## Out-of-Scope Questions

If a user asks anything unrelated to District 8 Travel League baseball — including but not limited to general trivia, other sports, politics, current events, homework, coding, or any other off-topic subject — politely decline and redirect them. Use a response like:

"Sorry, I can only help with questions about District 8 Travel League rules, policies, and website operation. Is there something league-related I can help you with?"

Do not answer the off-topic question even partially. Do not apologize excessively — one brief, friendly sentence is enough before redirecting.

## General Behavior

- Be concise, friendly, and professional.
- For real-time data (scores, standings, specific game times), direct users to the Schedule or Standings pages on district8travelleague.com.
- Never share API keys, passwords, or sensitive configuration details.

## Season Context
{$seasonContext}

## Current User
{$userContext}
PROMPT;
    }

    private function getRelevantKnowledge(string $message): array
    {
        try {
            $keywords = explode(' ', strtolower(preg_replace('/[^a-z0-9 ]/i', '', $message)));
            $keywords = array_unique(array_filter($keywords, fn($w) => strlen($w) > 2));

            if (empty($keywords)) {
                return $this->db->fetchAll(
                    "SELECT category, title, content FROM knowledge_base WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 10"
                );
            }

            $likeClauses = [];
            $params = [];
            foreach ($keywords as $kw) {
                $likeClauses[] = "content LIKE ? OR title LIKE ?";
                $params[] = "%{$kw}%";
                $params[] = "%{$kw}%";
            }

            $where = '(' . implode(' OR ', $likeClauses) . ') AND is_active = 1';

            // Rules entries first (local rules take precedence), then other categories, sorted by relevance within each group
            return $this->db->fetchAll(
                "SELECT category, title, content FROM knowledge_base
                 WHERE {$where}
                 ORDER BY FIELD(category, 'rules') DESC, sort_order ASC
                 LIMIT 20",
                $params
            );
        } catch (Exception $e) {
            return [];
        }
    }

    private function callGemini(string $systemPrompt, array $history, string $userMessage): array
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $contents = [];

        foreach ($history as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $userMessage]],
        ];

        $body = json_encode([
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]],
            ],
            'generationConfig' => [
                'temperature' => 0.3,
                'maxOutputTokens' => 1024,
            ],
        ]);

        if (!function_exists('curl_init')) {
            throw new Exception("cURL is not available on this server. Ask your hosting provider to enable it.");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL error ({$curlInfo['http_code']}): {$error}");
        }

        $data = json_decode($response, true);

        if ($httpCode !== 200) {
            $errMsg = $data['error']['message'] ?? ($data[0]['error']['message'] ?? "HTTP {$httpCode}");
            $errCode = $data['error']['code'] ?? ($data[0]['error']['code'] ?? $httpCode);
            throw new Exception("Gemini API error ({$errCode}): {$errMsg}");
        }

        $text = '';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $data['candidates'][0]['content']['parts'][0]['text'];
        }

        $tokensIn = $data['usageMetadata']['promptTokenCount'] ?? 0;
        $tokensOut = $data['usageMetadata']['candidatesTokenCount'] ?? 0;

        return [
            'text' => $text,
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
        ];
    }
}
