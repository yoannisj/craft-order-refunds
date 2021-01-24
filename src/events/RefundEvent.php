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

/**
 * Class for Refund event objects
 */

class RefundEvent extends Event
{
    /**
     * @var Refund
     */

    public $refund;

    /**
     * @var bool Whether the refund this event triggered for is new
     */

    public $isNew;
}
