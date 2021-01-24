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

    public function attributes()
    {
        $attributes = parent::attributes();

        $attributes[] = 'refund';

        return $attributes;
    }

    /**
     * Setter for the `refund` property
     * 
     * @param Refund $value
     */

    public function setRefund( Refund $value )
    {
        $this->_refund = $refund;

        // force re-calculating computed properties that are affected
        unset($this->_adjustments);
    }

    /**
     * Getter for the `refund` property
     * 
     * @return Refund
     */

    public function getRefund(): Refund
    {
        return $this->refund;
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