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

use yoannisj\orderrefunds\services\Refunds;

/**
 * OrderRefunds Craft Plugin class
 * 
 * @since 0.1.0
 */

class OrderRefunds extends Plugin
{
    // =Properties
    // =========================================================================

    /**
     * Static reference to the plugin's singleton instance
     * 
     * @var \yoannisj\orderrefunds\OrderRefunds
     */

    public static $plugin;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */

    public function init()
    {
        parent::init();

        // add static reference to plugin's singleton instance
        self::$plugin = $this;

        Craft::info(Craft::t('order-refunds', '{name} plugin initialized', [
            'name' => $this->name
        ]), __METHOD__);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */

    protected function createSettingsModel()
    {
        return new Settings();
    }

}