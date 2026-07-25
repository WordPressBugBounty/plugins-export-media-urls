/* Export Media URLs — admin UI (vanilla JS, no jQuery/select2 dependency). */

function toggleOptions(className, style, labelId, label, onclickFunction) {
    var rows = document.getElementsByClassName(className);
    for (var i = 0; i < rows.length; i++) {
        rows[i].style.display = style;
    }
    var labelElement = document.getElementById(labelId);
    if (labelElement) {
        labelElement.innerHTML = label;
        labelElement.setAttribute("onclick", "javascript: " + onclickFunction + "; return false;");
    }
}

function showAdvanceOptions() {
    toggleOptions('advance-options', 'table-row', 'advanceOptionsLabel', 'Hide Advanced Options', 'hideAdvanceOptions()');
}

function hideAdvanceOptions() {
    toggleOptions('advance-options', 'none', 'advanceOptionsLabel', 'Show Advanced Options', 'showAdvanceOptions()');
}

function moreFilterOptions() {
    toggleOptions('filter-options', 'table-row', 'moreFilterOptionsLabel', 'Hide Filter Options', 'lessFilterOptions()');
}

function lessFilterOptions() {
    toggleOptions('filter-options', 'none', 'moreFilterOptionsLabel', 'Show Filter Options', 'moreFilterOptions()');
}

function showRangeFields() {
    document.getElementById('postRange').style.display = 'block';
}

function hideRangeFields() {
    document.getElementById('postRange').style.display = 'none';
}

function showDateFields() {
    document.getElementById('dateRange').style.display = 'block';
}

function hideDateFields() {
    document.getElementById('dateRange').style.display = 'none';
}

/* ----------------------------------------------------------------------
 * Export-field helpers: presets and per-group select all / none.
 * -------------------------------------------------------------------- */

function emuFieldCheckboxes() {
    return document.querySelectorAll('input[name="export_fields[]"]');
}

/* Open any <details> group that now contains a checked field. */
function emuSyncGroupOpenState() {
    var groups = document.querySelectorAll('details.emu-field-group');
    for (var i = 0; i < groups.length; i++) {
        if (groups[i].querySelector('input[name="export_fields[]"]:checked')) {
            groups[i].setAttribute('open', 'open');
        }
    }
}

function emuApplyPreset(keysCsv) {
    var wanted = keysCsv.length ? keysCsv.split(',') : [];
    var boxes = emuFieldCheckboxes();
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = (wanted.indexOf(boxes[i].value) !== -1);
    }
    emuSyncGroupOpenState();
    emuSyncUsageWarning();
}

function emuSetGroup(groupKey, state) {
    var boxes = document.querySelectorAll('input[name="export_fields[]"][data-emu-group="' + groupKey + '"]');
    for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = state;
    }
}

/* Show the "resource intensive" warning only while a where-used column
 * (data-emu-usage) is ticked. */
function emuSyncUsageWarning() {
    var row = document.getElementById('emuUsageWarningRow');
    if (!row) {
        return;
    }
    var active = document.querySelector('input[name="export_fields[]"][data-emu-usage="1"]:checked');
    row.style.display = active ? 'table-row' : 'none';
}

/* Paginate the on-screen results table in the browser. Page size comes from
 * the .emu-perpage selector (100/250/500/750/1000/all); default is 100. */
function emuPaginateResults() {
    var table = document.getElementById('outputData');
    if (!table || !table.tBodies.length) {
        return;
    }

    var rows = Array.prototype.slice.call(table.tBodies[0].rows);
    var nav = document.querySelector('.emu-pagination');
    var select = document.querySelector('.emu-perpage');
    var prev = nav ? nav.querySelector('.emu-prev') : null;
    var next = nav ? nav.querySelector('.emu-next') : null;
    var indicator = nav ? nav.querySelector('.emu-page-indicator') : null;

    var current = 1;

    function pageSize() {
        if (!select || select.value === 'all') {
            return rows.length || 1;
        }
        var n = parseInt(select.value, 10);
        return (n > 0) ? n : (rows.length || 1);
    }

    function totalPages() {
        return Math.max(1, Math.ceil(rows.length / pageSize()));
    }

    function render() {
        var size = pageSize();
        var pages = totalPages();
        if (current > pages) {
            current = pages;
        }
        var start = (current - 1) * size;
        var end = current * size;
        for (var i = 0; i < rows.length; i++) {
            rows[i].style.display = (i >= start && i < end) ? '' : 'none';
        }
        if (nav) {
            if (pages > 1) {
                nav.style.display = '';
                if (indicator) { indicator.textContent = current + ' / ' + pages; }
                if (prev) { prev.disabled = (current === 1); }
                if (next) { next.disabled = (current === pages); }
            } else {
                nav.style.display = 'none';
            }
        }
    }

    if (prev) {
        prev.addEventListener('click', function () {
            if (current > 1) { current--; render(); }
        });
    }
    if (next) {
        next.addEventListener('click', function () {
            if (current < totalPages()) { current++; render(); }
        });
    }
    if (select) {
        select.addEventListener('change', function () {
            current = 1;
            render();
        });
    }

    render();
}

document.addEventListener('DOMContentLoaded', function () {
    emuPaginateResults();

    var presets = document.querySelectorAll('.emu-preset');
    for (var i = 0; i < presets.length; i++) {
        presets[i].addEventListener('click', function () {
            emuApplyPreset(this.getAttribute('data-emu-preset') || '');
        });
    }

    var selectAll = document.querySelectorAll('.emu-group-all');
    for (var j = 0; j < selectAll.length; j++) {
        selectAll[j].addEventListener('click', function (e) {
            e.preventDefault();
            emuSetGroup(this.getAttribute('data-emu-group'), true);
            emuSyncUsageWarning();
        });
    }

    var selectNone = document.querySelectorAll('.emu-group-none');
    for (var k = 0; k < selectNone.length; k++) {
        selectNone[k].addEventListener('click', function (e) {
            e.preventDefault();
            emuSetGroup(this.getAttribute('data-emu-group'), false);
            emuSyncUsageWarning();
        });
    }

    /* Toggling any field checkbox re-evaluates the where-used warning. */
    var fieldBoxes = emuFieldCheckboxes();
    for (var m = 0; m < fieldBoxes.length; m++) {
        fieldBoxes[m].addEventListener('change', emuSyncUsageWarning);
    }
    emuSyncUsageWarning();
});
