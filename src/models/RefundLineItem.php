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
use craft\helpers\ArrayHelper;

use craft\commerce\Plugin as Commerce;
use craft\commerce\models\LineItem;
use craft\commerce\elements\Order;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\helpers\RefundHelper;

/**
 * Model representing a line item in an order refund
 * 
 * @prop read-only {bool} canRestock
 * @prop read-only {int} restockedQty
 * @prop read-only {int} restockableQty
 * @prop read-only {int} qtyToRestock
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

    public $restock = false;

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

        $attributes[] = 'refund';

        return $attributes;
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

        // remove the 'refund' field
        $fields = ArrayHelper::without($fields, 'refund');
        $fields = ArrayHelper::withoutValue($fields, 'refund');

        $fields[] = 'canRestock';
        $fields[] = 'restockedQty';
        $fields[] = 'restockableQty';
        $fields[] = 'qtyToRestock';

        return $fields;
    }

    /**
     * @inheritdoc
     */

    public function extraFields(): array
    {
        $fields = parent::extraFields();

        // add the 'refund' extra field
        $fields[] = 'refund';

        return $fields;
    }

    /**
     * Returns whether line item can be restocked
     * 
     * @return bool
     */

    public function getCanRestock(): bool
    {
        return RefundHelper::isRestockableLineItem($this);
    }

    /**
     * Get line item qty that has been restocked
     * 
     * @return int
     */

    public function getRestockedQty(): int
    {
        $refund = $this->getRefund();

        if (isset($refund->restockedQuantities[$this->id])) {
            return $refund->restockedQuantities[$this->id];
        }

        return 0;
    }

    /**
     * Returns line item quantity that can be restocked
     * 
     * @return int
     */

    public function getRestockableQty(): int
    {
        if (!$this->getCanRestock()) return 0;
        return ($this->qty - $this->getRestockedQty());
    }

    /**
     * 
     */

    public function getQtyToRestock(): int
    {
        if ($this->restock == false) {
            return 0;
        }

        return $this->getRestockableQty();
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