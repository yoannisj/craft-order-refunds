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

namespace yoannisj\orderrefunds\services;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\InvalidArgumentException;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json as JsonHelper;

use craft\commerce\Plugin as Commerce;
use craft\commerce\records\Transaction as TransactionRecord;
use craft\commerce\elements\Order;
use craft\commerce\errors\RefundException;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\records\RefundRecord;
use yoannisj\orderrefunds\events\RefundEvent;
use yoannisj\orderrefunds\events\RefundLineItemEvent;
use yoannisj\orderrefunds\helpers\RefundHelper;

/**
 * Service component implementing CRUD operations for Refund models
 * 
 * @since 0.1.0
 */

class Refunds extends Component
{
    // =Static
    // =========================================================================

    // Events
    const EVENT_BEFORE_SAVE_REFUND = 'beforeSaveRefund';
    const EVENT_AFTER_SAVE_REFUND = 'afterSaveRefund';
    const EVENT_BEFORE_RESTOCK_REFUND_ITEM = 'beforeRestockRefundItem';
    const EVENT_AFTER_RESTOCK_REFUND_ITEM = 'afterRestockRefundItem';
    const EVENT_BEFORE_CREATE_REFUND_TRANSACTION = 'beforeCreateRefundTransaction';
    const EVENT_AFTER_CREATE_REFUND_TRANSACTION = 'afterCreateRefundTransaction';

    // =Properties
    // =========================================================================

    /**
     * @var array
     */

    private $_refundsByOrderId = [];

    /**
     * @var array
     */

    private $_refundsById = [];

    // =Public Methods
    // =========================================================================

    /**
     * Returns the list of refunds for given order
     * 
     * @param Order $order
     * 
     * @return Refund[]
     */

    public function getRefundsForOrder( Order $order ): array
    {
        if (!array_key_exists($order->id, $this->_refundsByOrderId))
        {
            $transactions = RefundHelper::getRefundTransactions($order);
            $transactionIds = ArrayHelper::getColumn($transactions, 'id');

            // @todo: get all transaction ids in 1 query

            $orderRefunds = [];
            $records = RefundRecord::findAll([
                'transactionId' => $transactionIds
            ]);
    
            foreach ($records as $record)
            {
                $refund = new Refund();
                $refund->setAttributes($record->getAttributes(), false);

                $this->_refundsById[$refund->id] = $refund;
                $orderRefunds[] = $refund;
            }

            $this->_refundsByOrderId[$order->id] = $orderRefunds;
        }

        return $this->_refundsByOrderId[$order->id];
    }

    /**
     * Returns the list of refunds for given order id
     * 
     * @param int $orderId
     * 
     * @return Refund[]
     */

    public function getRefundsForOrderId( int $orderId ): array
    {
        $order = Commerce::getInstance()->getOrders()->getOrderById($orderId);
        if (!$order) return [];

        return $this->getRefundsForOrder($order);
    }

    /**
     * Returns refund for given id
     * 
     * @param int $id
     * 
     * @return Refund|null
     */

    public function getRefundById( int $id )
    {
        if (!array_key_exists($id, $this->_refundsById))
        {
            $refund = null;
            $record = RefundRecord::findOne($id);

            if ($record) {
                $refund = new Refund();
                $refund->setAttributes($record->getAttributes(), false);
            }

            $this->_refundsById[$id] = $refund;
        }

        return $this->_refundsById[$id];
    }

    /**
     * Saves a refund to the database
     * 
     * @param Refund $refund
     * 
     * @return bool Whether the record was saved successfully
     */

    public function saveRefund( Refund $refund, bool $runValidation = true ): bool
    {
        $isNew = !isset($refund->id);

        // set refund scenario
        if ($isNew) {
            $refund->scenario = Refund::SCENARIO_CREATE;
        } else {
            $refund->scenario = Refund::SCENARIO_UPDATE;

            // ensure updated refund is actually revisable
            if (!$refund->isRevisable)
            {
                throw new RefundException(Craft::t('order-refunds',
                    "Can not save refund '{reference}' because it is not revisable",
                    [ 'reference' => $refund->reference ]
                ));
            }
        }

        // trigger beforeSave event
        if ($this->hasEventHandlers(self::EVENT_BEFORE_SAVE_REFUND))
        {
            $this->trigger(
                self::EVENT_BEFORE_SAVE_REFUND,
                new RefundEvent([
                    'refund' => $refund,
                    'isNew' => $isNew,
                ])
            );
        }

        $dbTransaction = Craft::$app->getDb()->beginTransaction();

        try
        {
            if (!$this->saveRefundInternal($refund, $runValidation, $isNew))
            {
                $dbTransaction->rollBack();
                return false;
            }

            // commit transaction
            $dbTransaction->commit();
        }

        catch (\Throwable $exception)
        {
            $dbTransaction->rollBack();
            throw $exception;
        }

        if ($this->hasEventHandlers(self::EVENT_AFTER_SAVE_REFUND))
        {
            // trigger afterSave event
            $this->trigger(
                self::EVENT_AFTER_SAVE_REFUND,
                new RefundEvent([
                    'refund' => $refund,
                    'isNew' => $isNew,
                ])
            );
        }

        return true;
    }

    // =Protected Methods
    // ========================================================================

    /**
     * Internal method to save given Refund model to the Database
     * 
     * @param Refund $refund
     * 
     * @return bool
     */

    protected function saveRefundInternal( Refund $refund, bool $runValidation = true, bool $isNew = null ): bool
    {
        if ($isNew === null) {
            $isNew = !isset($refund->id);
        }

        // make sure refund has a reference
        if (empty($refund->reference))
        {
            $template = OrderRefunds::$plugin->getSettings()
                ->refundReferenceTemplate;
            $refund->reference = Craft::$app->getView()
                ->renderObjectTemplate($template, $refund);
        }

        // validate refund model before saving it to the db
        if ($runValidation && !$refund->validate())
        {
            Craft::info(('Refund not saved due to validation error: ' .
                print_r($refund->errors, true)), __METHOD__);

            return false;
        }

        if ($refund->getHasLineItemsToRestock()
            && !$this->restockRefundItems($refund, $isNew)
        ) {
            return false;
        }

        else if (!$refund->transactionId
            && !$this->createRefundTransaction($refund, $isNew)
        ) {
            return false;
        }

        // refunds can only be revised once
        $wasRevisable = $refund->isRevisable;
        $refund->isRevisable = false;

        // Save refund data in database using a record
        if (!$isNew)
        {
            // query existing record
            $record = RefundRecord::findOne($refund->id);
            if (!$record)
            {
                throw new InvalidConfigException(
                    Craft::t(
                        'order-refunds',
                        "Could not find refund with given ID `{id}`",
                        [ 'id' => $refund->id ],
                    )
                );
            }            
        }

        else {
            $record = new RefundRecord();
        }

        // update record and save back in database
        $record->setAttributes($refund->getAttributes(), false);
        $success = (bool)$record->save(); // (note: save returns number of changed rows)

        if (!$success) {
            // restore revisable state on model if save failed
            $refund->isRevisable = $wasRevisable;
        }

        // pass on 'id' to refund model (consumed by event listeners)
        $refund->id = $record->id;

        return $success;
    }

    /**
     * Restocks given Refund's line items
     * 
     * @param Refund $refund
     * @param bool $isNew
     * 
     * @return bool
     */

    protected function restockRefundItems( Refund $refund, bool $isNew = null ): bool
    {
        if ($isNew === null) {
            $isNew = !isset($refund->id);
        }

        $craftElements = Craft::$app->getElements();

        foreach ($refund->getLineItems() as $lineItem)
        {
            $qtyToRestock = $lineItem->getQtyToRestock();
            if (!$qtyToRestock) continue;

            if ($this->hasEventHandlers(self::EVENT_BEFORE_RESTOCK_REFUND_ITEM))
            {
                $this->trigger(
                    self::EVENT_BEFORE_RESTOCK_REFUND_ITEM,
                    new RefundLineItemEvent([
                        'refund' => $refund,
                        'isNew' => $isNew,
                        'lineItem' => $lineItem,
                    ])
                );
            }

            // update purchasable's stock level
            $purchasable = $lineItem->getPurchasable();
            $purchasable->stock += $qtyToRestock;

            // save purchasable with updated stock, or fail
            if (!$craftElements->saveElement($purchasable)) {
                return false;
            }

            // update restocked quantities on refund
            $restockedQty = $refund->restockedQuantities[$lineItem->id] ?? 0;
            $refund->restockedQuantities[$lineItem->id] = $restockedQty + $qtyToRestock;

            if ($this->hasEventHandlers(self::EVENT_AFTER_RESTOCK_REFUND_ITEM))
            {
                $this->trigger(
                    self::EVENT_AFTER_RESTOCK_REFUND_ITEM,
                    new RefundLineItemEvent([
                        'refund' => $refund,
                        'isNew' => $isNew,
                        'lineItem' => $lineItem,
                    ])
                );
            }
        }

        return true;
    }

    /**
     * Creates refund transaction for given refund (refunding parent
     * transaction by total amount)
     * 
     * @param Refund $refund
     * @param bool $isNew
     * 
     * @return bool
     * 
     * @throws RefundException If created refund transaction was not successfull
     */

    protected function createRefundTransaction( Refund $refund, bool $isNew = null ): bool
    {
        if ($isNew === null) {
            $isNew = !isset($refund->id);
        }

        if ($this->hasEventHandlers(self::EVENT_BEFORE_CREATE_REFUND_TRANSACTION))
        {
            $this->trigger(
                self::EVENT_BEFORE_CREATE_REFUND_TRANSACTION,
                new RefundEvent([
                    'refund' => $refund,
                    'isNew' => $isNew
                ])
            );
        }

        // Create Refund transaction and display result
        $transaction = Commerce::getInstance()->getPayments()
            ->refundTransaction(
                $refund->getParentTransaction(),
                $refund->getTotal(),
                $refund->getNote()
            );

        // @todo: Handle 'processing' and 'redirect' transaction statuses
        if ($transaction->status != TransactionRecord::STATUS_SUCCESS)
        {
            $message = $transaction->message;

            // support message format in Paypal API response
            if (empty($message))
            {
                $messageData = ArrayHelper::getValue($transaction->response, 'message');
                if (!empty($messageData)) {
                    $messageData = JsonHelper::decodeIfJson($messageData);
                    $messageName = ArrayHelper::getValue($messageData, 'name');
                    $message = ArrayHelper::getValue($messageData, 'message');
                    $message = empty($messageName) ? $message : "$messageName: $message";
                }
            }

            if(empty($message)) $message = ' ('.$message.')';
            throw new RefundException("Couldn’t refund transaction$message");
        }

        // update refund and its order
        $refund->getOrder()->updateOrderPaidInformation();
        $refund->setTransaction($transaction);

        if ($this->hasEventHandlers(self::EVENT_AFTER_CREATE_REFUND_TRANSACTION))
        {
            $this->trigger(
                self::EVENT_AFTER_CREATE_REFUND_TRANSACTION,
                new RefundEvent([
                    'refund' => $refund,
                    'isNew' => $isNew
                ])
            );
        }

        return true;
    }

}