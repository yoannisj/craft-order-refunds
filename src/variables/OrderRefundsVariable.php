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

namespace yoannisj\orderrefunds\variables;

use Craft;
use craft\helpers\ArrayHelper;

use craft\commerce\models\Transaction;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\services\Refunds;
use yoannisj\orderrefunds\helpers\RefundHelper;

/**
 * Twig templating variable with Order Refunds data and methods
 * 
 * Craft allows plugins to provide their own template variables, accessible from
 * the {{ craft }} global variable (e.g. {{ craft.moduleName }}).
 *
 * The template variable gives access to all public properties and methods
 * defined on this class
 * 
 * Note: this variable class extends the plugin's 'refunds' service component,
 * which means all of the service's public methods are available in twig.
 * 
 * @since 0.1.0
 */

class OrderRefundsVariable extends Refunds
{
    // =Properties
    // =========================================================================

    // =Public Methods
    // =========================================================================

    // =Orders
    // -------------------------------------------------------------------------

    /**
     * Returns list of refund transactions for given Order
     * 
     * @param Order $order
     * 
     * @return Transaction[]
     */

    public function getRefundTransactions( Order $order ): array
    {
        return RefundHelper::getRefundTransactions($order);
    }

    /**
     * Returns list of refundable transactions for given Order
     * 
     * @param Order $order
     * 
     * @return Transaction[]
     */

    public function getRefundableTransactions( Order $order ): array
    {
        return RefundHelper::getRefundableTransactions($order);
    }

    /**
     * Computes options data for given Order's refundable transaction
     * 
     * @return array
     */

    public function getRefundableTransactionOptions( Order $order ): array
    {
        $options = [];

        $formatter = Craft::$app->getFormatter();
        $transactions = RefundHelper::getRefundableTransactions($order);

        foreach ($transactions as $transaction)
        {
            $label = $this->getTransactionLabel($transaction);

            $options[] = [
                'label' => $label,
                'value' => $transaction->id,
            ];
        }

        return $options;
    }

    /**
     * Returns list of refundable line item quantities for given Order
     * (result maps line item id with quantity that was not refunded yet).
     * 
     * @param Order $order
     * 
     * @return array
     */

    public function getRefundableLineItemQuantities( Order $order ): array
    {
        RefundHelper::getRefundableLineItemQuantities($ordr);
    }

    /**
     * Computes table data for refundable line items in given Order.
     * Returns `null` if there are no refundable line items in given Order.
     * 
     * @return array|null
     */

    public function getRefundableLineItemsTableData( Order $order )
    {
        $refundableQuantities = RefundHelper::getRefundableLineItemQuantities($order);
        $orderLineItems = $order->getLineItems();
        $currency = $order->getPaymentCurrency();

        $view = Craft::$app->getView();
        $formatter = Craft::$app->getFormatter();

        $cols = $this->getLineItemsTableCols();
        $rows = [];

        foreach ($refundableQuantities as $lineItemId => $qty)
        {
            $lineItem = ArrayHelper::firstWhere($orderLineItems, 'id', $lineItemId);
            if (!$lineItem) continue;

            $salePrice = $lineItem->salePrice;
            $subtotal = $salePrice * $qty;

            $salePriceText = $formatter->asCurrency($salePrice, $currency);
            $subtotalPriceText = $formatter->asCurrency($subtotal, $currency);

            $qtyName = 'lineItems['.$lineItem->id.'][qty]';
            $qtyInput = $view->renderTemplate('_includes/forms/text', [
                'type' => 'number',
                'name' => $qtyName,
                'value' => 0,
                'size' => 3,
                'min' => 0,
                'max' => $qty,
                'step' => 1,
                'placeholder' => 0,
                'disabled' => false,
            ]);
            $qtySuffix = '<span class="extralight">/&thinsp;'.$qty.'</span>';

            $rows[$lineItem->id] = [
                'description' => $lineItem->description,
                'salePrice' => '<span data-amount="'.$salePrice.'">'.$salePriceText.'</span>',
                'qty' => $qtyInput.$qtySuffix,
                'subtotal' => '<span data-amount="'.$subtotal.'">'.$subtotalPriceText.'</span>',
            ];
        }

        return [
            'cols' => $cols,
            'rows'=> $rows,
        ];
    }

    /**
     * Returns whether given Order's shipping can still be refunded
     * 
     * @param Order $order
     * 
     * @return bool
     */

    public static function canRefundOrderShipping( Order $order ): bool
    {
        return RefundHelper::canRefundOrderShipping($order);
    }

    // =Refunds
    // -------------------------------------------------------------------------

    /**
     * Computes table data for detail of line items included in given Refund.
     * Returns `null` if given refund does not cover any line item.
     * 
     * @return array
     */

    public function getRefundLineItemsTableData( Refund $refund ): array
    {
        $order = $refund->getOrder();
        if (!$order) return []; // no order? no line items table data

        $refundableQuantities = RefundHelper::getRefundableLineItemQuantities($order);
        $orderLineItems = $order->getLineItems();
        $refundLineItems = $refund->getLineItems();

        $view = Craft::$app->getView();
        $formatter = Craft::$app->getFormatter();
        $currency = $refund->getTransactionCurrency();
        $inputNamespace = $refund->id .'[lineItemsData]';

        $cols = $this->getLineItemsTableCols();
        $rows = [];

        foreach ($orderLineItems as $lineItem)
        {
            $lineItemId = $lineItem->id;
            $refundableQty = $refundableQuantities[$lineItemId] ?? 0;

            $refundLineItem = ArrayHelper::firstWhere($refundLineItems, 'id', $lineItemId);
            $refundQty = $refundLineItem ? $refundLineItem->qty : 0;

            // include this refund's line item quantity back into refundable quantity
            $refundableQty += $refundQty;

            // only include refundable line items
            if ($refundableQty === 0) continue;

            // get price texts
            $salePrice = $lineItem->salePrice;
            $subtotal = $salePrice * $refundQty;

            $salePriceText = $formatter->asCurrency($salePrice, $currency);
            $subtotalText = $formatter->asCurrency($subtotal, $currency);

            $qtyHtml = $view->renderTemplate('_includes/forms/text', [
                'type' => 'number',
                'name' => $inputNamespace.'['.$lineItemId.'][qty]',
                'value' => $refundQty,
                'size' => 3,
                'min' => 0,
                'max' => $refundableQty,
                'class' => 'refund-item-qty-input'
            ]).'<small class="extralight">/&thinsp;'.$refundableQty.'</small>';

            $rows[$lineItemId] = [
                'description' => $lineItem->description,
                'salePrice' => $salePriceText,
                'qty' => $qtyHtml,
                'subtotal' => $subtotalText,
            ];
        }

        return [
            'cols' => $cols,
            'rows' => $rows,
        ];
    }

    // =Transactions
    // -------------------------------------------------------------------------

    /**
     * Returns a string label for given transaction
     * 
     * @param Transaction $transaction
     * 
     * @return string
     */

    public function getTransactionLabel( Transaction $transaction ): string
    {
        $formatter = Craft::$app->getFormatter();
        $gatewayLabel = str_replace('(', ', ', str_replace(')', '', $transaction->gateway));

        $label = ucfirst($transaction->type);
        $label .= ' (' .$gatewayLabel.')';
        $label .= " / ".$formatter->asDate($transaction->dateCreated);
        $label .= ' ('.$formatter->asTime($transaction->dateCreated).')';

        return $label;
    }

    /**
     * Returns a string label for given transaction
     * 
     * @param Transaction $transaction
     * 
     * @return string
     */

    public function getTransactionLabelHtml( Transaction $transaction ): string
    {
        $formatter = Craft::$app->getFormatter();

        $label = ucfirst($transaction->type);
        $label .= ' / '.$formatter->asDate($transaction->dateCreated);
        $label .= '<br />'.$formatter->asTime($transaction->dateCreated);

        return $label;
    }

    // =Protected Methods
    // =========================================================================

    /**
     * Returns columns data for table of refund(able) line items
     * 
     * @return array
     */

    protected function getLineItemsTableCols(): array
    {
        return [
            'description' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Description'),
                'order' => 1,
                'class' => 'refund-item-description',
            ],
            'salePrice' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Sale Price'),
                'order' => 2,
                'class' => 'refund-item-saleprice',
            ],
            'qty' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Quantity'),
                'order' => 3,
                'class' => 'refund-item-qty',
            ],
            'subtotal' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Subtotal'),
                'order' => 4,
                'class' => 'refund-item-subtotal',
            ],
        ];
    }
}
