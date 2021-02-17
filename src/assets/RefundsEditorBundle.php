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

namespace yoannisj\orderrefunds\assets;

use Craft;
use craft\web\View;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

/**
 * Asset bundle for Order Refunds sections rendered on commerce Order edit page
 * 
 * @since 0.1.0
 */

class RefundsEditorBundle extends AssetBundle
{
    // =Static
    // =========================================================================

    // =Properties
    // =========================================================================

    /**
     * 
     */

    public $depends = [
        CraftCpAsset::class,
    ];

    // =Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */

    public function init()
    {
        // define the path that your publishable resources live
        $this->sourcePath = "@yoannisj/orderrefunds/resources";

        $this->css = [
            'css/refunds-editor.css',
        ];

        $this->js = [
            'js/refunds-editor.js',
        ];

        parent::init();
    }

    // =Protected Methods
    // =========================================================================

}