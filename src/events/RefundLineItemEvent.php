<?php 

/**
 * Craft module for Costes business logic
 *
 * @author Yoannis Jamar
 * @copyright Copyright (c) 2019 Yoannis Jamar
 * @link https://github.com/denisyilmaz/www.hotelcostes.com/
 * @package www.hotelcostes.com
 */

namespace yoannisj\orderrefunds\events;

use yii\base\Event;

use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\models\RefundLineItem;

/**
 * Class for Refund Line Item event objects
 * 
 * @since 0.1.0
 */

class RefundLineItemEvent extends Event
{
    /**
     * @var Refund Refund for which the action is being performed
     */

    public $refund;

    /**
     * @var bool Whether the refund this event triggered for is new
     */

    public $isNew;

    /**
     * @var RefundLineItem Line item for which action is being performed
     */

    public $lineItem;
}
