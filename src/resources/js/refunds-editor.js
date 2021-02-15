;(function(window, $, undefined) {

    /**
     * =Constructor
     */

    var RefundEditor = function( el, options )
    {
        this.$container = el instanceof $ ? el : $(el);
        this.container = this.$container.get(0);
        this.options = $.extend({}, this.defaults, options || {});

        this.init();
    };

    /**
     * =Defaults
     */

    RefundEditor.defaults = {
        changeDelay: 200,
    };

    /**
     * =Initialize editor
     */

    RefundEditor.prototype.init = function()
    {
        this.initElements();
        this.initState();
        this.startListening();
    };

    /**
     * Initialize editor elements
     */

    RefundEditor.prototype.initElements = function()
    {
        this.$form = this.$container.find('.refund-form');

        if (this.$form.is('form')) {
            this.$form.prop({ method: 'POST', action: '' });
        } else {
            this.$form.wrapInner('<form method="POST" action="">');
            this.$form = this.$form.find('form');
        }
    };

    /**
     * Setup initial editor state
     */

    RefundEditor.prototype.initState = function()
    {
        this.isEditing = !this.$form.hasClass('is-editing');
        this.toggle();

        console.log('init state', this.isEditing);
    };

    /**
     * Start listening to DOM events
     */

    RefundEditor.prototype.startListening = function()
    {
        this.$container.on('click.refund-editor', '.refund-create, .refund-edit',
            $.proxy(this.onEditControlClick, this));

        this.$container.on('click.refund-editor', '.refund-cancel',
            $.proxy(this.onCancelControlClick, this));

        this.$container.on('change.refund-editor', '.refund-item-qty input',
            $.proxy(this.onRefundChange, this));

        this.$container.on('click.refund-editor', '.refund-shipping-field .lightswitch',
            $.proxy(this.onRefundChange, this));
    };

    /**
     * Toggle editor mode
     */

    RefundEditor.prototype.toggle = function()
    {
        return this[ this.isEditing ? 'toggleOff' : 'toggleOn' ]();
    };

    /**
     * Toggle editor mode on
     */

    RefundEditor.prototype.toggleOn = function()
    {
        console.log('toggleOn?', '-> isEditing:', this.isEditing);

        if (this.isEditing) return;
        this.isEditing = true;

        console.log('toggleOn!', '-> isEditing:', this.isEditing);

        this.$container.addClass('is-editing');
    };

    /**
     * Toggle editor mode off
     */

    RefundEditor.prototype.toggleOff = function()
    {
        console.log('toggleOff?', '-> isEditing:', this.isEditing);

        if (!this.isEditing) return;
        this.isEditing = false;

        this.$container.removeClass('is-editing');
    };

    /**
     * Calculates refund based on current form data
     */

    RefundEditor.prototype.calculate = function( )
    {
        if (this.isCalculating) return;
        this.isCalculating = true;

        this.$container.addClass('is-calculating');

        console.log('RefundEditor::calculate()');
    
        $.ajax({
            method: 'POST',
            data: this.$form.serialize(),
            dataType: 'json',
            success: $.proxy(this.handleCalculateSuccess, this),
            error: $.proxy(this.handleCalculateError, this),
        });
    };

    /**
     * Event handler for click events on edit control
     */

    RefundEditor.prototype.onEditControlClick = function(ev)
    {
        ev.preventDefault();

        console.log('RefundEditor::onEditControlClick!');
        this.toggleOn();
    };

    /**
     * Event handler for click events on cancel control
     */

    RefundEditor.prototype.onCancelControlClick = function(ev)
    {
        ev.preventDefault();

        console.log('RefundEditor::onCancelControlClick!');
        this.toggleOff();
    };

    /**
     * Event handler for click events on editor control
     */

    RefundEditor.prototype.onRefundChange = function(ev)
    {
        console.log('RefundEditor::onRefundChange!');

        if (!this.isCalculating)
        {
            if (this.options.changeDelay)
            {
                console.log('change delay...');

                clearTimeout(this._changeTimer);

                var self = this;
                this._changeTimer = setTimeout(function() {
                    self.calculate();
                });
            }

            else {
                console.log('change!');
                this.calculate();
            }
        }
    };

    /**
     * Ajax handler for successful calculation
     */

    RefundEditor.prototype.handleCalculateSuccess = function()
    {
        this.isCalculating = false;
        this.$container.removeClass('is-calculating');

        console.log('RefundEditor::onCalculateSuccess()', arguments);
    };

    /**
     * Ajax handler for errors in calculation
     */

    RefundEditor.prototype.handleCalculateError = function()
    {
        this.isCalculating = false;
        this.$container.removeClass('is-calculating');

        console.log('RefundEditor::onCalculateError()', arguments);
    };

    // export RefundEditor Class
    window.RefundEditor = RefundEditor;

})(window, jQuery);