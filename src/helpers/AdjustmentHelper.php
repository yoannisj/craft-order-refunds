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

namespace yoannisj\orderrefunds\helpers;

use yii\base\InvalidArgumentException;

use Craft;
use craft\helpers\ArrayHelper;

use craft\commerce\models\LineItem;
use craft\commerce\models\OrderAdjustment;
use craft\commerce\records\TaxRate as TaxRateRecord;
use craft\commerce\helpers\Currency as CurrencyHelper;

/**
 * Class with static helper methods to work with Order Adjustments
 * 
 * @since 0.1.0
 */

class AdjustmentHelper
{
    /**
     * @param OrderAdjustment $adjustment
     * @param int $quantity 
     * 
     * @return OrderAdjustment|null
     * 
     * @throws InvalidArgumentException If given $adjustment does not apply to a line item
     */

    public static function quantifyLineItemAdjustment( OrderAdjustment $adjustment, int $quantity )
    {
        if (!$adjustment->lineItemId) {
            throw new InvalidArgumentException('Argument $adjustment must be a line item adjustment');
        }

        // no quantity? no adjustment.
        if ($quantity == 0) return null;

        // no line item? no adjustment.
        $lineItem = $adjustment->getLineItem();
        if (!$lineItem) return null;

        $amount = $adjustment->amount / $lineItem->qty * $quantity;
        $adjustment->amount = CurrencyHelper::round($amount);

        return $adjustment;
    }

    /**
     * Returns the amount that is part of line item's taxable price,
     * for given list of adjustments
     * 
     * @todo: take a $taxable parameter for specific taxable target
     * 
     * @param OrderAdjustment[] $adjustments
     * @param string $taxable
     * @param LineItem|int $lineItem
     * 
     * @return float
     */

    public function lineItemTotalTaxableAdjustment( array $adjustments, sting $taxable, $lineItem ): float
    {
        $lineItemId = null;

        if (is_numeric($lineItem)) {
            $lineItemId = $lineItem;
        } else if ($lineItem instanceof LineItem) {
            $lineItemId = $lineItem->id;
        } else {
            throw new InvalidArgumentException('Argument $lineItem must be a line item id or model');
        }

        if (!$lineItemId) {
            return 0; // throw an error?
        }

        $discount = 0;
        $shippingCost = 0;

        foreach ($adjustments as $adjustment)
        {
            // optionally filter adjustments for given line item
            if ($adjustment->lineItemId != $lineItemId) continue;

            if ($adjustment->type == 'discount') {
                // this removes discounted amount, but also included tax when it does not apply
                $discount += $adjustment->amount;
            } else if ($adjustment->type == 'shipping') {
                $shippingCost += $adjustment->amount;
            }
        }

        switch ($taxable)
        {
            case TaxRateRecord::TAXABLE_PRICE:
                return $discount;
                break;
            case TaxRateRecord::TAXABLE_SHIPPING:
                return $shippingCost;
                break;
            case TaxRateRecord::TAXABLE_PRICE_SHIPPING:
                return $discount + $shippingCost;
                break;
            default:
                return $discount + $shippingCost;
        }
    }


    /**
     * Returns the amount that is part of order's taxable price,
     * for given list of adjustments
     * 
     * @todo: take a $taxable parameter for specific taxable target
     * 
     * @param OrderAdjustment[] $adjustments
     * 
     * @return float
     */

    public static function orderTotalTaxableAdjustment( array $adjustments ): float
    {
        $nonIncludedAmount = 0;
        $taxAmount = 0;
        $includedTaxAmount = 0;

        foreach ($adjustments as $adjustment)
        {
            $isIncluded = $adjustment->included;
            $isTax = ($adjustment->type == 'tax');

            if ($isIncluded == false) $nonIncludedAmount += $adjustment->amount;
            if ($isTax) $taxAmount += $adjustment->amount;
            if ($isTax && $isIncluded) $includedTaxAmount += $adjustment->amount;
        }

        return $nonIncludedAmount - ($taxAmount + $includedTaxAmount);
    }
}