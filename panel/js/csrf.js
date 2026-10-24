(function (window, document, $) {
    'use strict';
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta || !$) { return; }
    var token = meta.getAttribute('content');

    function localUrl(url) {
        var anchor = document.createElement('a');
        anchor.href = url || window.location.href;
        return anchor.protocol === window.location.protocol && anchor.host === window.location.host;
    }

    $.ajaxPrefilter(function (options, originalOptions, xhr) {
        if (localUrl(options.url)) {
            xhr.setRequestHeader('X-CSRF-Token', token);
        }
    });

    $(function () {
        // Native submits (including legacy iframe uploads/downloads) need a body token.
        $('form').each(function () {
            if (localUrl(this.action) && (this.method || '').toUpperCase() === 'POST'
                && !this.querySelector('input[name="csrf_token"]')) {
                $('<input>', {type: 'hidden', name: 'csrf_token', value: token}).appendTo(this);
            }
        });
    });
}(window, document, window.jQuery));
