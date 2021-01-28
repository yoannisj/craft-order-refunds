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
use yii\base\InvalidArgumentException;

use Craft;
use craft\helpers\ArrayHelper;

use craft\commerce\Plugin as Commerce;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\records\RefundRecord;
use yoannisj\orderrefunds\events\RefundEvent;
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

    // =Properties
    // =========================================================================

    /**
     * @var array
     */

    private $_refundsByOrderId = [];

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

            $refunds = [];
            $records = RefundRecord::findAll([
                'transactionId' => $transactionIds
            ]);
    
            foreach ($records as $record) {
                $refunds[] = new Refund($record->getAttributes());
            }
    
            $this->_refundsByOrderId[$order->id] = $refunds;
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
     * Saves a refund to the database
     * 
     * @param Refund $refund
     * 
     * @return bool Whether the record was saved successfully
     */

    public function saveRefund( Refund $refund, bool $runValidation = true ): bool
    {
        $isNewRefund = !$refund->id;

        if ($isNewRefund) { // refunds are immutable
            throw new InvalidArgumentException(
                'Can not modify existing refund with ID '.$refund->id);
        }

        // make sure refund has a reference
        if (empty($refund->reference))
        {
            $template = OrderRefunds::$plugin->getSettings()
                ->refundReferenceTemplate;
            $refund->reference = Craft::$app->getView()
                ->renderObjectTemplate($template, $refund);
        }

        // trigger beforeSave event
        $this->trigger(
            self::EVENT_BEFORE_SAVE_REFUND,
            new RefundEvent([
                'refund' => $refund,
                'isNew' => $isNewRefund,
            ])
        );

        // validate refund model before saving it to the db
        if ($runValidation && !$refund->validate())
        {
            Craft::info(('Refund not saved due to validation error: ' .
                print_r($refund->errors, true)), __METHOD__);
            return false;
        }

        // Save refund using a refund record
        $config = $refund->getAttributes();
        $record = new RefundRecord($config);

        $record->save();

        // trigger afterSave event
        $this->trigger(
            self::EVENT_AFTER_SAVE_REFUND,
            new RefundEvent([
                'refund' => $refund,
                'isNew' => $isNewRefund,
            ])
        );

        return true;
    }
}