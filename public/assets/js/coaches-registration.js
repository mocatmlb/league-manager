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

    document.addEventListener('DOMContentLoaded', function () {
        initLeagueOtherToggle();
        initLoginCaptchaReveal();
    });
})();
