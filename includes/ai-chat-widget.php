<?php
if (!defined('D8TL_APP')) return;
if (!Auth::isLoggedIn()) return;

$db = Database::getInstance();
$aiEnabled = getSetting('ai_enabled', '0') === '1';
$apiKey = getSetting('ai_api_key', '');
if (!$aiEnabled || empty($apiKey)) return;
?>
<!-- Blue Chat Widget -->
<style>
/* ── Floating action button ── */
#blue-fab {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 62px;
    height: 62px;
    border-radius: 50%;
    background: #1a3a6b;
    color: #fff;
    border: 3px solid #ffc107;
    box-shadow: 0 4px 16px rgba(0,0,0,0.35);
    font-size: 28px;
    cursor: pointer;
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s, box-shadow 0.2s;
    padding: 0;
    line-height: 1;
}
#blue-fab:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0,0,0,0.4); }

/* ── Modal positioning ── */
#blue-modal .modal-dialog {
    position: fixed;
    bottom: 96px;
    right: 20px;
    width: 390px;
    max-width: calc(100vw - 40px);
    margin: 0;
}
#blue-modal .modal-content {
    border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.25);
    height: 540px;
    max-height: calc(100vh - 130px);
    display: flex;
    flex-direction: column;
    border: none;
    overflow: hidden;
}

/* ── Header ── */
#blue-modal .modal-header {
    border-radius: 14px 14px 0 0;
    background: linear-gradient(135deg, #1a3a6b 0%, #0d6efd 100%);
    color: #fff;
    padding: 10px 14px;
    flex-shrink: 0;
    align-items: center;
    border-bottom: 3px solid #ffc107;
}
#blue-modal .modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.85;
    align-self: flex-start;
    margin-top: 2px;
}
#blue-header-identity {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}
#blue-ump-avatar {
    width: 46px;
    height: 46px;
    flex-shrink: 0;
    filter: drop-shadow(1px 2px 3px rgba(0,0,0,0.4));
}
#blue-header-text {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
#blue-header-name {
    font-size: 17px;
    font-weight: 700;
    letter-spacing: 0.3px;
    line-height: 1.2;
}
#blue-header-disclaimer {
    font-size: 10.5px;
    color: #ffc107;
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: 0.1px;
}

/* ── Messages ── */
#blue-messages {
    flex: 1;
    overflow-y: auto;
    padding: 14px 16px;
    background: #f0f4f8;
}
#blue-messages .message {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
}
#blue-messages .message.user { align-items: flex-end; }
#blue-messages .message.assistant { align-items: flex-start; }
#blue-messages .bubble {
    max-width: 86%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 13.5px;
    line-height: 1.45;
    white-space: pre-wrap;
}
#blue-messages .message.user .bubble {
    background: #1a3a6b;
    color: #fff;
    border-bottom-right-radius: 4px;
}
#blue-messages .message.assistant .bubble {
    background: #fff;
    color: #212529;
    border: 1px solid #d0d8e4;
    border-bottom-left-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
#blue-messages .typing .bubble {
    background: #e2e8f0;
    color: #6c757d;
    font-style: italic;
}

/* ── Input area ── */
#blue-input-area {
    padding: 10px 14px;
    border-top: 1px solid #d0d8e4;
    background: #fff;
    border-radius: 0 0 14px 14px;
    flex-shrink: 0;
}
#blue-input-area .input-group { gap: 8px; }
#blue-input-area input {
    border-radius: 20px !important;
    border: 1px solid #c8d3e0;
    padding: 8px 16px;
    font-size: 13.5px;
}
#blue-input-area button {
    border-radius: 20px !important;
    padding: 8px 16px;
    flex-shrink: 0;
    background: #1a3a6b;
    border-color: #1a3a6b;
}
#blue-input-area button:hover { background: #0d6efd; border-color: #0d6efd; }
#blue-input-area input:disabled,
#blue-input-area button:disabled { opacity: 0.6; cursor: not-allowed; }
.blue-error {
    color: #dc3545;
    font-size: 12.5px;
    padding: 6px 10px;
    text-align: center;
}
</style>

<?php
// Inline cartoon umpire SVG — no external file dependency
$blue_ump_svg = '<svg id="blue-ump-avatar" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 110" aria-label="Blue the Umpire">'
    // Cap crown
    . '<ellipse cx="50" cy="36" rx="28" ry="16" fill="#1a3a6b"/>'
    // Cap brim
    . '<path d="M 18 46 Q 50 54 82 46 L 82 50 Q 50 60 18 50 Z" fill="#142d55"/>'
    // Cap button
    . '<circle cx="50" cy="23" r="3.5" fill="#ffc107"/>'
    // Cap letter C
    . '<text x="50" y="42" text-anchor="middle" font-family="Arial Black,sans-serif" font-size="13" font-weight="900" fill="#ffc107" letter-spacing="0">C</text>'
    // Face
    . '<ellipse cx="50" cy="67" rx="24" ry="22" fill="#f5c89a"/>'
    // Eyebrows — grumpy angled inward
    . '<path d="M 31 57 Q 38 53 43 57" stroke="#5c3a1e" stroke-width="3" fill="none" stroke-linecap="round"/>'
    . '<path d="M 57 57 Q 62 53 69 57" stroke="#5c3a1e" stroke-width="3" fill="none" stroke-linecap="round"/>'
    // Eyes
    . '<ellipse cx="38" cy="63" rx="3.5" ry="4" fill="#2c1a0e"/>'
    . '<ellipse cx="62" cy="63" rx="3.5" ry="4" fill="#2c1a0e"/>'
    // Eye glints
    . '<circle cx="40" cy="61.5" r="1.2" fill="#fff"/>'
    . '<circle cx="64" cy="61.5" r="1.2" fill="#fff"/>'
    // Nose
    . '<ellipse cx="50" cy="70" rx="3" ry="2" fill="#d4956a"/>'
    // Frown
    . '<path d="M 39 79 Q 50 75 61 79" stroke="#c07050" stroke-width="2.5" fill="none" stroke-linecap="round"/>'
    // Chin dimple
    . '<path d="M 48 85 Q 50 87 52 85" stroke="#d4956a" stroke-width="1.5" fill="none" stroke-linecap="round"/>'
    // Chest protector body
    . '<path d="M 18 93 Q 50 85 82 93 L 80 110 L 20 110 Z" fill="#1a3a6b"/>'
    // Chest protector center stripe
    . '<path d="M 45 87 Q 50 85 55 87 L 54 110 L 46 110 Z" fill="#ffc107" opacity="0.5"/>'
    . '</svg>';
?>

<button id="blue-fab" onclick="toggleBlue()" title="Hey Blue! Ask the ump a question">
    ⚾
</button>

<div class="modal fade" id="blue-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <div id="blue-header-identity">
                    <?= $blue_ump_svg ?>
                    <div id="blue-header-text">
                        <div id="blue-header-name">Hey Blue! ⚾</div>
                        <div id="blue-header-disclaimer">⚠️ Experimental AI · Not an official rules interpreter</div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="blue-messages">
                <div class="message assistant">
                    <div class="bubble">Hey, what do you got?! Ask me about league rules, the schedule, or how to use the website. I'll do my best — but I'm an AI, so always check the rulebook before you argue the call! ⚾</div>
                </div>
            </div>
            <div id="blue-input-area">
                <div class="input-group">
                    <input type="text" id="blue-input" class="form-control" placeholder="Ask Blue something..." onkeydown="if(event.key==='Enter')sendBlue()">
                    <button id="blue-send" class="btn btn-primary" onclick="sendBlue()"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div id="blue-error" class="blue-error" style="display:none"></div>
            </div>
        </div>
    </div>
</div>

<script>
let blueSessionId = localStorage.getItem('blue_session_id') || '';

if (!blueSessionId) {
    blueSessionId = '<?php echo session_id(); ?>-' + Math.random().toString(36).slice(2, 10);
    localStorage.setItem('blue_session_id', blueSessionId);
}

function toggleBlue() {
    const modal = new bootstrap.Modal(document.getElementById('blue-modal'));
    modal.toggle();
    setTimeout(() => {
        document.getElementById('blue-input').focus();
    }, 500);
}

function sendBlue() {
    const input = document.getElementById('blue-input');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    input.disabled = true;
    document.getElementById('blue-send').disabled = true;
    hideError();

    addMessage('user', msg);

    const typingDiv = document.createElement('div');
    typingDiv.className = 'message assistant typing';
    typingDiv.innerHTML = '<div class="bubble"><i class="fas fa-circle-notch fa-spin"></i> Thinking...</div>';
    document.getElementById('blue-messages').appendChild(typingDiv);
    scrollToBottom();

    fetch('<?php echo EnvLoader::getBaseUrl(); ?>/ajax/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg, session_id: blueSessionId })
    })
    .then(r => r.json())
    .then(data => {
        typingDiv.remove();
        if (data.error) {
            showError(data.error);
        } else {
            addMessage('assistant', data.reply);
            if (data.session_id) {
                blueSessionId = data.session_id;
                localStorage.setItem('blue_session_id', data.session_id);
            }
        }
    })
    .catch(err => {
        typingDiv.remove();
        showError('Could not reach Blue. Please try again.');
    })
    .finally(() => {
        input.disabled = false;
        document.getElementById('blue-send').disabled = false;
        input.focus();
    });
}

function addMessage(role, text) {
    const div = document.createElement('div');
    div.className = 'message ' + role;
    div.innerHTML = '<div class="bubble">' + escapeHtml(text) + '</div>';
    document.getElementById('blue-messages').appendChild(div);
    scrollToBottom();
}

function showError(text) {
    const el = document.getElementById('blue-error');
    el.textContent = text;
    el.style.display = 'block';
}

function hideError() {
    document.getElementById('blue-error').style.display = 'none';
}

function scrollToBottom() {
    const container = document.getElementById('blue-messages');
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
