<?php
/**
 * Order Refunds plugin for Craft CMS 3.x
 *
 * Detailed refunds for Craft Commerce orders
 *
 * @author Yoannis Jamar
 * @copyright Copyright (c) 2017 Yoannis Jamar
 * @link https://github.com/yoannisj
 * @package craft-order-refunds
 */

namespace yoannisj\orderrefunds\models;

use yii\validators\InlineValidator;

use Craft;
use craft\base\Model;

use craft\commerce\Plugin as Commerce;
use craft\commerce\models\Transaction;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\RefundLineItem;
use yoannisj\orderrefunds\helpers\RefundHelper;
use yoannisj\orderrefunds\helpers\AdjustmentHelper;

/**
 * Model representing an order refund
 * 
 * @since 0.1.0
 * 
 * @todo Support arbitrary compensations in refunds
 */

class Refund extends Model
{
    // =Static
    // =========================================================================

    // =Properties
    // =========================================================================

    /**
     * @var int
     */

    public $id;

    /**
     * @var string
     */

    public $reference;

    /**
     * @var int
     */

    public $transactionId;

    /**
     * @var \craft\commerce\models\Transaction
     */

    private $_transaction;

    /**
     * @var bool
     */

    private $_includesAllLineItems = false;

    /**
     * @var array
     */

    private $_lineItemsData = [];

    /**
     * @var bool
     */

    private $_includesShipping = false;

    /**
     * @var \modules\costes\models\RefundLineItem[]
     */

    private $_lineItems;

    /**
     * @var \craft\commerce\models\OrderAdjustment[]
     */

    private $_adjustments;

    /**
     * @var string|Datetime
     */

    public $dateCreated;

    /**
     * @var string|Datetime
     */

    public $dateUpdated;

    /**
     * @var string
     */

    public $uid;

    // =Public Methods
    // =========================================================================

    // =Attributes
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function attributes()
    {
        $attributes = parent::attributes();

        $attributes[] = 'includesAllLineItems';
        $attributes[] = 'lineItemsData';
        $attributes[] = 'includesShipping';

        return $attributes;
    }

    /**
     * Setter for the `includesAllLineItems` property
     * 
     * @param bool $value
     */

    public function setIncludesAllLineItems( bool $value )
    {
        $this->_includesAllLineItems = $value;

        // force re-calculation of computed properties that are affected
        unset($this->_lineItems);
        unset($this->_adjustments);
    }

    /**
     * Getter for the `lineItemsData` property
     * 
     * @return bool
     */

    public function getIncludesAllLineItems(): bool
    {
        return $this->_includesAllLineItems;
    }

    /**
     * Setter for the `lineItemsData` property
     * 
     * @param array $value
     */

    public function setLineItemsData( array $value )
    {
        $this->_lineItemsData = $value;

        // force re-calculation of computed properties that are affected
        unset($this->_lineItems);
        unset($this->_adjustments);
    }

    /**
     * Getter for the `lineItemsData` property
     * 
     * @return array
     */

    public function getLineItemsData(): array
    {
        return $this->_lineItemsData;
    }

    /**
     * Setter for the `includesShipping` property
     * 
     * @param bool $value
     */

    public function setIncludesShipping( bool $value )
    {
        $this->_includesShipping = $value;

        // force re-calculation of computed properties that are affected
        unset($this->_adjustments);
    }

    /**
     * Getter for the `includesShipping` property
     * 
     * @return bool
     */

    public function getIncludesShipping(): bool
    {
        return $this->_includesShipping;
    }

    // =Validation
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function rules()
    {
        $rules = parent::rules();

        $rules['attrRequired'] = [ ['transactionId', 'reference'], 'required' ];

        $rules['attrBoolean'] = [ ['includesAllLineItems', 'includesShipping'], 'boolean' ];
        $rules['attrArray'] = [ ['lineItemsData'], 'array' ];
        $rules['attrId'] = [ ['transactionId'], 'integer', 'min' => 1 ];

        $rules['transactionIdRefund'] = [
            'transactionId',
            function(string $attribute, $params, InlineValidator $validator, $currentId)
            {
                $transaction = $this->getTransaction();
                if ($transaction && $transaction->type != 'refund')
                {
                    $validator->addError($this, $attribute,
                        "The `{attribute}` transaction must be of type 'refund'");
                }
            }
        ];

        return $rules;
    }

    // =Fields
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function fields()
    {
        $fields = parent::fields();

        $fields[] = 'transactionDate';
        $fields[] = 'orderId';
        $fields[] = 'lineItems';
        $fields[] = 'totalQty';
        $fields[] = 'itemSubtotal';

        $fields[] = 'adjustments';
        $fields[] = 'orderAdjustments';

        $fields[] = 'total';
        $fields[] = 'totalShippingCost';
        $fields[] = 'totalTax';
        $fields[] = 'totalTaxIncluded';

        return $fields;
    }

    /**
     * @inheritdoc
     */

    public function extraFields()
    {
        $fields = parent::extraFields();

        $fields[] = 'transaction';
        $fields[] = 'order';

        return $fields;
    }

    /**
     * @return \craft\commerce\models\Transaction \ null
     */

    public function getTransaction()
    {
        if (!isset($this->_transaction) && $this->transactionId)
        {
            $this->_transaction = Commerce::getInstance()
                ->getTransactions()->getTransactionById($this->transactionId);
        }

        return $this->_transaction;
    }

    /**
     * @return \DateTime|null
     */

    public function getTransactionDate()
    {
        $transaction = $this->getTransaction();
        // return date of creation since transactions can not be updated
        return ($transaction ? $transaction->dateCreated : null);
    }

    /**
     * @return \craft\commerce\elements\Order | null
     */

    public function getOrder()
    {
        $transaction = $this->getTransaction();
        return ($transaction ? $transaction->getOrder() : null);
    }

    /**
     * @return integer | null
     */

    public function getOrderId()
    {
        $order = $this->getOrder();
        return $order ? $order->id : null;
    }

    /**
     * Returns order line items covered by the refund
     * 
     * @return \modules\costes\models\RefundLineItem[]
     */

    public function getLineItems(): array
    {
        if (!isset($this->_lineItems)) {
            $this->_lineItems = $this->calculateLineItems();
        }

        return $this->_lineItems;
    }

    /**
     * @return int
     * 
     * Returns the total number of items covered by the refund
     */

    public function getTotalQty(): int
    {
        $totalQty = 0;

        foreach ($this->getLineItems() as $lineItem) {
            $totalQty += $lineItem->qty;
        }

        return $totalQty;
    }

    /**
     * Returns the subtotal of line items covered by the refund
     * 
     * @return int
     */

    public function getItemSubtotal(): int
    {
        $subtotal = 0;

        foreach ($this->getLineItems() as $lineItem) {
            $subtotal += $lineItem->getSubtotal();
        }

        return $subtotal;
    }

    /**
     * Returns all order adjustments covered by the refund
     * 
     * @return \craft\commerce\models\OrderAdjustment[]
     */

    public function getAdjustments(): array
    {
        if (!isset($this->_adjustments)) {
            $this->_adjustments = $this->calculateAdjustments();
        }

        return $this->_adjustments;
    }

    /**
     * Returns order-level adjustments covered by the refund
     * 
     * @return \craft\commerce\models\OrderAdjustment[]
     */

    public function getOrderAjustments(): array
    {
        $adjustments = $this->getAdjustments();
        $orderAdjustments = [];

        foreach ($adjustments as $adjustment)
        {
            if (!$adjustment->getLineItem()) {
                $orderAdjustments[] = $adjustment;
            }
        }

        return $orderAdjustments;
    }

    /**
     * Returns total shipping cost covered by the refund
     * 
     * @return int
     */

    public function getTotalShippingCost(): int
    {
        return 0;
    }

    /**
     * Returns total tax amount covered by the refund
     * 
     * @return int
     */

    public function getTotalTax(): int
    {
        return 0;   
    }

    /**
     * Returns total included tax amount covered by the refund
     * 
     * @return int
     */

    public function getTotalTaxIncluded(): int
    {
        return 0;
    }

    // =Protected Methods
    // =========================================================================

    /**
     * @return \modules\costes\models\RefundLineItem[]
     */

    protected function calculateLineItems(): array
    {
        $order = $this->getOrder();
        if (!$order) return [];

        $orderLineItems = $order->getLineItems();
        $includesAllItems = $this->getIncludesAllLineItems();
        $lineItemsData = $this->getLineItemsData();

        $lineItems = [];

        foreach ($orderLineItems as $orderLineItem)
        {
            $refundQty = ($includesAllItems ? $orderLineItem->qty :
                ($lineItemsData[$lineItem->id]['qty'] ?? 0));

            // leave out line items that are not included by refund
            if ($refundQty <= 0) continue;

            $config = $orderLineItem->getAttributes();
            $config['qty'] = $refundQty;

            $lineItems[] = new RefundLineItem($config);
        }

        return $lineItems;
    }

    /**
     * @return \craft\commerce\models\OrderAdjustment[]
     */

    protected function calculateAdjustments(): array
    {
        $order = $this->getOrder();
        if (!$order) return [];

        $orderAdjustments = $order->getAdjustments();

        $includesAllItems = $refund->getIncludesAllLineItems();
        $lineItemsData = $refund->getLineItemsData();
        $includesShipping = $refund->getIncludesShipping();

        $refundAdjustments = [];

        foreach ($orderAdjustments as $adjustment)
        {
            $snapshot = $adjustment->getSourceSnapshot();
            $taxable = $snapshot['taxable'] ?? null;

            if (!$includesShipping)
            {
                // don't include shipping adjustments
                if ($adjustment->type == 'shipping'
                    // nor tax adjustments that apply to shipping costs only
                    || $taxable == TaxRateRecord::TAXABLE_SHIPPING
                    || $taxable == TaxRateRecord::TAXABLE_ORDER_TOTAL_SHIPPING
                    // nor tax adjustments that apply to price + shipping
                    // (those are included after re-calculation below)
                    || $taxable == TaxRateRecord::TAXABLE_PRICE_SHIPPING
                    || $taxable == TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE)
                {
                    continue;
                }
            }

            $refundAdjustment = clone $adjustment;

            // quantify line item adjustments per the refunded quantity
            if (!$includesAllItems && $adjustment->lineItemId)
            {
                $refundQty = $lineItemsData[$lineItem->id]['qty'] ?? 0;
                $refundAdjustment = AdjustmentHelper::quantifyLineItemAdjustment($refundAdjustment, $refundQty);

                // if qty is 0 or line item is not found adjustment becomes null
                if (!$refundAdjustment) continue;
            }

            $refundAdjustments[] = $refundAdjustment;
        }

        if ($includesShipping) {
            return $refundAdjustments; // we are done :)
        }

        // calculate refund item subtotals
        $refundItemSubtotal = 0;
        $refundItemSubtotals = [];

        foreach ($order->getLineItems() as $lineItem)
        {
            $refundQty = ($includesAllItems ? $lineItem->qty : 
                ($lineItemsData[$lineItem->id]['qty'] ?? 0));

            if ($refundQty <= 0) continue;

            $subtotal = $lineItem->salePrice * $refundQty;
            $refundItemSubtotal += $subtotal;
            $refundItemSubtotals[$lineItem->id] = $subtotal;
        }

        // re-calculate and include line item tax adjustments that apply
        // to the line item price + shipping cost
        foreach ($orderAdjustments as $adjustment)
        {
            if (!$adjustment->lineItemId) continue;

            $snapshot = $adjustment->getSourceSnapshot();
            $taxable = $snapshot['taxable'] ?? null;

            // adjustments that apply to other taxable were already included above
            if ($taxable != TaxRateRecord::TAXABLE_PRICE_SHIPPING) continue;

            $lineItem = $adjustment->getLineItem();
            if (!$lineItem) continue; // no line item, no adjustment

            $refundSubtotal = $refundItemSubtotals[$lineItem->id] ?? null;

            // don't re-include adjustments for line items that are not included in refund
            if ($refundSubtotal == null) continue;

            $refundAdjustment = clone $adjustment;
            // overide taxable in snapshot
            $refundAdjustment->setSourceSnapshot(array_merge($snapshot, [
                'taxable' => TaxRateRecord::TAXABLE_PRICE,
            ]));

            // re-calculate tax based on line item price (without shipping cost)
            // (at this point $refundAdjustments are quantified for refunded line items)
            $taxableAdjustment = AdjustmentHelper::lineItemTotalTaxableAdjustment(
                $refundAdjustments, TaxRateRecord::TAXABLE_PRICE, $lineItem);

            $taxableAmount = $refundSubtotal + $taxableAdjustment;
            $taxRate = (float)$snapshot['rate'];
            $isIncluded = $refundAdjustment->included;

            if ($isIncluded) {
                $taxExcludedPrice = ($taxableAmount / (1 + $taxRate));
                $refundAdjustment->amount = ($taxableAmount - $taxExcludedPrice);
            } else {
                $refundAdjustment->amount = $taxableAmount * $taxRate;
            }

            // re-include re-calculated line item tax adjustment
            $refundAdjustments[] = $refundAdjustment;
        }

        // re-caclulate and include order-level tax adjustments that apply
        // to the order price + shipping cost
        foreach ($adjustments as $adjustment)
        {
            if ($adjustment->lineItemId) continue;

            $snapshot = $adjustment->getSourceSnapshot();
            $taxable = $snapshot['taxable'] ?? null;

            // adjustments that apply to other taxable were already included above
            if ($taxable != TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE) continue;

            $refundAdjustment = clone $adjustment;

            // re-calculate tax based on order total price (without shipping cost)
            // (at this point $refundAdjustments are quantified for refunded line items)
            $taxableAdjustment = AdjustmentHelper::orderTotalTaxableAdjustment(
                $refundAdjustments, TaxRateRecord::TAXABLE_ORDER_TOTAL_PRICE);

            $taxableAmount = $refundItemSubtotal + $taxableAdjustment;
            $taxRate = (float)$snapshot['rate'];
            $isIncluded = $refundAdjustment->included;

            if ($isIncluded) {
                $taxExcludedPrice = ($taxableAmount / (1 + $taxRate));
                $refundAdjustment->amount = ($taxableAmount - $taxExcludedPrice);
            } else {
                $refundAdjustment->amount = $taxableAmount * $taxRate;
            }

            $refundAdjustments[] = $refundAjustment;
        }

        return $refundAdjustments;
    }
}