<?php
if (!defined('D8TL_APP')) return;
if (!Auth::isLoggedIn()) return;

$db = Database::getInstance();
$aiEnabled = getSetting('ai_enabled', '0') === '1';
$apiKey = getSetting('ai_api_key', '');
if (!$aiEnabled || empty($apiKey)) return;
?>
<!-- Skipper Chat Widget -->
<style>
#skipper-fab {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #0d6efd;
    color: #fff;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    font-size: 24px;
    cursor: pointer;
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.2s;
}
#skipper-fab:hover { transform: scale(1.1); }
#skipper-fab .badge {
    position: absolute;
    top: -4px;
    right: -4px;
    font-size: 10px;
}
#skipper-modal .modal-dialog {
    position: fixed;
    bottom: 86px;
    right: 20px;
    width: 380px;
    max-width: calc(100vw - 40px);
    margin: 0;
}
#skipper-modal .modal-content {
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    height: 520px;
    max-height: calc(100vh - 120px);
    display: flex;
    flex-direction: column;
}
#skipper-modal .modal-header {
    border-radius: 12px 12px 0 0;
    background: #0d6efd;
    color: #fff;
    padding: 12px 16px;
    flex-shrink: 0;
}
#skipper-modal .modal-header .btn-close { filter: brightness(0) invert(1); }
#skipper-messages {
    flex: 1;
    overflow-y: auto;
    padding: 16px;
    background: #f8f9fa;
}
#skipper-messages .message {
    margin-bottom: 12px;
    display: flex;
    flex-direction: column;
}
#skipper-messages .message.user { align-items: flex-end; }
#skipper-messages .message.assistant { align-items: flex-start; }
#skipper-messages .bubble {
    max-width: 85%;
    padding: 10px 14px;
    border-radius: 16px;
    font-size: 14px;
    line-height: 1.4;
    white-space: pre-wrap;
}
#skipper-messages .message.user .bubble {
    background: #0d6efd;
    color: #fff;
    border-bottom-right-radius: 4px;
}
#skipper-messages .message.assistant .bubble {
    background: #fff;
    color: #212529;
    border: 1px solid #dee2e6;
    border-bottom-left-radius: 4px;
}
#skipper-messages .typing .bubble {
    background: #e9ecef;
    color: #6c757d;
    font-style: italic;
}
#skipper-input-area {
    padding: 12px 16px;
    border-top: 1px solid #dee2e6;
    background: #fff;
    border-radius: 0 0 12px 12px;
    flex-shrink: 0;
}
#skipper-input-area .input-group {
    gap: 8px;
}
#skipper-input-area input {
    border-radius: 20px !important;
    border: 1px solid #dee2e6;
    padding: 8px 16px;
}
#skipper-input-area button {
    border-radius: 20px !important;
    padding: 8px 16px;
    flex-shrink: 0;
}
#skipper-input-area input:disabled,
#skipper-input-area button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.skipper-error {
    color: #dc3545;
    font-size: 13px;
    padding: 8px 12px;
    text-align: center;
}
</style>

<button id="skipper-fab" onclick="toggleSkipper()" title="Ask Skipper">
    <i class="fas fa-ship"></i>
</button>

<div class="modal fade" id="skipper-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-end">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ship"></i> Skipper</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div id="skipper-messages">
                <div class="message assistant">
                    <div class="bubble">Ahoy! I'm Skipper, your District 8 Travel League assistant. Ask me about rules, schedules, teams, or how to use the site!</div>
                </div>
            </div>
            <div id="skipper-input-area">
                <div class="input-group">
                    <input type="text" id="skipper-input" class="form-control" placeholder="Ask Skipper something..." onkeydown="if(event.key==='Enter')sendSkipper()">
                    <button id="skipper-send" class="btn btn-primary" onclick="sendSkipper()"><i class="fas fa-paper-plane"></i></button>
                </div>
                <div id="skipper-error" class="skipper-error" style="display:none"></div>
            </div>
        </div>
    </div>
</div>

<script>
let skipperSessionId = localStorage.getItem('skipper_session_id') || '';

if (!skipperSessionId) {
    skipperSessionId = '<?php echo session_id(); ?>-' + Math.random().toString(36).slice(2, 10);
    localStorage.setItem('skipper_session_id', skipperSessionId);
}

function toggleSkipper() {
    const modal = new bootstrap.Modal(document.getElementById('skipper-modal'));
    modal.toggle();
    setTimeout(() => {
        document.getElementById('skipper-input').focus();
    }, 500);
}

function sendSkipper() {
    const input = document.getElementById('skipper-input');
    const msg = input.value.trim();
    if (!msg) return;

    input.value = '';
    input.disabled = true;
    document.getElementById('skipper-send').disabled = true;
    hideError();

    addMessage('user', msg);

    const typingDiv = document.createElement('div');
    typingDiv.className = 'message assistant typing';
    typingDiv.innerHTML = '<div class="bubble"><i class="fas fa-circle-notch fa-spin"></i> Thinking...</div>';
    document.getElementById('skipper-messages').appendChild(typingDiv);
    scrollToBottom();

    fetch('<?php echo EnvLoader::getBaseUrl(); ?>/ajax/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg, session_id: skipperSessionId })
    })
    .then(r => r.json())
    .then(data => {
        typingDiv.remove();
        if (data.error) {
            showError(data.error);
        } else {
            addMessage('assistant', data.reply);
            if (data.session_id) {
                skipperSessionId = data.session_id;
                localStorage.setItem('skipper_session_id', data.session_id);
            }
        }
    })
    .catch(err => {
        typingDiv.remove();
        showError('Could not reach Skipper. Please try again.');
    })
    .finally(() => {
        input.disabled = false;
        document.getElementById('skipper-send').disabled = false;
        input.focus();
    });
}

function addMessage(role, text) {
    const div = document.createElement('div');
    div.className = 'message ' + role;
    div.innerHTML = '<div class="bubble">' + escapeHtml(text) + '</div>';
    document.getElementById('skipper-messages').appendChild(div);
    scrollToBottom();
}

function showError(text) {
    const el = document.getElementById('skipper-error');
    el.textContent = text;
    el.style.display = 'block';
}

function hideError() {
    document.getElementById('skipper-error').style.display = 'none';
}

function scrollToBottom() {
    const container = document.getElementById('skipper-messages');
    container.scrollTop = container.scrollHeight;
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
</script>
