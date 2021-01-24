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

namespace yoannisj\orderrefunds\records;

use Craft;
use craft\db\ActiveRecord;

use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;

/**
 * ActiveRecord model for an order refund
 * 
 * @property integer id
 * @property integer transactionId
 * @property boolean includesAllLineItems
 * @property json lineItemsData
 * @property boolean includesShipping
 * @property DateTime dateCreated
 * @property DateTime dateUpdated
 * @property string uid
 * 
 * @since 0.1.0
 */

class RefundRecord extends ActiveRecord
{
    // =Static
    // =========================================================================

    /**
     * @inheritdoc
     */

    public static function tableName(): string
    {
        return OrderRefunds::TABLE_REFUNDS;
    }

    // =Properties
    // =========================================================================

    // =Public Methods
    // =========================================================================

    // =Protected Methods
    // =========================================================================

}