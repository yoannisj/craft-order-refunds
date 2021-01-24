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

use Craft;
use craft\base\Model;

/**
 * Model class for plugin's settings
 * 
 * @since 0.1.0
 */

class Settings extends Model
{
    /**
     * @var string
     */

    public $refundReferenceTemplate = "Refund #{seq('refund:' ~ dateCreated|date('ym'), 4)}";
}