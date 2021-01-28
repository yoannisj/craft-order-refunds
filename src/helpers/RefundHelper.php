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

namespace yoannisj\orderrefunds\helpers;

use Craft;
use craft\helpers\ArrayHelper;

use craft\commerce\models\Transaction;
use craft\commerce\models\LineItem;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannis\orderrefunds\models\Refund;

/**
 * Class with static helper methods to work with Refunds
 * 
 * @since 0.1.0
 */

class RefundHelper
{
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
        $transactions = $order->getTransactions();
        return ArrayHelper::where($transactions, 'type', 'refund');
    }

    /**
     * Returns list of refundable transactions for given Order
     * 
     * @param Order $order
     * 
     * @return Transaction[]
     */

    public static function getRefundableTransactions( Order $order ): array
    {
        $transactions = [];

        foreach ($order->getTransactions() as $transaction)
        {
            if ($transaction->canRefund()) {
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    /**
     * Returns list of refundable line item quantities for given Order
     * (result maps line item id with quantity that was not refunded yet).
     * 
     * @param Order $order
     * 
     * @return array
     */

    public static function getRefundableLineItemQuantities( Order $order ): array
    {
        $lineItems = $order->getLineItems();
        $refunds = OrderRefunds::$plugin->getRefunds()->getRefundsForOrder($order);

        $quantities = [];

        foreach ($lineItems as $lineItem) {
            $quantities[$lineItem->id] = $lineItem->qty;
        }

        foreach ($refunds as $refund)
        {
            foreach ($refund->getLineItems() as $refundItem)
            {
                $lineItemId = $refundItem->id;
                $lineItem = ArrayHelper::firstWhere($lineItems, 'id', $lineItemId);

                if (!$lineItem) continue;

                $refundableQty = $lineItem->qty - $refundItem->qty;

                if ($refundableQty <= 0) {
                    unset($quantities[$lineItemId]);
                } else {
                    $quantities[$lineItemId] = $refundableQty;
                }
            }
        }

        return $quantities;
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
        $refunds = OrderRefunds::$plugin->getRefunds()->getRefundsForOrder($order);

        foreach ($refunds as $refund)
        {
            if ($refund->getIncludesShipping()) {
                return false;
            }
        }

        return true;
    }
}