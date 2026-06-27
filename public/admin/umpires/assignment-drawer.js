(function () {
    'use strict';

    var drawer = document.getElementById('assignmentDrawer');
    if (!drawer || typeof bootstrap === 'undefined') {
        return;
    }

    var body = document.getElementById('assignmentDrawerBody');
    var title = document.getElementById('assignmentDrawerTitle');
    var csrfToken = drawer.getAttribute('data-csrf-token') || '';
    var pageMode = drawer.getAttribute('data-page-mode') || '';
    var canOverride = drawer.getAttribute('data-can-override') === '1';
    var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawer);
    var activeGameId = 0;
    var drawerState = null;
    var activePickerSlotIndex = null;
    var pickerFilter = 'all';
    var pickerSearch = '';
    var pickerMode = 'pool';
    var returnFocusSlotIndex = null;
    var pendingPublishFeedback = null;

    function setStaticState(message, type) {
        body.textContent = '';
        var alert = document.createElement('div');
        alert.className = type === 'error' ? 'alert alert-danger' : 'text-muted py-3';
        alert.textContent = message;
        body.appendChild(alert);
    }

    function appendText(parent, tagName, className, text) {
        var node = document.createElement(tagName);
        if (className) {
            node.className = className;
        }
        node.textContent = text == null || text === '' ? '-' : String(text);
        parent.appendChild(node);
        return node;
    }

    function fullName(row) {
        return [row && row.first_name, row && row.last_name].filter(Boolean).join(' ') || 'Unnamed umpire';
    }

    function badge(parent, text, className) {
        var span = document.createElement('span');
        span.className = 'badge ' + className;
        span.textContent = text;
        parent.appendChild(span);
        return span;
    }

    function requestJson(url, options) {
        return fetch(url, options).then(function (response) {
            return response.json().catch(function () {
                return { success: false, error: 'Invalid JSON response.' };
            }).then(function (payload) {
                if (!response.ok || !payload.success) {
                    var error = new Error(payload.error || 'Request failed.');
                    error.payload = payload;
                    throw error;
                }
                return payload.data;
            });
        });
    }

    function loadDrawer(gameId) {
        activeGameId = Number(gameId || 0);
        if (!activeGameId) {
            return;
        }
        title.textContent = 'Assignment Drawer';
        setStaticState('Loading assignment drawer...', 'loading');
        offcanvas.show();

        requestJson('ajax/get-drawer.php?game_id=' + encodeURIComponent(activeGameId), {
            credentials: 'same-origin'
        }).then(renderDrawer).catch(function (error) {
            setStaticState(error.message, 'error');
        });
    }

    function conflictLabel(conflict) {
        if (!conflict) {
            return '';
        }
        var teams = [conflict.away_team, conflict.home_team].filter(Boolean).join(' at ');
        return [
            conflict.game_date,
            conflict.game_time,
            conflict.game_number ? 'Game ' + conflict.game_number : null,
            teams,
            conflict.location_name
        ].filter(Boolean).join(' | ');
    }

    function renderOverrideError(container, error, retryCallback) {
        var payload = error.payload || {};
        container.textContent = '';
        container.className = 'small mb-2';

        var alert = document.createElement('div');
        alert.className = 'alert alert-danger py-2 mb-2';
        appendText(alert, 'div', 'fw-semibold', error.message);
        if (payload.conflict) {
            appendText(alert, 'div', 'mt-1', conflictLabel(payload.conflict));
        }
        container.appendChild(alert);

        if (!payload.requires_override || !canOverride) {
            return;
        }

        var group = document.createElement('div');
        group.className = 'border rounded p-2 bg-light';

        var label = document.createElement('label');
        label.className = 'form-label small fw-semibold mb-1';
        label.textContent = 'Override reason';
        group.appendChild(label);

        var textarea = document.createElement('textarea');
        textarea.className = 'form-control form-control-sm mb-2';
        textarea.rows = 2;
        textarea.maxLength = 500;
        group.appendChild(textarea);

        var validation = document.createElement('div');
        validation.className = 'text-danger mb-2 d-none';
        validation.textContent = 'Enter an override reason before confirming.';
        group.appendChild(validation);

        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'btn btn-danger btn-sm';
        confirm.textContent = 'Confirm Override';
        confirm.addEventListener('click', function () {
            var reason = textarea.value.trim();
            if (!reason) {
                validation.classList.remove('d-none');
                textarea.focus();
                return;
            }
            validation.classList.add('d-none');
            confirm.disabled = true;
            confirm.textContent = 'Saving...';
            retryCallback(reason).catch(function (retryError) {
                renderOverrideError(container, retryError, retryCallback);
            });
        });
        group.appendChild(confirm);
        container.appendChild(group);
    }

    function postSlot(url, payload) {
        var form = new FormData();
        Object.keys(payload).forEach(function (key) {
            form.append(key, payload[key]);
        });
        form.append('csrf_token', csrfToken);

        return requestJson(url, {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        }).then(function () {
            return requestJson('ajax/get-drawer.php?game_id=' + encodeURIComponent(activeGameId), {
                credentials: 'same-origin'
            });
        }).then(function (data) {
            renderDrawer(data);
            updatePageRow(data);
        });
    }

    function postPublish(confirmPartial) {
        var form = new FormData();
        form.append('game_id', activeGameId);
        form.append('csrf_token', csrfToken);
        if (confirmPartial) {
            form.append('confirm_partial', '1');
        }

        return requestJson('ajax/publish.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        }).then(function (data) {
            pendingPublishFeedback = data;
            return requestJson('ajax/get-drawer.php?game_id=' + encodeURIComponent(activeGameId), {
                credentials: 'same-origin'
            });
        }).then(function (data) {
            renderDrawer(data);
            updatePageRow(data);
        });
    }

    function renderDrawer(data) {
        drawerState = data || {};
        activePickerSlotIndex = null;
        pickerFilter = 'all';
        pickerSearch = '';
        pickerMode = 'pool';
        renderCurrentDrawer();
    }

    function renderCurrentDrawer() {
        var data = drawerState || {};
        var game = data.game || {};
        title.textContent = 'Game ' + (game.game_number || game.game_id || activeGameId);
        body.textContent = '';

        if (data.migration_mode) {
            var migration = document.createElement('div');
            migration.className = 'alert alert-warning py-2';
            migration.textContent = 'Migration mode is active. Saved slots remain draft and no notifications are sent.';
            body.appendChild(migration);
        }

        body.appendChild(renderGameSummary(game));
        body.appendChild(renderAdvisoryWarnings(data.warnings || []));

        if (activePickerSlotIndex === 0 || activePickerSlotIndex === 1) {
            body.appendChild(renderPickerView(activePickerSlotIndex));
            focusPickerSearch();
            return;
        }

        body.appendChild(renderSlotOverview());

        var publishPanel = renderPublishPanel(data.slots || {});
        if (publishPanel) {
            body.appendChild(publishPanel);
        }

        if (returnFocusSlotIndex === 0 || returnFocusSlotIndex === 1) {
            focusSlotAction(returnFocusSlotIndex);
            returnFocusSlotIndex = null;
        }
    }

    function renderAdvisoryWarnings(warnings) {
        var section = document.createElement('section');
        section.className = 'mb-3';
        section.setAttribute('data-advisory-warnings', '');

        if (!warnings || warnings.length === 0) {
            section.classList.add('d-none');
            return section;
        }

        warnings.forEach(function (warning) {
            var alert = document.createElement('div');
            alert.className = 'alert alert-warning py-2 mb-2';
            appendText(alert, 'div', 'fw-semibold', warning.message || 'Assignment quality warning');
            if (warning.conflict) {
                appendText(alert, 'div', 'small mt-1', [
                    warning.conflict.game_date,
                    warning.conflict.game_time,
                    warning.conflict.location_name
                ].filter(Boolean).join(' | '));
            }
            section.appendChild(alert);
        });

        return section;
    }

    function renderGameSummary(game) {
        var summary = document.createElement('section');
        summary.className = 'mb-3';
        appendText(summary, 'h6', 'text-uppercase text-muted small mb-2', 'Game Details');
        appendText(summary, 'div', 'fw-semibold', (game.away_team || '-') + ' at ' + (game.home_team || '-'));
        appendText(summary, 'div', 'small text-muted', [game.game_date, game.game_time, game.location_name, game.division_name].filter(Boolean).join(' | '));
        if (game.has_pending_scr) {
            var tentative = document.createElement('div');
            tentative.className = 'mt-2';
            badge(tentative, 'Tentative', 'bg-warning text-dark');
            appendText(tentative, 'span', 'small text-muted ms-2', 'Schedule change pending — game details may change.');
            summary.appendChild(tentative);
        }
        return summary;
    }

    function renderSlotOverview() {
        var data = drawerState || {};
        var slots = data.slots || {};
        var labels = data.slot_labels || {};
        var overview = document.createElement('section');
        overview.className = 'mb-3';
        [0, 1].forEach(function (slotIndex) {
            overview.appendChild(renderSlotPanel(slotIndex, labels[slotIndex] || ('Umpire ' + (slotIndex + 1)), slots[slotIndex] || {}));
        });
        return overview;
    }

    function hasFilledDraftSlot(slots) {
        return [slots[0], slots[1]].some(function (slot) {
            return slot && slot.status === 'Draft' && slot.umpire_user_id;
        });
    }

    function hasFilledPublishedSlot(slots) {
        return [slots[0], slots[1]].some(function (slot) {
            return slot && slot.status === 'Published' && slot.umpire_user_id;
        });
    }

    function hasPublishableFilledSlot(slots) {
        return hasFilledDraftSlot(slots) || hasFilledPublishedSlot(slots);
    }

    function renderPublishSuccessMessage(statusNode, publishData) {
        var notified = Number((publishData && publishData.notified) || 0);
        var suppressed = Number((publishData && publishData.suppressed) || 0);
        var published = Number((publishData && publishData.published) || 0);

        if (notified === 0 && suppressed > 0) {
            statusNode.className = 'small text-muted mb-2';
            statusNode.textContent = 'No changes to notify — all slots up to date.';
            return;
        }

        var parts = [];
        if (published > 0) {
            parts.push(published + ' slot' + (published === 1 ? '' : 's') + ' published');
        }
        if (notified > 0) {
            parts.push(notified + ' assignment email' + (notified === 1 ? '' : 's') + ' queued');
        }
        if (suppressed > 0) {
            parts.push(suppressed + ' unchanged slot' + (suppressed === 1 ? '' : 's') + ' skipped');
        }

        statusNode.className = 'small text-success mb-2';
        statusNode.textContent = parts.length ? parts.join('. ') + '.' : 'Publish complete.';
    }

    function renderPublishPanel(slots) {
        if (!hasPublishableFilledSlot(slots || {})) {
            return null;
        }

        var isRepublish = !hasFilledDraftSlot(slots || {}) && hasFilledPublishedSlot(slots || {});
        var panel = document.createElement('section');
        panel.className = 'border rounded p-3 mb-3 bg-light';

        appendText(panel, 'h6', 'mb-2', isRepublish ? 'Re-Publish Assignments' : 'Publish Assignments');
        appendText(
            panel,
            'p',
            'small text-muted mb-2',
            isRepublish
                ? 'Re-send assignment email only for changed slots or schedule updates.'
                : 'Send assignment email for filled draft slots and mark them Published.'
        );

        var status = document.createElement('div');
        status.className = 'small mb-2';
        panel.appendChild(status);
        if (pendingPublishFeedback) {
            renderPublishSuccessMessage(status, pendingPublishFeedback);
            pendingPublishFeedback = null;
        }

        var publish = document.createElement('button');
        publish.type = 'button';
        publish.className = 'btn btn-success btn-sm';
        publish.textContent = isRepublish ? 'Re-Publish' : 'Publish';

        function resetPublishButton() {
            publish.disabled = false;
            publish.textContent = isRepublish ? 'Re-Publish' : 'Publish';
        }

        function attempt(confirmPartial) {
            status.className = 'small text-muted mb-2';
            status.textContent = confirmPartial
                ? (isRepublish ? 'Re-publishing partial crew...' : 'Publishing partial crew...')
                : (isRepublish ? 'Re-publishing...' : 'Publishing...');
            publish.disabled = true;
            publish.textContent = isRepublish ? 'Re-Publishing...' : 'Publishing...';
            return postPublish(confirmPartial).then(function () {
                resetPublishButton();
            }).catch(function (error) {
                var payload = error.payload || {};
                pendingPublishFeedback = null;
                resetPublishButton();
                if (payload.requires_confirmation) {
                    renderPartialPublishWarning(status, payload, function () {
                        return attempt(true);
                    });
                    return;
                }
                status.className = 'small text-danger mb-2';
                status.textContent = error.message;
            });
        }

        publish.addEventListener('click', function () {
            attempt(false);
        });
        panel.appendChild(publish);

        return panel;
    }

    function renderPartialPublishWarning(container, payload, confirmCallback) {
        container.textContent = '';
        container.className = 'mb-2';

        var alert = document.createElement('div');
        alert.className = 'alert alert-warning py-2 mb-2';
        appendText(alert, 'div', 'fw-semibold', payload.error || 'This game has fewer filled slots than expected.');
        var warning = payload.warning || {};
        appendText(alert, 'div', 'small', 'Filled slots: ' + Number(warning.filled_slots || 0) + ' of ' + Number(warning.expected_crew_size || 2));
        container.appendChild(alert);

        var confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'btn btn-warning btn-sm';
        confirm.textContent = 'Confirm Partial Publish';
        confirm.addEventListener('click', function () {
            confirm.disabled = true;
            confirm.textContent = 'Publishing...';
            confirmCallback().catch(function (retryError) {
                container.className = 'small text-danger mb-2';
                container.textContent = retryError.message;
            });
        });
        container.appendChild(confirm);
    }

    function otherAssignedUmpireIds(slotIndex, slots) {
        return [0, 1].reduce(function (ids, otherSlotIndex) {
            var otherSlot = slots && slots[otherSlotIndex];
            if (otherSlotIndex !== slotIndex && otherSlot && otherSlot.umpire_user_id) {
                ids.push(Number(otherSlot.umpire_user_id));
            }
            return ids;
        }, []);
    }

    function renderSlotPanel(slotIndex, label, slot) {
        var panel = document.createElement('section');
        panel.className = 'border rounded p-3 mb-3 bg-white';

        var header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-start gap-2 mb-2';
        appendText(header, 'h6', 'mb-0', label);
        badge(header, slot.status || 'Open', slot.status === 'Published' ? 'bg-success' : (slot.status === 'Draft' ? 'bg-warning text-dark' : 'bg-secondary'));
        panel.appendChild(header);

        if (slot.umpire) {
            var assigned = document.createElement('div');
            assigned.className = 'mb-3';
            appendText(assigned, 'div', 'fw-semibold', fullName(slot.umpire));
            var assignedMeta = document.createElement('div');
            assignedMeta.className = 'd-flex flex-wrap gap-1 mt-1';
            badge(assignedMeta, slot.umpire.umpire_level || 'Level unknown', slot.umpire.umpire_level === 'Black Shirt' ? 'bg-dark' : 'bg-primary');
            if (Number(slot.umpire.is_under_18) === 1) {
                badge(assignedMeta, 'Under 18', 'bg-info text-dark');
            }
            badge(assignedMeta, 'Load ' + Number(slot.umpire.current_game_load || 0), 'bg-light text-dark border');
            assigned.appendChild(assignedMeta);
            appendText(assigned, 'div', 'small text-muted mt-1', [slot.umpire.phone, slot.umpire.email].filter(Boolean).join(' | '));
            panel.appendChild(assigned);
        } else {
            appendText(panel, 'p', 'text-muted mb-3', 'Open');
        }

        var status = document.createElement('div');
        status.className = 'small mb-2';
        panel.appendChild(status);

        var actions = document.createElement('div');
        actions.className = 'd-flex flex-wrap gap-2';

        var choose = document.createElement('button');
        choose.type = 'button';
        choose.className = 'btn btn-primary btn-sm';
        choose.textContent = slot.umpire_user_id ? 'Change' : 'Choose';
        choose.setAttribute('data-slot-action', 'picker');
        choose.setAttribute('data-slot-index', String(slotIndex));
        choose.addEventListener('click', function () {
            openPicker(slotIndex);
        });
        actions.appendChild(choose);

        var unassign = document.createElement('button');
        unassign.type = 'button';
        unassign.className = 'btn btn-outline-secondary btn-sm';
        unassign.textContent = 'Unassign';
        unassign.disabled = !slot.umpire_user_id;
        unassign.addEventListener('click', function () {
            status.className = 'small text-muted mb-2';
            status.textContent = 'Unassigning...';
            choose.disabled = true;
            unassign.disabled = true;
            postSlot('ajax/unassign-slot.php', {
                game_id: activeGameId,
                slot_index: slotIndex
            }).catch(function (error) {
                renderOverrideError(status, error, function (reason) {
                    return postSlot('ajax/unassign-slot.php', {
                        game_id: activeGameId,
                        slot_index: slotIndex,
                        override_reason: reason
                    });
                });
                choose.disabled = false;
                unassign.disabled = false;
            });
        });
        if (slot.umpire_user_id) {
            actions.appendChild(unassign);
        }
        panel.appendChild(actions);

        return panel;
    }

    function openPicker(slotIndex) {
        activePickerSlotIndex = slotIndex;
        pickerFilter = 'all';
        pickerSearch = '';
        pickerMode = 'pool';
        returnFocusSlotIndex = slotIndex;
        renderCurrentDrawer();
    }

    function focusPickerSearch() {
        window.setTimeout(function () {
            var input = body.querySelector('[data-assignment-picker-search]');
            if (input) {
                input.focus();
            }
        }, 0);
    }

    function focusSlotAction(slotIndex) {
        window.setTimeout(function () {
            var action = body.querySelector('[data-slot-action="picker"][data-slot-index="' + slotIndex + '"]');
            if (action) {
                action.focus();
            }
        }, 0);
    }

    function renderPickerView(slotIndex) {
        var data = drawerState || {};
        var labels = data.slot_labels || {};
        var slot = (data.slots || {})[slotIndex] || {};
        var label = labels[slotIndex] || ('Umpire ' + (slotIndex + 1));

        var panel = document.createElement('section');
        panel.className = 'border rounded p-3 mb-3 bg-white';

        var header = document.createElement('div');
        header.className = 'd-flex justify-content-between align-items-start gap-2 mb-3';
        var titleWrap = document.createElement('div');
        appendText(titleWrap, 'h6', 'mb-1', label + ' Picker');
        appendText(titleWrap, 'div', 'small text-muted', slot.umpire ? ('Current: ' + fullName(slot.umpire)) : 'Current: Open');
        header.appendChild(titleWrap);

        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'btn btn-outline-secondary btn-sm';
        back.textContent = 'Back';
        back.addEventListener('click', function () {
            activePickerSlotIndex = null;
            renderCurrentDrawer();
        });
        header.appendChild(back);
        panel.appendChild(header);

        var search = document.createElement('input');
        search.type = 'search';
        search.className = 'form-control mb-2';
        search.value = pickerSearch;
        search.placeholder = 'Search name, level, phone, or email';
        search.setAttribute('aria-label', 'Search umpires');
        search.setAttribute('data-assignment-picker-search', '1');
        panel.appendChild(search);

        var modeGroup = document.createElement('div');
        modeGroup.className = 'btn-group mb-2 w-100';
        modeGroup.setAttribute('role', 'group');
        modeGroup.setAttribute('aria-label', 'Roster view');
        [
            ['pool', 'Available'],
            ['all', 'All Umpires']
        ].forEach(function (entry) {
            var modeBtn = document.createElement('button');
            modeBtn.type = 'button';
            modeBtn.className = 'btn btn-sm ' + (pickerMode === entry[0] ? 'btn-primary' : 'btn-outline-primary');
            modeBtn.textContent = entry[1];
            modeBtn.setAttribute('aria-pressed', pickerMode === entry[0] ? 'true' : 'false');
            modeBtn.setAttribute('data-pool-mode', entry[0]);
            modeBtn.addEventListener('click', function () {
                pickerMode = entry[0];
                renderCurrentDrawer();
            });
            modeGroup.appendChild(modeBtn);
        });
        panel.appendChild(modeGroup);

        var filters = document.createElement('div');
        filters.className = 'btn-group flex-wrap mb-3';
        filters.setAttribute('role', 'group');
        [
            ['all', 'All'],
            ['low-load', 'Low load'],
            ['blue-shirt', 'Blue Shirt'],
            ['black-shirt', 'Black Shirt'],
            ['under-18', 'Under 18']
        ].forEach(function (filter) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm ' + (pickerFilter === filter[0] ? 'btn-primary' : 'btn-outline-primary');
            button.textContent = filter[1];
            button.setAttribute('aria-pressed', pickerFilter === filter[0] ? 'true' : 'false');
            button.addEventListener('click', function () {
                pickerFilter = filter[0];
                renderCurrentDrawer();
            });
            filters.appendChild(button);
        });
        panel.appendChild(filters);

        var count = document.createElement('div');
        count.className = 'small text-muted mb-2';
        count.setAttribute('data-picker-result-count', '1');
        panel.appendChild(count);

        var status = document.createElement('div');
        status.className = 'small mb-2';
        panel.appendChild(status);

        var results = document.createElement('div');
        results.className = 'd-flex flex-column gap-2';
        results.setAttribute('data-picker-results', '1');
        panel.appendChild(results);

        function updateResults() {
            pickerSearch = search.value;
            var fullRoster = pickerRoster(slotIndex);
            var baseRoster = pickerMode === 'pool'
                ? fullRoster.filter(function (u) { return u.in_pool; })
                : fullRoster;
            var filtered = filterPickerRoster(baseRoster);
            var visible = filtered.slice(0, 75);
            results.textContent = '';

            if (pickerMode === 'pool') {
                count.textContent = filtered.length + ' available of ' + fullRoster.length + ' eligible umpires';
            } else {
                count.textContent = filtered.length + ' of ' + fullRoster.length + ' roster umpires';
            }

            if (visible.length === 0) {
                if (pickerMode === 'pool' && !pickerSearch.trim() && pickerFilter === 'all') {
                    var emptyWrap = document.createElement('div');
                    emptyWrap.className = 'text-muted py-2';
                    appendText(emptyWrap, 'div', '', 'No umpires are available for this game time.');
                    appendText(emptyWrap, 'div', 'small mt-1', 'Switch to All Umpires to see the full roster.');
                    results.appendChild(emptyWrap);
                } else {
                    appendText(results, 'div', 'text-muted py-2', 'No matching umpires.');
                }
                return;
            }

            visible.forEach(function (umpire) {
                results.appendChild(renderPickerResult(slotIndex, umpire, status));
            });

            if (filtered.length > visible.length) {
                appendText(results, 'div', 'small text-muted py-2', 'Showing first ' + visible.length + '. Keep typing to narrow results.');
            }
        }

        search.addEventListener('input', updateResults);
        updateResults();

        return panel;
    }

    function pickerRoster(slotIndex) {
        var data = drawerState || {};
        var roster = data.roster || [];
        var unavailableIds = otherAssignedUmpireIds(slotIndex, data.slots || {});
        return roster.filter(function (umpire) {
            return unavailableIds.indexOf(Number(umpire.id)) === -1;
        });
    }

    function filterPickerRoster(roster) {
        var query = pickerSearch.trim().toLowerCase();
        return roster.filter(function (umpire) {
            if (pickerFilter === 'low-load' && Number(umpire.current_game_load || 0) > 1) {
                return false;
            }
            if (pickerFilter === 'blue-shirt' && (umpire.umpire_level || '') !== 'Blue Shirt') {
                return false;
            }
            if (pickerFilter === 'black-shirt' && (umpire.umpire_level || '') !== 'Black Shirt') {
                return false;
            }
            if (pickerFilter === 'under-18' && Number(umpire.is_under_18) !== 1) {
                return false;
            }
            if (!query) {
                return true;
            }
            return [
                fullName(umpire),
                umpire.umpire_level,
                umpire.phone,
                umpire.email
            ].filter(Boolean).join(' ').toLowerCase().indexOf(query) !== -1;
        });
    }

    function renderPickerResult(slotIndex, umpire, status) {
        var row = document.createElement('div');
        row.className = 'border rounded p-2';
        row.setAttribute('data-umpire-result-id', String(umpire.id));

        var top = document.createElement('div');
        top.className = 'd-flex justify-content-between align-items-start gap-2';
        var identity = document.createElement('div');
        appendText(identity, 'div', 'fw-semibold', fullName(umpire));
        var meta = document.createElement('div');
        meta.className = 'd-flex flex-wrap gap-1 mt-1';
        badge(meta, umpire.umpire_level || 'Level unknown', umpire.umpire_level === 'Black Shirt' ? 'bg-dark' : 'bg-primary');
        if (Number(umpire.is_under_18) === 1) {
            badge(meta, 'Under 18', 'bg-info text-dark');
        }
        badge(meta, 'Load ' + Number(umpire.current_game_load || 0), 'bg-light text-dark border');
        identity.appendChild(meta);
        top.appendChild(identity);

        var select = document.createElement('button');
        select.type = 'button';
        select.className = 'btn btn-primary btn-sm';
        select.textContent = 'Select';
        select.addEventListener('click', function () {
            status.className = 'small text-muted mb-2';
            status.textContent = 'Saving...';
            select.disabled = true;
            postSlot('ajax/save-slot.php', {
                game_id: activeGameId,
                slot_index: slotIndex,
                umpire_user_id: umpire.id
            }).catch(function (error) {
                renderOverrideError(status, error, function (reason) {
                    return postSlot('ajax/save-slot.php', {
                        game_id: activeGameId,
                        slot_index: slotIndex,
                        umpire_user_id: umpire.id,
                        override_reason: reason
                    });
                });
                select.disabled = false;
            });
        });
        top.appendChild(select);
        row.appendChild(top);

        appendText(row, 'div', 'small text-muted mt-1', [umpire.phone, umpire.email].filter(Boolean).join(' | '));
        return row;
    }

    function syncTentativeBadge(cell, hasPendingScr, attributeName) {
        if (!cell) {
            return;
        }
        var existing = cell.querySelector('[' + attributeName + ']');
        if (hasPendingScr) {
            if (!existing) {
                var tentativeBadge = badge(cell, 'Tentative', 'bg-warning text-dark ms-1');
                tentativeBadge.setAttribute(attributeName, '');
            }
        } else if (existing) {
            existing.remove();
        }
    }

    function updatePageRow(data) {
        var game = data.game || {};
        var row = document.querySelector('[data-game-id="' + game.game_id + '"]');
        if (!row) {
            return;
        }

        var slots = data.slots || {};
        var filled = [slots[0], slots[1]].filter(function (slot) {
            return slot && slot.status && slot.status !== 'Open' && slot.umpire_user_id;
        }).length;
        var hasPendingScr = Boolean(game.has_pending_scr);

        if (pageMode === 'queue') {
            var count = row.querySelector('[data-slot-count]');
            if (count) {
                count.textContent = filled + '/2';
            }
            syncTentativeBadge(row.querySelector('td'), hasPendingScr, 'data-queue-tentative');
            row.classList.toggle('d-none', filled >= 2);
            return;
        }

        if (pageMode === 'board') {
            var filledCell = row.querySelector('[data-board-filled]');
            if (filledCell) {
                filledCell.textContent = filled + '/2';
            }
            [0, 1].forEach(function (idx) {
                var cell = row.querySelector('[data-board-slot="' + idx + '"]');
                if (cell) {
                    cell.textContent = '';
                    var slot = slots[idx] || {};
                    if (slot.umpire) {
                        appendText(cell, 'span', '', fullName(slot.umpire));
                        badge(cell, slot.status, slot.status === 'Published' ? 'bg-success ms-1' : 'bg-warning text-dark ms-1');
                    } else {
                        appendText(cell, 'span', 'text-muted', '-');
                    }
                }
            });
            var statusCell = row.querySelector('[data-board-status]');
            if (statusCell) {
                var published = [slots[0], slots[1]].filter(function (slot) { return slot && slot.status === 'Published'; }).length;
                var draft = [slots[0], slots[1]].filter(function (slot) { return slot && slot.status === 'Draft'; }).length;
                var label = filled === 0 ? 'Unassigned' : (published === 2 ? 'Published' : (draft > 0 ? 'Draft' : 'Partial'));
                var className = filled === 0 ? 'bg-secondary' : (published === 2 ? 'bg-success' : (draft > 0 ? 'bg-warning text-dark' : 'bg-info'));
                statusCell.textContent = '';
                badge(statusCell, label, className);
                syncTentativeBadge(statusCell, hasPendingScr, 'data-board-tentative');
            }
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-assignment-drawer-trigger]');
        if (!trigger) {
            return;
        }
        event.preventDefault();
        loadDrawer(trigger.getAttribute('data-game-id'));
    });

    document.addEventListener('keydown', function (event) {
        var trigger = event.target.closest('[data-assignment-drawer-trigger]');
        if (!trigger || (event.key !== 'Enter' && event.key !== ' ')) {
            return;
        }
        event.preventDefault();
        loadDrawer(trigger.getAttribute('data-game-id'));
    });
})();
