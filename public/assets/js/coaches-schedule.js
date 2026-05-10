document.addEventListener('DOMContentLoaded', function () {
    var table = document.getElementById('scheduleTable');
    if (!table) return;

    var tbody = table.querySelector('tbody');
    var headers = table.querySelectorAll('th[data-col]');
    var colFilters = table.querySelectorAll('.col-filter');
    var dateFrom = document.getElementById('dateFrom');
    var dateTo = document.getElementById('dateTo');
    var clearBtn = document.getElementById('clearFilters');

    var sortState = { col: null, dir: 'asc' };

    // Sort
    headers.forEach(function (th) {
        th.addEventListener('click', function () {
            var col = parseInt(th.getAttribute('data-col'), 10);
            if (sortState.col === col) {
                sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.col = col;
                sortState.dir = 'asc';
            }

            var rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort(function (a, b) {
                var aCell = a.children[col];
                var bCell = b.children[col];
                var aVal, bVal;

                if (col === 1) {
                    aVal = aCell.getAttribute('data-date') || '';
                    bVal = bCell.getAttribute('data-date') || '';
                } else if (col === 6) {
                    aVal = parseScoreForSort(aCell.textContent.trim());
                    bVal = parseScoreForSort(bCell.textContent.trim());
                    if (aVal === null && bVal === null) return 0;
                    if (aVal === null) return 1;
                    if (bVal === null) return -1;
                    var cmp = aVal - bVal;
                    return sortState.dir === 'asc' ? cmp : -cmp;
                } else {
                    aVal = aCell.textContent.trim();
                    bVal = bCell.textContent.trim();
                }

                if (typeof aVal === 'string') {
                    var cmp = aVal.localeCompare(bVal, undefined, { numeric: true });
                    return sortState.dir === 'asc' ? cmp : -cmp;
                }
                return 0;
            });

            rows.forEach(function (row) { tbody.appendChild(row); });
            updateSortIndicators();
        });
    });

    function parseScoreForSort(text) {
        if (!text || text === '—' || text === '–' || text === '-') return null;
        // Handle Unicode en-dash (–), em-dash (—), and standard hyphen (-)
        var parts = text.split(/\s*[–—\-]\s*/);
        if (parts.length >= 1) {
            var n = parseInt(parts[0], 10);
            return isNaN(n) ? null : n;
        }
        return null;
    }

    function updateSortIndicators() {
        headers.forEach(function (th) {
            var col = parseInt(th.getAttribute('data-col'), 10);
            var indicator = th.querySelector('.sort-indicator');
            if (col === sortState.col) {
                indicator.textContent = sortState.dir === 'asc' ? '▲' : '▼';
                th.setAttribute('aria-sort', sortState.dir === 'asc' ? 'ascending' : 'descending');
            } else {
                indicator.textContent = '';
                th.setAttribute('aria-sort', 'none');
            }
        });
    }

    // Filter
    function applyFilters() {
        var textFilters = [];
        colFilters.forEach(function (input) {
            var val = input.value.trim().toLowerCase();
            if (val) {
                textFilters.push({ col: parseInt(input.getAttribute('data-col'), 10), val: val });
            }
        });

        var fromVal = dateFrom ? dateFrom.value : '';
        var toVal = dateTo ? dateTo.value : '';

        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function (row) {
            var show = true;

            for (var i = 0; i < textFilters.length; i++) {
                var f = textFilters[i];
                var cell = row.children[f.col];
                if (!cell || cell.textContent.trim().toLowerCase().indexOf(f.val) === -1) {
                    show = false;
                    break;
                }
            }

            if (show && (fromVal || toVal)) {
                var dateCell = row.children[1];
                var rowDate = dateCell ? dateCell.getAttribute('data-date') || '' : '';
                if (fromVal && rowDate < fromVal) show = false;
                if (toVal && rowDate > toVal) show = false;
            }

            row.style.display = show ? '' : 'none';
        });
    }

    colFilters.forEach(function (input) {
        input.addEventListener('input', applyFilters);
    });
    if (dateFrom) dateFrom.addEventListener('input', applyFilters);
    if (dateTo) dateTo.addEventListener('input', applyFilters);

    // Clear Filters
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            colFilters.forEach(function (input) { input.value = ''; });
            if (dateFrom) dateFrom.value = '';
            if (dateTo) dateTo.value = '';

            sortState.col = null;
            sortState.dir = 'asc';
            updateSortIndicators();

            tbody.querySelectorAll('tr').forEach(function (row) {
                row.style.display = '';
            });
        });
    }
});
