(function (window, document) {
    'use strict';
    window.panelMediaText = function (value) {
        var node = document.createElement('span');
        node.textContent = value == null ? '' : String(value);
        return node;
    };
    window.panelMediaEscape = function (value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
        });
    };
    window.panelMediaConfirm = function (message, cancel, ok, callback) {
        return window.bootbox.confirm(window.panelMediaText(message), window.panelMediaEscape(cancel), window.panelMediaEscape(ok), callback);
    };
    window.panelMediaPrompt = function (message, cancel, ok, callback, initial) {
        return window.bootbox.prompt(window.panelMediaEscape(message), window.panelMediaEscape(cancel), window.panelMediaEscape(ok), callback, window.panelMediaEscape(initial));
    };
    window.panelMediaModal = function (content, title) {
        return window.bootbox.modal(content, window.panelMediaEscape(title));
    };
    window.panelMediaCopyDialog = function (url, label) {
        var content = document.createElement('div');
        var heading = document.createElement('div');
        heading.textContent = 'URL:';
        content.appendChild(heading);
        var row = document.createElement('div');
        row.className = 'input-append';
        row.style.width = '100%';
        var input = document.createElement('input');
        input.id = 'url_text';
        input.type = 'text';
        input.style.width = '80%';
        input.style.height = '30px';
        input.value = url;
        row.appendChild(input);
        var button = document.createElement('button');
        button.id = 'copy-button';
        button.type = 'button';
        button.className = 'btn btn-inverse copy-button';
        button.style.width = '20%';
        button.style.height = '30px';
        button.setAttribute('data-clipboard-target', '#url_text');
        button.title = 'copy';
        var icon = document.createElement('i');
        icon.className = 'icon icon-white icon-share';
        button.appendChild(icon);
        var text = document.createElement('span');
        text.textContent = ' ' + label;
        button.appendChild(text);
        row.appendChild(button);
        content.appendChild(row);
        return content;
    };
}(window, document));
