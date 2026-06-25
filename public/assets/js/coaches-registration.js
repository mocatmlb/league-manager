/**
 * Coach registration/login progressive behaviors.
 * - Registration: reveal "Other" league input.
 * - Login: reveal reCAPTCHA after failed-attempt threshold.
 */
(function () {
    function initLeagueOtherToggle() {
        var leagueSelect = document.getElementById('league');
        var otherContainer = document.getElementById('league-other-container');
        var otherInput = document.getElementById('league_other');

        if (!leagueSelect || !otherContainer || !otherInput) {
            return;
        }

        var sync = function () {
            var showOther = leagueSelect.value === 'other';
            otherContainer.classList.toggle('d-none', !showOther);
            otherInput.required = showOther;
            if (!showOther) {
                otherInput.value = '';
            }
        };

        leagueSelect.addEventListener('change', sync);
        sync();
    }

    function initLoginCaptchaReveal() {
        var captchaContainer = document.getElementById('recaptcha-container');
        if (!captchaContainer) {
            return;
        }

        // AuthService::CAPTCHA_THRESHOLD is 3
        var failedAttempts = parseInt(captchaContainer.dataset.failedAttempts || '0', 10);
        if (failedAttempts >= 3) {
            captchaContainer.classList.remove('d-none');
        }
    }

    function initTeamNamePreview() {
        var leagueSelect = document.getElementById('league_name');
        var otherInput   = document.getElementById('other_league');
        var preview      = document.getElementById('team-name-preview');
        var lastName     = preview ? (preview.dataset.lastName || '') : '';

        if (!leagueSelect || !preview) return;

        var update = function () {
            var val = leagueSelect.value === 'other'
                ? (otherInput ? otherInput.value.trim() : '')
                : leagueSelect.value;
            preview.textContent = (val || '—') + (lastName ? '-' + lastName : '');
        };

        leagueSelect.addEventListener('change', update);
        if (otherInput) otherInput.addEventListener('input', update);
    }

    function initHomeFieldRepeater() {
        var container  = document.getElementById('location-repeater');
        var addBtn     = document.getElementById('add-location-btn');
        var maxEntries = 5;

        if (!container || !addBtn) return;

        var updateButtons = function () {
            var blocks = container.querySelectorAll('.location-block');
            var count  = blocks.length;
            addBtn.disabled = (count >= maxEntries);
            blocks.forEach(function (block, i) {
                var removeBtn = block.querySelector('.remove-location-btn');
                if (removeBtn) removeBtn.style.display = (count === 1) ? 'none' : '';
                block.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                    el.id   = el.id.replace(/_\d+$/, '_' + i);
                });
                block.querySelectorAll('[for]').forEach(function (el) {
                    el.htmlFor = el.htmlFor.replace(/_\d+$/, '_' + i);
                });
            });
        };

        addBtn.addEventListener('click', function () {
            var blocks = container.querySelectorAll('.location-block');
            if (blocks.length >= maxEntries) return;
            var clone = blocks[0].cloneNode(true);
            clone.querySelectorAll('input, textarea').forEach(function (el) { el.value = ''; });
            container.appendChild(clone);
            updateButtons();
        });

        container.addEventListener('click', function (e) {
            if (!e.target.classList.contains('remove-location-btn')) return;
            var blocks = container.querySelectorAll('.location-block');
            if (blocks.length <= 1) return;
            e.target.closest('.location-block').remove();
            updateButtons();
        });

        updateButtons();
    }

    // Also wire team-register page league/other toggle (uses id="league_name" not id="league")
    function initTeamRegisterLeagueToggle() {
        var leagueSelect    = document.getElementById('league_name');
        var otherContainer  = document.getElementById('league-other-container');
        var otherInput      = document.getElementById('other_league');

        if (!leagueSelect || !otherContainer) return;

        var sync = function () {
            var showOther = leagueSelect.value === 'other';
            otherContainer.classList.toggle('d-none', !showOther);
            if (otherInput) {
                otherInput.required = showOther;
                if (!showOther) otherInput.value = '';
            }
        };

        leagueSelect.addEventListener('change', sync);
        sync();
    }

    function formatPhoneNumber(raw) {
        var digits = raw.replace(/\D/g, '').substring(0, 10);
        if (digits.length === 0) return '';
        if (digits.length <= 3) return '(' + digits;
        if (digits.length <= 6) return '(' + digits.substring(0, 3) + ') ' + digits.substring(3);
        return '(' + digits.substring(0, 3) + ') ' + digits.substring(3, 6) + '-' + digits.substring(6);
    }

    function initPhoneFormatting() {
        document.querySelectorAll('input[type="tel"].phone-format').forEach(function (input) {
            input.addEventListener('input', function () {
                var pos = this.selectionStart;
                var before = this.value.length;
                this.value = formatPhoneNumber(this.value);
                var after = this.value.length;
                this.setSelectionRange(pos + (after - before), pos + (after - before));
            });
            // Format any pre-filled value on load
            if (input.value) input.value = formatPhoneNumber(input.value);
        });
    }

    function initMapPreviewButtons() {
        var container = document.getElementById('location-repeater');
        if (!container) return;

        container.addEventListener('click', function (e) {
            var btn = e.target.closest('.preview-map-btn');
            if (!btn) return;
            e.preventDefault();
            var block   = btn.closest('.location-block');
            var nameEl  = block ? block.querySelector('.location-name-input') : null;
            var addrEl  = block ? block.querySelector('.location-address-input') : null;
            var name    = nameEl ? nameEl.value.trim() : '';
            var address = addrEl ? addrEl.value.trim() : '';
            var query   = address ? address : name;
            if (!query) return;
            window.open('https://maps.google.com/?q=' + encodeURIComponent(query), '_blank', 'noopener');
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initLeagueOtherToggle();
        initLoginCaptchaReveal();
        initTeamRegisterLeagueToggle();
        initTeamNamePreview();
        initHomeFieldRepeater();
        initPhoneFormatting();
        initMapPreviewButtons();
    });
})();
