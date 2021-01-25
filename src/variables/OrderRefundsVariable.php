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

    /**
     * Returns list of refund transactions for given Order
     * 
     * @param Order $order
     * 
     * @return Transaction[]
     */

    public static function getRefundTransactions( Order $order ): array
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

    /**
     * Computes options data for given Order's refundable transaction
     * 
     * @return array
     */

    public function getRefundableTransactionOptions( Order $order ): array
    {
        $options = [];

        $transactions = RefundHelper::getRefundableTransactions($order);

        foreach ($transactions as $transaction)
        {
            $labelType = Craft::t('commerce', ucfirst($transaction->type));
            $labelDate = date($transaction->dateCreated, 'Y/m/d H:i');
            $labelGateway = $transaction->getGateway()->name;

            $options[] = [
                'label' => "$labelType ($labelDate) - $labelGateway",
                'value' => $transaction->id,
            ];
        }

        return $options;
    }

    /**
     * Computes table data for refundable line items in given Order.
     * Returns `null` if there are no refundable line items in given Order.
     * 
     * @return array|null
     */

    public function getRefundableLineItemsTableData( Order $order )
    {
        $currency = $order->getPaymentCurrency();
        $lineItems = $order->getLineItems();
        $quantities = RefundHelper::getRefundableLineItemQuantities($order);

        // no refundable line items? no table data!
        if (empty($quantities)) return null;

        $cols = $this->getLineItemsTableCols();
        $rows = [];

        foreach ($quantities as $lineItemId => $qty)
        {
            $lineItem = ArrayHelper::firstWhere($lineItems, 'id', $lineItemId);
            if (!$lineItem) continue;

            $salePriceText = $formatter->asCurrency($lineItem->salePrice, $currency);
            $subtotalText = $formatter->asCurrency($lineItem->subtotal, $currency);

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
            $qtySuffix = '<span class="code extralight"> of '.$qty.'</span>';

            $rows[$lineItem->id] = [
                'description' => '<span class="refund-item-description">'.$lineItem->description.'</span>',
                'salePrice' => '<span class="refund-item-saleprice">'.$salePriceText.'</span>',
                'quantity' => '<span class="refund-item-qty">'.$qtyInput.' '.$qtySuffix.'</span>',
                'subtotal' => '<span class="refund-item-subtotal">'.$subtotalText.'</span>',
            ];
        }

        return [
            'cols' => $cols,
            'rows'=> $rows,
        ];
    }

    /**
     * Computes table data for detail of line items included in given Refund.
     * Returns `null` if given refund does not cover any line item.
     * 
     * @return array|null
     */

    public function getRefundDetailLineItemsTableData( Refund $refund )
    {
        $lineItems = $refund->getLineItems();

        // no line items refunded? no table data!
        if (empty($lineItems)) return null;

        $formatter = Craft::$app->getFormatter();
        $currency = $refund->getPaymentCurrency();

        $cols = $this->getLineItemsTableCols();
        $rows = [];

        foreach ($lineItems as $lineItem)
        {
            $salePriceText = $formatter->asCurrency($lineItem->salePrice, $currency);
            $subtotal = $formatter->asCurrency($lineItem->subtotal, $currency);

            $rows[$lineItem->id] = [
                'description' => $lineItem->description,
                'salePrice' => '<span class="refund-detail-item-saleprice">'.$salePriceText.'</span>',
                'qty' => '<span class="refund-detail-item-qty code">'.$lineItem->qty.'</span>',
                'subtotal' => '<span class="refund-detail-item-subtotal">'.$subtotalText.'</span>',
            ];
        }

        return [
            'cols' => $cols,
            'rows' => $rows,
        ];
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
                'type' => 'text',
                'heading' => Craft::t('commerce', 'Description'),
                'order' => 1,
            ],
            'salePrice' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Sale Price'),
                'order' => 2,
            ],
            'qty' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Quantity'),
                'order' => 3,
            ],
            'subtotal' => [
                'type' => 'html',
                'heading' => Craft::t('commerce', 'Subtotal'),
                'order' => 4,
            ],
        ];
    }
}
