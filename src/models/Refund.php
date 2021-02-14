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
use craft\validators\ArrayValidator;

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
     * @var integer Unique identifier for the refund
     */

    public $id;

    /**
     * @var string Unique reference for the refund (uses sequence)
     */

    public $reference;

    /**
     * @var integer ID of refunding transaction
     */

    public $transactionId;

    /**
     * @var Transaction Reference to refunded transaction
     */

    private $_transaction;

    /**
     * @var integer ID of parent transaction (transaction that was refunded)
     */

    private $_parentTransactionId;

    /**
     * @var Transaction Reference to parent transaction (transaction that was refunded)
     */

    private $_parentTransaction;

    /**
     * @var int ID of refunded order
     */

    private $_orderId;

    /**
     * @var Order Reference to refunded order
     */

    private $_order;

    /**
     * @var array Data for line items covered by the refund
     */

    private $_lineItemsData = [];

    /**
     * @var bool Whether the refund covers the order's shipping cost
     */

    private $_includesShipping = false;

    /**
     * @var RefundLineItem[] Computed list of scoped refund line items
     */

    private $_lineItems;

    /**
     * @var OrderAdjustment[] Computed list of scoped refund adjustments
     */

    private $_adjustments;

    /**
     * @var string|Datetime Date at which the refund was created
     */

    public $dateCreated;

    /**
     * @var string|Datetime Date at which the refund was updated
     */

    public $dateUpdated;

    /**
     * @var string Unique identifier string
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

        $attributes[] = 'lineItemsData';
        $attributes[] = 'includesShipping';

        return $attributes;
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
        $this->_lineItems = null;
        $this->_adjustments = null;
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
        $this->_adjustments = null;
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
        $rules['attrArray'] = [ ['lineItemsData'], ArrayValidator::class ];
        $rules['attrId'] = [ ['transactionId'], 'integer', 'min' => 1 ];

        $rules['transactionIdCorrespond'] = [
            'transactionId',
            function(string $attribute, $params, InlineValidator $validator, $value)
            {
                $transaction = $this->getTransaction();
                $orderId = $this->_orderId;
                $parentTransactionId = $this->_parentTransactionId;

                if ($transaction && $orderId && $transaction->orderId != $orderId)
                {
                    $validator->addError($this, $attribute,
                        '{attribute} value `{value}` does not correspond with order');
                }

                if ($transaction && $parentTransactionId && $transaction->$parentTransactionId != $parentTransactionId)
                {
                    $validator->addError($this, $attribute,
                        '{attribute} value `{value}` does not correspond with parent transaction');
                }
            }
        ];

        $rules['orderIdCorrespond'] = [
            'orderId',
            function(string $attribute, $params, InlineValidator $validator, $value)
            {
                $transaction = $this->getTransaction();
                $parentTransaction = $this->getParentTransaction();

                if ($transaction && $transaction->orderId != $value)
                {
                    $validator->addError($this, $attribute,
                        '{attribute} value `{value}` does not correspond with transaction');
                }

                if ($parentTransaction && $parentTransaction->orderId != $value)
                {
                    $validator->addError($this, $attribute, Craft::t('order-refunds',
                        '{attribute} value `{value}` does not correspond with parent transaction'));
                }
            }
        ];

        $rules['parentTransactionIdCorrespond'] = [
            'parentTransactionId',
            function(string $attribute, $params, InlineValidator $validator, $value)
            {
                $parentTransaction = $this->getParentTransaction();
                $orderId = $this->_orderId;
                $transaction = $this->getTransaction();

                if ($transaction && $transaction->parentId != $value)
                {
                    $validator->addError($this, $attribute, Craft::t('order-refunds',
                        '{attribute} value `{value}` does not correspond with transaction.'));
                }

                if ($orderId && $parentTransaction && $orderId != $parentTransaction->orderId)
                {
                    $validator->addError($this, $attribute, Craft::t('order-refunds',
                        '{attribute} value `{value}` does not correspond with order.'));
                }
            }
        ];

        $rules['totalMatchesTransaction'] = [
            'total',
            function(string $attribute, $params, InlineValidator $validator, $value)
            {
                $transaction = $this->getTransaction();
                if ($transaction && $transaction->amount != $this->getTotal())
                {
                    $validator->addError($this, $attribute, Craft::t('order-refunds',
                        '{attribute} must match the refund transaction amount.'));
                }
            },
            'when' => function($model) {
                return !empty($model->transactionId);
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

        $fields[] = 'orderId';
        $fields[] = 'parentTransactionId';
        $fields[] = 'transactionDate';
        $fields[] = 'transactionCurrency';
        $fields[] = 'transactionNote';

        $fields[] = 'lineItems';
        $fields[] = 'totalQty';
        $fields[] = 'itemSubtotal';

        $fields[] = 'adjustments';
        $fields[] = 'orderAdjustments';
        $fields[] = 'totalShippingCost';
        $fields[] = 'totalTax';
        $fields[] = 'totalTaxIncluded';
        $fields[] = 'adjustmentsTotal';

        $fields[] = 'total';
        $fields[] = 'totalPrice';

        return $fields;
    }

    /**
     * @inheritdoc
     */

    public function extraFields()
    {
        $fields = parent::extraFields();

        $fields[] = 'order';
        $fields[] = 'parentTransaction';
        $fields[] = 'transaction';

        return $fields;
    }

    /**
     * Setter for defaulted `orderId` property
     * 
     * @param int|null $orderId
     */

    public function setOrderId( int $orderId = null )
    {
        $this->_orderId = $orderId;

        if ($this->_order
            && $this->_order->id != $orderId)
        {
            $this->_order = null;
        }
    }

    /**
     * Getter for defaulted `orderId` property
     * 
     * @return int|null
     */

    public function getOrderId()
    {
        if (!isset($this->_orderId)
            && ($transaction = $this->getTransaction())
        ) {
            $this->_orderId = $transaction->orderId;
        }

        return $this->_orderId;
    }

    /**
     * Setter for computed `order` property
     * 
     * @param Order|null $value
     */

    public function setOrder( Order $order = null )
    {
        $this->_order = $order;

        if ($order) {
            $this->_orderId = $order->id;
        }
    }

    /**
     * Getter for computed `order` property
     * 
     * @return Order|null
     */

    public function getOrder()
    {
        if (!isset($this->_order)
            && ($orderId = $this->getOrderId())
        ) {
            $this->_order = Commerce::getInstance()->getOrders()
                ->getOrderById($orderId);
        }

        return $this->_order;
    }

    /**
     * Setter for defaulted `parentTransactionId` property
     * 
     * @param int|null $transactionId
     */

    public function setParentTransactionId( int $transactionId = null )
    {
        $this->_parentTransactionId = $transactionId;

        if ($this->_parentTransaction
            && $this->_parentTransaction->id != $transactionId)
        {
            $this->_parentTransaction = null;
        }
    }

    /**
     * Getter for defaulted `parentTransactionId` property
     * 
     * @return int|null
     */

    public function getParentTransactionId()
    {
        if (!isset($this->_parentTransactionId)
            && ($transaction = $this->getTransaction())
        ) {
            $this->_parentTransactionId = $transaction->parentId;
        }

        return $this->_parentTransactionId;
    }

    /**
     * Setter for computed `parentTransaction` property
     * 
     * @param Transaction|null $value
     */

    public function setParentTransaction( Transaction $transaction = null )
    {
        $this->_parentTransaction = $transaction;

        if ($transaction) {
            $this->_parentTransactionId = $transaction->id;
        }
    }

    /**
     * Getter for computed `parentTransaction` property
     * 
     * @return Transaction|null
     */

    public function getParentTransaction()
    {
        if (!isset($this->_parentTransaction)
            && ($parentTransactionId = $this->getParentTransactionId())
        ) {
            $this->_parentTransaction = Commerce::getInstance()->getTransactions()
                ->getTransactionById($parentTransactionId);
        }

        return $this->_parentTransaction;
    }

    /**
     * @return Transaction \ null
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
     * @return string | null
     */

    public function getTransactionCurrency()
    {
        $transaction = $this->getTransaction();
        return $transaction ? $transaction->paymentCurrency : null;
    }

    /**
     * @return string | null
     */

    public function getTransactionNote()
    {
        $transaction = $this->getTransaction();
        return $transaction ? $transaction->note : null;
    }

    /**
     * Returns order line items covered by the refund
     * 
     * @return RefundLineItem[]
     */

    public function getLineItems(): array
    {
        if (!isset($this->_lineItems)) {
            $this->_lineItems = $this->calculateLineItems();
        }

        return $this->_lineItems;
    }

    /**
     * @return float
     * 
     * Returns the total number of items covered by the refund
     */

    public function getTotalQty(): float
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
     * @return float
     */

    public function getItemSubtotal(): float
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
     * @return OrderAdjustment[]
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
     * @return OrderAdjustment[]
     */

    public function getOrderAdjustments(): array
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
     * @return float
     */

    public function getAdjustmentsTotal(): float
    {
        $total = 0;

        foreach ($this->getAdjustments() as $adjustment) {
            $total += $adjustment->amount;
        }

        return $total;
    }

    /**
     * Returns total shipping cost covered by the refund
     * 
     * @return float
     */

    public function getTotalShippingCost(): float
    {
        $total = 0;

        foreach ($this->getAdjustments() as $adjustment)
        {
            if ($adjustment->type == 'shipping') {
                $total += $adjustment->amount;
            }
        }

        return $total;
    }

    /**
     * Returns total tax amount covered by the refund
     * 
     * @return float
     */

    public function getTotalTax(): float
    {
        $total = 0;

        foreach ($this->getAdjustments() as $adjustment)
        {
            if ($adjustment->type == 'tax') {
                $total += $adjustment->amount;
            }
        }
        return 0;   
    }

    /**
     * Returns total included tax amount covered by the refund
     * 
     * @return float
     */

    public function getTotalTaxIncluded(): float
    {
        $total = 0;

        foreach ($this->getAdjustments() as $adjustment)
        {
            if ($adjustment->type == 'tax' && $adjustment->included) {
                $total += $adjustment->amount;
            }
        }

        return $total;
    }

    /**
     * @return float
     */

    public function getTotal(): float
    {
        return $this->getItemSubtotal() + $this->getAdjustmentsTotal();
    }

    /**
     * @return float
     */

    public function getTotalPrice(): float
    {
        return $this->getTotal();
    }

    // =Protected Methods
    // =========================================================================

    /**
     * @return RefundLineItem[]
     */

    protected function calculateLineItems(): array
    {
        $order = $this->getOrder();
        if (!$order) return [];

        $lineItemsData = $this->getLineItemsData();
        $lineItems = [];

        foreach ($order->getLineItems() as $lineItem)
        {
            $refundQty = ($lineItemsData[$lineItem->id]['qty'] ?? 0);

            // leave out line items that are not included by refund
            if ($refundQty <= 0) continue;

            $config = $lineItem->getAttributes();
            $config['qty'] = $refundQty;

            $lineItems[] = new RefundLineItem($config);
        }

        return $lineItems;
    }

    /**
     * @return OrderAdjustment[]
     */

    protected function calculateAdjustments(): array
    {
        $order = $this->getOrder();
        if (!$order) return [];

        $orderAdjustments = $order->getAdjustments();
        $lineItemsData = $this->getLineItemsData();
        $includesShipping = $this->getIncludesShipping();

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
            if ($adjustment->lineItemId)
            {
                $lineItem = $adjustment->getLineItem();
                if (!$lineItem) continue; // no line item, no adjustment

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
            $refundQty = ($lineItemsData[$lineItem->id]['qty'] ?? 0);

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
        foreach ($orderAdjustments as $adjustment)
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

            $refundAdjustments[] = $refundAdjustment;
        }

        return $refundAdjustments;
    }
}