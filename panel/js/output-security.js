(function (window, document) {
    'use strict';
    var grid = window.jsGrid;
    if (!grid) { return; }
    function text(value) {
        return document.createTextNode(value == null ? '' : String(value));
    }
    // Keep vendor files untouched. jsGrid otherwise appends raw field strings as HTML.
    grid.Field.prototype.itemTemplate = function (value) { return text(value); };
    grid.Field.prototype.headerTemplate = function () {
        return text(this.title == null ? this.name : this.title);
    };
    var selectItem = grid.SelectField.prototype.itemTemplate;
    grid.SelectField.prototype.itemTemplate = function () {
        return text(selectItem.apply(this, arguments));
    };
}(window, document));
