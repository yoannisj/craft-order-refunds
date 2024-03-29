<?php
/**
 * Order Refunds plugin for Craft CMS 3.x
 *
 * Detailed refunds for Craft Commerce orders
 *
 * @author Yoannis Jamar
 * @copyright Copyright (c) 2021 Yoannis Jamar
 * @link https://github.com/yoannisj
 * @package craft-order-refunds
 */

namespace yoannisj\orderrefunds;

use yii\base\Event;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\web\View;
use craft\web\twig\variables\CraftVariable;
use craft\helpers\UrlHelper;
use craft\helpers\Json as JsonHelper;

use yoannisj\orderrefunds\models\Settings;
use yoannisj\orderrefunds\services\Refunds;
use yoannisj\orderrefunds\variables\OrderRefundsVariable;
use yoannisj\orderrefunds\assets\RefundsEditorBundle;

/**
 * OrderRefunds Craft Plugin class
 * 
 * @property \yoannisj\orderrefunds\services\Refunds $refunds
 * 
 * @since 0.1.0
 */

class OrderRefunds extends Plugin
{
    // =Static
    // =========================================================================

    /**
     * Name of database table used to store order Refund records
     */

    const TABLE_REFUNDS = "{{%orderrefunds_refunds}}";

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

        // Register service components to plugin class
        $this->setComponents([
            'refunds' => Refunds::class,
        ]);

        $request = Craft::$app->getRequest();
        $response = Craft::$app->getResponse();

        // Register template root
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS,
            function (RegisterTemplateRootsEvent $event)
            {
                $event->roots['order-refunds'] = __DIR__.'/templates';
            }
        );

        // Extend craft's twig variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function(Event $event)
            {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('orderRefunds', OrderRefundsVariable::class);
            }
        );

        // Load order refunds on Order edit screen
        Craft::$app->getView()->hook(
            'cp.commerce.order.edit.main-pane',
            [$this, 'getOrderRefundsHtml']
        );

        Craft::info(Craft::t('order-refunds', '{name} plugin initialized', [
            'name' => $this->name
        ]), __METHOD__);
    }

    /**
     * @inheritdoc
     */
    public function afterInstall()
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        // Redirect to plugin settings after installation
        // @todo: redirect to a "setup" screen in CP where user can choose
        // the reference format and update refund records for all existing
        // transactions
        // @todo: don't show "new refund" button on order edit CP
        // until all existing refund transactions were updated
        Craft::$app->controller->redirect(
            UrlHelper::cpUrl('settings/plugins/order-refunds')
        )->send();
    }

    /**
     * Getter for plugin's `refunds` service component
     * 
     * @return Refunds
     */

    public function getRefunds(): Refunds
    {
        return $this->get('refunds');
    }

    /**
     * Registers assets and returns html for the refunds edit UI
     * 
     * @return string|null
     */

    public function getOrderRefundsHtml( array &$context )
    {
        if (!Craft::$app->getUser()->checkPermission('commerce-refundPayment')) {
            return '';
        }

        $order = $context['order'] ?? null;

        if (!$order) return '';

        $view = Craft::$app->getView();

        // register assets bundle
        $view->registerAssetBundle(RefundsEditorBundle::class);

        // render html for edit UI
        $html = $view->renderTemplate('order-refunds/order-refunds', [
            'order' => $order
        ]);

        return $html;
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

    /**
     * @inheritdoc
     */

    protected function settingsHtml(): string
    {
        return Craft::$app->getView()->renderTemplate(
            'order-refunds/settings',
            [ 'settings' => $this->getSettings(), ]
        );
    }
}