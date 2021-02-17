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

use yii\validators\InlineValidator;

use Craft;
use craft\base\Model;

use craft\commerce\Plugin as Commerce;
use craft\commerce\models\LineItem;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;

/**
 * Model representing a line item in an order refund
 * 
 * @since 0.1.0
 */

class RefundLineItem extends LineItem
{
    // =Static
    // =========================================================================

    // =Properties
    // =========================================================================

    /**
     * @var bool
     */

    private $_restock = false;

    /**
     * @var Refund
     */

    private $_refund;

    /**
     * @var OrderAdjustment[]
     */

    private $_adjustments;

    // =Public Methods
    // =========================================================================

    // =Attributes
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function attributes(): array
    {
        $attributes = parent::attributes();

        $attributes[] = 'restock';
        $attributes[] = 'refund';

        return $attributes;
    }

    /**
     * Setter for the `restock` property
     * 
     * @param bool $restock
     */

    public function setRestock( bool $restock = null )
    {
        $this->_restock = !!($restock);
    }

    /**
     * Getter for the `restock` property
     * 
     * @return bool
     */

    public function getRestock(): bool
    {
        return $this->qty ? $this->_restock : false;
    }

    /**
     * Setter for the `refund` property
     * 
     * @param Refund $refund
     */

    public function setRefund( Refund $refund )
    {
        $this->_refund = $refund;

        // force re-calculating computed properties that are affected
        $this->_adjustments = null;
    }

    /**
     * Getter for the `refund` property
     * 
     * @return Refund
     */

    public function getRefund(): Refund
    {
        return $this->_refund;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     *
     * @return array
     */

    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'price';
        $attributes[] = 'saleAmount';
        $attributes[] = 'salePrice';
        $attributes[] = 'subtotal';
        $attributes[] = 'total';
        $attributes[] = 'discount';
        $attributes[] = 'shippingCost';
        $attributes[] = 'tax';
        $attributes[] = 'taxIncluded';
        $attributes[] = 'adjustmentsTotal';

        return $attributes;
    }

    // =Validation
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function rules()
    {
        $rules = parent::rules();

        // add 'refund' to the required attributes
        $requiredAttr = ($rules['attrRequired'][0] ?? []) + ['refund'];
        $rules['attrRequired'] = [ $requiredAttr, 'required' ];

        return $rules;
    }

    // =Fields
    // -------------------------------------------------------------------------

    /**
     * @inheritdoc
     */

    public function fields(): array
    {
        $fields = parent::fields();

        unset($fields['refund']);

        return $fields;
    }

    /**
     * @return OrderAdjustment
     */

    public function getAdjustments(): array
    {
        if (!isset($this->_adjustments)) {
            $this->_adjustments = $this->calculateAdjustments();
        }

        return $this->_adjustments;
    }

    // =Protected Methods
    // =========================================================================

    /**
     * @return OrderAdjustment
     */

    protected function calculateAdjustments(): array
    {
        $refund = $this->getRefund();
        if (!$refund) return [];

        $adjustments = [];

        foreach ($refund->getAdjustments() as $adjustment)
        {
            if ($adjustment->lineItemId
                && $adjustment->lineItemId == $this->id)
            {
                $adjustments[] = $adjustment;
            }
        }

        return $adjustments;
    }
}