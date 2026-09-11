(function ($) {
    'use strict';

    var running = false;

    function setRefreshButtonsDisabled(disabled) {
        $('.zotero-viz-refresh-cache, .zotero-viz-refresh-one').prop('disabled', disabled);
    }

    function setProgress(done, total) {
        var pct = total > 0 ? Math.round((done / total) * 100) : 0;
        $('#zotero-viz-progress-bar').css('width', pct + '%');
        $('#zotero-viz-progressbar').attr('aria-valuenow', pct);
        $('#zotero-viz-refresh-count').text(done + ' of ' + total + ' libraries complete');
    }

    function setLibraryState(collectionIndex, state, message) {
        var $item = $('#zotero-viz-refresh-libraries li[data-index="' + collectionIndex + '"]');
        $item.removeClass('waiting running success error').addClass(state);
        $item.find('.zotero-viz-refresh-state').text(message);
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function refreshButtonHtml(index, displayName) {
        return '<button type="button" class="button button-small zotero-viz-refresh-one" data-index="' +
            escapeHtml(index) + '" data-display-name="' + escapeHtml(displayName) + '">Refresh</button>';
    }

    function updateCacheRow(result) {
        if (!result || !result.success) {
            return;
        }
        var $row = $('#zotero-viz-cache-rows tr').filter(function () {
            return $(this).attr('data-display-name') === result.display_name;
        });
        if (!$row.length) {
            return;
        }
        var index = $row.attr('data-index');
        $row.html(
            '<td><strong>' + escapeHtml(result.display_name) + '</strong></td>' +
            '<td>' + escapeHtml(result.library_name || '') + '</td>' +
            '<td>' + escapeHtml(result.type || '') + '</td>' +
            '<td>' + escapeHtml(result.item_count) + '</td>' +
            '<td>' + escapeHtml(result.countries) + '</td>' +
            '<td>' + escapeHtml(result.year_range || 'N/A') + '</td>' +
            '<td>' + escapeHtml(result.updated || 'just now') + '</td>' +
            '<td class="zotero-viz-cache-actions">' + refreshButtonHtml(index, result.display_name) + '</td>'
        );
        if (running) {
            $row.find('.zotero-viz-refresh-one').prop('disabled', true);
        }
    }

    function finishRefresh(ok, summary) {
        running = false;
        setRefreshButtonsDisabled(false);
        $('#zotero-viz-refresh-progress .spinner').removeClass('is-active');
        $('#zotero-viz-refresh-label').text(summary);
        $('#zotero-viz-refresh-dismiss').removeAttr('hidden');
        if (ok) {
            $('#zotero-viz-refresh-progress').addClass('is-complete');
        }
    }

    function dismissRefresh() {
        $('#zotero-viz-refresh-progress').attr('hidden', 'hidden').removeClass('is-complete');
        $('#zotero-viz-refresh-dismiss').attr('hidden', 'hidden');
    }

    function showProgressPanel(label) {
        $('#zotero-viz-refresh-progress')
            .removeAttr('hidden')
            .removeClass('is-complete')
            .get(0).scrollIntoView({ behavior: 'smooth', block: 'start' });
        $('#zotero-viz-refresh-progress .spinner').addClass('is-active');
        $('#zotero-viz-refresh-dismiss').attr('hidden', 'hidden');
        $('#zotero-viz-refresh-label').text(label);
        $('#zotero-viz-refresh-libraries').empty();
        setProgress(0, 1);
    }

    function refreshOne(libraries, index) {
        var lib = libraries[index];
        var total = libraries.length;
        $('#zotero-viz-refresh-label').text('Refreshing ' + lib.display_name + '…');
        setLibraryState(lib.index, 'running', 'Fetching from Zotero…');

        $.ajax({
            url: zoteroVizAdmin.ajaxUrl,
            method: 'POST',
            timeout: 360000,
            data: {
                action: 'zotero_viz_refresh_one',
                nonce: zoteroVizAdmin.nonce,
                index: lib.index
            }
        }).done(function (response) {
            if (!response || !response.success || !response.data || !response.data.result) {
                var err = (response && response.data) ? response.data : 'Refresh failed';
                if (typeof err !== 'string') {
                    err = 'Refresh failed';
                }
                setLibraryState(lib.index, 'error', err);
                setProgress(index + 1, total);
                if (index + 1 < total) {
                    refreshOne(libraries, index + 1);
                } else {
                    finishRefresh(false, 'Cache refresh finished with errors.');
                }
                return;
            }

            var result = response.data.result;
            if (result.success) {
                setLibraryState(lib.index, 'success', result.message);
                updateCacheRow(result);
            } else {
                setLibraryState(lib.index, 'error', result.message || 'Failed');
            }
            setProgress(index + 1, total);

            if (index + 1 < total) {
                refreshOne(libraries, index + 1);
            } else {
                var failed = $('#zotero-viz-refresh-libraries li.error').length;
                if (failed) {
                    finishRefresh(false, 'Cache refresh finished with ' + failed + ' error' + (failed === 1 ? '' : 's') + '.');
                } else {
                    finishRefresh(true, total === 1 ? 'Library cache refreshed.' : 'Cache refresh complete.');
                }
            }
        }).fail(function (xhr) {
            setLibraryState(lib.index, 'error', 'Request failed' + (xhr.status ? ' (HTTP ' + xhr.status + ')' : ''));
            setProgress(index + 1, total);
            if (index + 1 < total) {
                refreshOne(libraries, index + 1);
            } else {
                finishRefresh(false, 'Cache refresh finished with errors.');
            }
        });
    }

    function runRefreshQueue(libraries, preparingLabel) {
        if (!libraries.length) {
            finishRefresh(false, 'No libraries to refresh.');
            return;
        }

        $('#zotero-viz-refresh-label').text(preparingLabel || 'Preparing cache refresh…');
        $('#zotero-viz-refresh-libraries').empty();

        var $list = $('#zotero-viz-refresh-libraries');
        libraries.forEach(function (lib) {
            $list.append(
                $('<li/>', { 'data-index': lib.index, 'class': 'waiting' })
                    .append($('<strong/>').text(lib.display_name))
                    .append(document.createTextNode(' '))
                    .append($('<span/>', { 'class': 'zotero-viz-refresh-state' }).text('Waiting'))
            );
        });
        setProgress(0, libraries.length);
        refreshOne(libraries, 0);
    }

    function fetchLibraryList(done) {
        $.post(zoteroVizAdmin.ajaxUrl, {
            action: 'zotero_viz_refresh_list',
            nonce: zoteroVizAdmin.nonce
        }).done(function (response) {
            if (!response || !response.success || !response.data || !response.data.libraries) {
                var err = (response && response.data) ? response.data : 'Could not start cache refresh.';
                if (typeof err !== 'string') {
                    err = 'Could not start cache refresh.';
                }
                finishRefresh(false, err);
                return;
            }
            done(response.data.libraries);
        }).fail(function () {
            finishRefresh(false, 'Could not start cache refresh.');
        });
    }

    function startRefresh() {
        if (running || typeof zoteroVizAdmin === 'undefined') {
            return;
        }
        running = true;
        setRefreshButtonsDisabled(true);
        showProgressPanel('Preparing cache refresh…');

        fetchLibraryList(function (libraries) {
            runRefreshQueue(libraries, 'Preparing cache refresh…');
        });
    }

    function startRefreshOne(index, displayName) {
        if (running || typeof zoteroVizAdmin === 'undefined') {
            return;
        }
        if (isNaN(index) || index < 0) {
            return;
        }

        running = true;
        setRefreshButtonsDisabled(true);
        showProgressPanel('Preparing refresh for ' + displayName + '…');

        // Reuse the list endpoint so API key / cache-dir checks run before fetching.
        fetchLibraryList(function (libraries) {
            var match = null;
            libraries.forEach(function (lib) {
                if (Number(lib.index) === Number(index)) {
                    match = lib;
                }
            });

            if (!match) {
                finishRefresh(false, 'Library not found. Refresh the page and try again.');
                return;
            }

            runRefreshQueue([match], 'Preparing refresh for ' + match.display_name + '…');
        });
    }

    $(document).on('click', '.zotero-viz-refresh-cache', function (event) {
        event.preventDefault();
        startRefresh();
    });

    $(document).on('click', '.zotero-viz-refresh-one', function (event) {
        event.preventDefault();
        var $btn = $(this);
        var index = parseInt($btn.attr('data-index'), 10);
        var displayName = $btn.attr('data-display-name') || 'library';
        startRefreshOne(index, displayName);
    });

    $(document).on('click', '#zotero-viz-refresh-dismiss', function (event) {
        event.preventDefault();
        dismissRefresh();
    });
})(jQuery);
