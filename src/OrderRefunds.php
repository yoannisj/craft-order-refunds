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

namespace yoannisj\orderrefunds;

use Craft;
use craft\base\Plugin;

/**
 * OrderRefunds Craft Plugin class
 * 
 * @since 0.1.0
 */

class OrderRefunds extends Plugin
{
    /**
     * @var \yoannisj\orderrefunds\OrderRefunds
     */

    public static $plugin;

    /**
     * @inheritdoc
     */

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Craft::info('OrderRefunds plugin initialized', __METHOD__);
    }
}