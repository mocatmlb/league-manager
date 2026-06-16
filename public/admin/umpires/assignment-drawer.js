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
    var offcanvas = bootstrap.Offcanvas.getOrCreateInstance(drawer);
    var activeGameId = 0;

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
                    throw new Error(payload.error || 'Request failed.');
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

    function renderDrawer(data) {
        var game = data.game || {};
        title.textContent = 'Game ' + (game.game_number || game.game_id || activeGameId);
        body.textContent = '';

        if (data.migration_mode) {
            var migration = document.createElement('div');
            migration.className = 'alert alert-warning py-2';
            migration.textContent = 'Migration mode is active. Saved slots remain draft and no notifications are sent.';
            body.appendChild(migration);
        }

        var summary = document.createElement('section');
        summary.className = 'mb-3';
        appendText(summary, 'h6', 'text-uppercase text-muted small mb-2', 'Game Details');
        appendText(summary, 'div', 'fw-semibold', (game.away_team || '-') + ' at ' + (game.home_team || '-'));
        appendText(summary, 'div', 'small text-muted', [game.game_date, game.game_time, game.location_name, game.division_name].filter(Boolean).join(' | '));
        body.appendChild(summary);

        var roster = data.roster || [];
        var slots = data.slots || {};
        var labels = data.slot_labels || {};
        [0, 1].forEach(function (slotIndex) {
            body.appendChild(renderSlotPanel(slotIndex, labels[slotIndex] || ('Umpire ' + (slotIndex + 1)), slots[slotIndex] || {}, roster));
        });
    }

    function renderSlotPanel(slotIndex, label, slot, roster) {
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
            assigned.appendChild(assignedMeta);
            appendText(assigned, 'div', 'small text-muted mt-1', [slot.umpire.phone, slot.umpire.email].filter(Boolean).join(' | '));
            panel.appendChild(assigned);
        } else {
            appendText(panel, 'p', 'text-muted mb-3', 'Open');
        }

        var select = document.createElement('select');
        select.className = 'form-select mb-2';
        select.setAttribute('aria-label', label + ' umpire');
        var blank = document.createElement('option');
        blank.value = '';
        blank.textContent = 'Select umpire';
        select.appendChild(blank);

        roster.forEach(function (umpire) {
            var option = document.createElement('option');
            option.value = umpire.id;
            option.textContent = fullName(umpire) + ' - ' + (umpire.umpire_level || 'Level unknown') + ' - Load ' + Number(umpire.current_game_load || 0);
            if (slot.umpire_user_id && Number(slot.umpire_user_id) === Number(umpire.id)) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        panel.appendChild(select);

        var rosterList = document.createElement('div');
        rosterList.className = 'd-flex flex-column gap-2 mb-3';
        roster.forEach(function (umpire) {
            rosterList.appendChild(renderRosterLine(umpire));
        });
        panel.appendChild(rosterList);

        var status = document.createElement('div');
        status.className = 'small mb-2';
        panel.appendChild(status);

        var actions = document.createElement('div');
        actions.className = 'd-flex gap-2';

        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'btn btn-primary btn-sm';
        save.textContent = 'Save';
        save.addEventListener('click', function () {
            if (!select.value) {
                status.className = 'small text-danger mb-2';
                status.textContent = 'Choose an umpire first.';
                return;
            }
            status.className = 'small text-muted mb-2';
            status.textContent = 'Saving...';
            save.disabled = true;
            unassign.disabled = true;
            postSlot('ajax/save-slot.php', {
                game_id: activeGameId,
                slot_index: slotIndex,
                umpire_user_id: select.value
            }).catch(function (error) {
                status.className = 'small text-danger mb-2';
                status.textContent = error.message;
                save.disabled = false;
                unassign.disabled = !slot.umpire_user_id || slot.status === 'Published';
            });
        });
        actions.appendChild(save);

        var unassign = document.createElement('button');
        unassign.type = 'button';
        unassign.className = 'btn btn-outline-secondary btn-sm';
        unassign.textContent = 'Unassign';
        unassign.disabled = !slot.umpire_user_id || slot.status === 'Published';
        unassign.addEventListener('click', function () {
            status.className = 'small text-muted mb-2';
            status.textContent = 'Unassigning...';
            save.disabled = true;
            unassign.disabled = true;
            postSlot('ajax/unassign-slot.php', {
                game_id: activeGameId,
                slot_index: slotIndex
            }).catch(function (error) {
                status.className = 'small text-danger mb-2';
                status.textContent = error.message;
                save.disabled = false;
                unassign.disabled = false;
            });
        });
        actions.appendChild(unassign);
        panel.appendChild(actions);

        return panel;
    }

    function renderRosterLine(umpire) {
        var line = document.createElement('div');
        line.className = 'small border-top pt-2';
        appendText(line, 'div', 'fw-semibold', fullName(umpire));
        var meta = document.createElement('div');
        meta.className = 'd-flex flex-wrap gap-1 my-1';
        badge(meta, umpire.umpire_level || 'Level unknown', umpire.umpire_level === 'Black Shirt' ? 'bg-dark' : 'bg-primary');
        if (Number(umpire.is_under_18) === 1) {
            badge(meta, 'Under 18', 'bg-info text-dark');
        }
        badge(meta, 'Load ' + Number(umpire.current_game_load || 0), 'bg-light text-dark border');
        line.appendChild(meta);
        appendText(line, 'div', 'text-muted', [umpire.phone, umpire.email].filter(Boolean).join(' | '));
        return line;
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

        if (pageMode === 'queue') {
            var count = row.querySelector('[data-slot-count]');
            if (count) {
                count.textContent = filled + '/2';
            }
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
