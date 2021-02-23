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

        this.form = this.$form.get(0);

        this.$lineItemRows = this.$form.find('.refund-form-lineitems tbody tr');
        this.$itemSubtotal = this.$form.find('.refund-form-itemsubtotal');
        this.$shippingCost = this.$form.find('.refund-form-shippingcost');
        this.$addedTax = this.$form.find('.refund-form-addedtax');
        this.$taxIncluded = this.$form.find('.refund-form-taxincluded');
        this.$taxExcluded = this.$form.find('.refund-form-taxexcluded');
        this.$total = this.$form.find('.refund-form-total');
        this.$transactionAmount = this.$form.find('.refund-form-transactionamount');

        this.$attributeElements = this.$form.find('[data-attribute]');
        this.$errors = this.$form.find('.refund-form-errors');
        this.$submit = this.$form.find('.refund-submit');
    };

    /**
     * Setup initial editor state
     */

    RefundEditor.prototype.initState = function()
    {
        this.isEditing = !this.$form.hasClass('is-editing');
        this.toggle();

        this.updateLineItemRows();
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

        this.$form.on('submit.refund-editor', $.proxy(this.onFormSubmit, this));
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
        if (this.isEditing) return;
        this.isEditing = true;

        this.$container.addClass('is-editing');
    };

    /**
     * Toggle editor mode off
     */

    RefundEditor.prototype.toggleOff = function()
    {
        if (!this.isEditing) return;
        this.isEditing = false;

        this.$container.removeClass('is-editing');
    };

    /**
     * Updates rows in line items table
     */

    RefundEditor.prototype.updateLineItemRows = function()
    {
        this.$lineItemRows.each(function()
        {
            var $row = $(this),
                $qtyInput = $row.find('.refund-item-qty input'),
                $restockLightswitch = $row.find('.refund-item-restock .lightswitch'),
                restockLightswitch = $restockLightswitch.data('lightswitch');

            if ($qtyInput.val() == 0)
            {
                $restockLightswitch.disable();
                if (restockLightswitch) restockLightswitch.turnOff();
            }

            else {
                $restockLightswitch.enable();
            }
        });
    };

    /**
     * Calculates refund based on current form data
     */

    RefundEditor.prototype.calculate = function( )
    {
        if (this.isCalculating) return;
        this.isCalculating = true;

        this.$container.addClass('is-calculating');

        // get serialized form params
        var params = this.$form.serializeArray()
            .filter(function(param) { // remove 'action' params
                return (param.name != 'action');
            });

        params.push({
            name: 'action',
            value: 'order-refunds/refunds/calculate'
        });

        console.log('RefundEditor::calculate()', params);

        $.ajax({
            url: '/index.php',
            method: 'POST',
            data: params,
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
        this.toggleOn();
    };

    /**
     * Event handler for click events on cancel control
     */

    RefundEditor.prototype.onCancelControlClick = function(ev)
    {
        ev.preventDefault();
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

    RefundEditor.prototype.onFormSubmit = function(ev)
    {
        var $ctrl = $(ev.originalEvent.submitter),
            confirmationMessage = $ctrl.data('confirmMessage') || this.$form.data('confirmMessage');

        if (confirmationMessage != '' )
        {
            ev.preventDefault();
            if (confirm(confirmationMessage)) {
                this.form.submit(); // won't trigger submit event again
            }
        }
    };

    /**
     * Ajax handler for successful calculation
     */

    RefundEditor.prototype.handleCalculateSuccess = function( results, statusTest, jqXhr )
    {
        this.isCalculating = false;
        this.$container.removeClass('is-calculating');

        console.log('RefundEditor::onCalculateSuccess()', results);

        if (results.success)
        {
            var refund = results.refund;
            this.updateLineItemRows();

            console.log('itemSubtotal', this.$itemSubtotal.length, refund.itemSubtotalAsCurrency);
            console.log('shippingCost', this.$shippingCost.length, refund.totalShippingCostAsCurrency);
            console.log('taxIncluded', this.$taxIncluded.length, refund.totalTaxIncludedAsCurrency);
            console.log('taxExcluded', this.$taxExcluded.length, refund.totalTaxExcludedAsCurrency);
            console.log('total', this.$total.length, refund.totalAsCurrency);
            console.log('transactionAmount', this.$transactionAmount.length, refund.$transactionAmountAsCurrency);

            this.$itemSubtotal.html(refund.itemSubtotalAsCurrency);
            this.$shippingCost.html(refund.totalShippingCostAsCurrency);
            this.$addedTax.html(refund.totalAddedTaxAsCurrency);
            this.$taxIncluded.html(refund.totalTaxIncludedAsCurrency);
            this.$taxExcluded.html(refund.totalTaxExcludedAsCurrency);
            this.$total.html(refund.totalAsCurrency);

            if (this.$transactionAmount.length) {
                this.$transactionAmount.html(refund.transactionAmountAsCurrency);
            }
        }

        // validation feedback
        this.$attributeElements.removeClass('error');

        if (!results.isValid)
        {
            var errorsHtml = '<ul>', attributeErrors;
            for (var attribute in results.validationErrors)
            {
                this.$attributeElements
                    .filter('[data-attribute="'+attribute+'"]')
                    .addClass('error');                    

                attributeErrors = results.validationErrors[attribute];
                errorsHtml += '<li>'+attributeErrors.join('<br />')+'</li>';
            }
            errorsHtml += '</ul>';

            this.$errors.html(errorsHtml).show();
            this.$submit.disable();
        }

        else {
            this.$errors.html('').hide();
            this.$submit.removeAttr('disabled').enable();
        }
    };

    /**
     * Ajax handler for errors in calculation
     */

    RefundEditor.prototype.handleCalculateError = function( jqXhr, statusText, errorMessage )
    {
        this.isCalculating = false;
        this.$container.removeClass('is-calculating');

        console.log('RefundEditor::onCalculateError()', errorMessage);
    };

    // export RefundEditor Class
    window.RefundEditor = RefundEditor;

    // initialize refund editors on page
    $('.refund-editor').each(function() {
        var $editor = $(this);
        $editor.data("refundEditor", new RefundEditor($editor));
    });

    // initiliaze refund editors on commerce order page
    var $orderRefunds = $("#order-refunds");
    var $transactionsTab = $('#transactionsTab');
    
    if ($orderRefunds.length && $transactionsTab.length) {
        // move order refunds to transactions tab, and show them
        $transactionsTab.append($orderRefunds);
        $orderRefunds.show().removeClass("hide").removeAttr("hidden");
    }

})(window, jQuery);