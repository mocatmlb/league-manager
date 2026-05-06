/**
 * District 8 Travel League — Admin League List Management
 *
 * Provides drag-and-drop reordering of the active league list (UX-DR13).
 * Uses SortableJS (loaded via CDN) for drag-and-drop.
 * Falls back to up/down arrow buttons for keyboard/non-pointer environments.
 *
 * Story 2.2 — Admin League List Management Page
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var tbody = document.getElementById('sortable-leagues');
        var orderedIdsInput = document.getElementById('ordered-ids-input');
        var saveOrderBtn = document.getElementById('save-order-btn');

        // Only initialise if the sortable table body is present
        if (!tbody || !orderedIdsInput || !saveOrderBtn) {
            return;
        }

        /**
         * Collect current row IDs in DOM order.
         * @returns {string} comma-separated list of ids
         */
        function getCurrentOrder() {
            var rows = tbody.querySelectorAll('tr.league-row');
            var ids = [];
            rows.forEach(function (row) {
                ids.push(row.getAttribute('data-id'));
            });
            return ids.join(',');
        }

        /** The original order when the page loaded (for dirty-check). */
        var originalOrder = getCurrentOrder();

        /**
         * Update the hidden input and enable/disable the Save Order button
         * based on whether the order has changed.
         */
        function onOrderChange() {
            var currentOrder = getCurrentOrder();
            orderedIdsInput.value = currentOrder;
            saveOrderBtn.disabled = (currentOrder === originalOrder);
            refreshMoveButtons();
        }

        // -------------------------------------------------------
        // Drag-and-drop via SortableJS
        // -------------------------------------------------------
        if (typeof Sortable !== 'undefined') {
            Sortable.create(tbody, {
                handle: '.drag-handle',
                animation: 150,
                ghostClass: 'sortable-ghost',
                onEnd: function () {
                    refreshRowNumbers();
                    onOrderChange();
                }
            });
        }

        // -------------------------------------------------------
        // Up / Down arrow button fallback
        // -------------------------------------------------------
        tbody.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-move]');
            if (!btn) return;

            var direction = btn.getAttribute('data-move');
            var row = btn.closest('tr.league-row');
            if (!row) return;

            if (direction === 'up') {
                var prev = row.previousElementSibling;
                if (prev && prev.classList.contains('league-row')) {
                    tbody.insertBefore(row, prev);
                }
            } else if (direction === 'down') {
                var next = row.nextElementSibling;
                if (next && next.classList.contains('league-row')) {
                    tbody.insertBefore(next, row);
                }
            }

            refreshRowNumbers();
            onOrderChange();
        });

        /**
         * Update the visible position numbers (#) in column 2 after reorder.
         */
        function refreshRowNumbers() {
            var rows = tbody.querySelectorAll('tr.league-row');
            rows.forEach(function (row, index) {
                var numCell = row.querySelector('td.row-number');
                if (numCell) {
                    numCell.textContent = index + 1;
                }
            });
        }

        function refreshMoveButtons() {
            var rows = tbody.querySelectorAll('tr.league-row');
            rows.forEach(function (row, index) {
                var upBtn = row.querySelector('[data-move="up"]');
                var downBtn = row.querySelector('[data-move="down"]');
                if (upBtn) {
                    upBtn.disabled = (index === 0);
                }
                if (downBtn) {
                    downBtn.disabled = (index === rows.length - 1);
                }
            });
        }

        refreshMoveButtons();
        orderedIdsInput.value = originalOrder;

    }); // end DOMContentLoaded

})();
