
(function(window, $, undefined) {

    var defaults = {};

     var RefundsEditor = function( options )
    {
        this.options = $.extend({}, defaults, options || {});

        this.$container = $('#refunds-editor');
        this.container = this.$container.get(0);

        this.init();
    };

    RefundsEditor.prototype.init = function()
    {
        $('#transactionsTab').append(this.$container);
        this.$container.show();
    };

    // export
    window.RefundsEditor = RefundsEditor;

})(window, jQuery);