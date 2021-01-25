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
use craft\web\twig\variables\CraftVariable;

use yoannisj\orderrefunds\services\Refunds;

/**
 * OrderRefunds Craft Plugin class
 * 
 * @property \yoannisj\orderrefunds\services\Refunds $refunds
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

        // Redirect to plugin settings after installation
        Event::on(
            Plugins::class,
            Plugins::EVENT_AFTER_INSTALL_PLUGIN,
            function(PluginEvent $event) use ($request)
            {
                if ($event->plugin === $this && $request->getIsCpRequest())
                {
                    $response->redirect(UrlHelper::cpUrl('settings/plugins/order-refunds'));
                    return Craft::$app->end();
                }
            }
        );

        Craft::info(Craft::t('order-refunds', '{name} plugin initialized', [
            'name' => $this->name
        ]), __METHOD__);
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