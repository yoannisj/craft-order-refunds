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

use yii\base\InvalidArgumentException;

use Craft;
use craft\helpers\ArrayHelper;

use craft\commerce\models\Transaction;
use craft\commerce\models\LineItem;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;

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
     * Creates a Refund model based on given parameters
     * 
     * @param array $params Parameters used to create the Refund model
     *
     * @return Refund
     * 
     * @throws InvalidArgumentException If params contain a refund id but no corresponding refund exists
     */

    public static function buildRefundFromParams( array $params ): Refund
    {
        // 1. build refund config from params
        $config = null;

        // -> optionally scope config in params with refund id
        $refundId = $params['refundId'] ?? null;
        if ($refundId) $config = $params[$refundId] ?? null;

        // -> optionally scope config in 'refund' key
        if (!$config) $config = $params['refund'] ?? $params;        

        // -> `refundId` could be scoped in 'refund'
        if (!$refundId) $refundId = $config['id'] ?? null;

        // 2.a) retrieve existing refund, based on given refund id
        if ($refundId)
        {
            $refund = OrderRefunds::$plugin->getRefunds()->getRefundById($refundId);

            if (!$refund)
            {
                throw new InvalidArgumentException(Craft::t(
                    'order-refunds',
                    'Could not find refund with given ID `{id}`',
                    [ 'id' => $refundId ]
                ));
            }
        }

        // 2.b) or create new one
        else {
            $refund = new Refund();
        }

        // 3. set attributes from config
        $refund->setAttributes($config, true);

        // 4. set other configurable properties from config
        // (i.e. not saved in a db column, but involved in computations)

        // -> order id can be given in refund config or as global param
        $orderId = $config['orderId'] ?? $params['orderId'] ?? null;
        if ($orderId) $refund->orderId = $orderId;

        $parentTransactionId = $config['parentTransactionId'] ?? null;
        if ($parentTransactionId) $refund->parentTransactionId = $parentTransactionId;

        // -> note can only be overriden if refund has no transaction yet
        if (!$refund->transactionId)
        {
            $note = $config['note'] ?? null;
            if ($note) $refund->note = $note;
        }

        return $refund;
    }

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

    /**
     * Checks whether given line item is restockable
     * 
     * @return bool
     */

    public static function isRestockableLineItem( LineItem $lineItem ): bool
    {
        if ($lineItem->qty == 0) return false;

        $purchasable = $lineItem->getPurchasable();
        if (!$purchasable) return false;

        if ($purchasable->canGetProperty('hasUnlimitedStock')
            && $purchasable->hasUnlimitedStock
        ) {
            return false;
        }

        else if ($purchasable->canGetProperty('stock')) {
            return true;
        }

        return false;
    }
}