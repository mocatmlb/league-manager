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

    document.addEventListener('DOMContentLoaded', function () {
        initLeagueOtherToggle();
        initLoginCaptchaReveal();
        initTeamRegisterLeagueToggle();
        initTeamNamePreview();
        initHomeFieldRepeater();
    });
})();
