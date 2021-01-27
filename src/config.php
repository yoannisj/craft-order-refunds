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

/**
 * Config values for craft's Order Refunds plugin
 * 
 * @since 0.1.0
 */

return [

    /**
     * Template string used to generate a unique reference for each refund.
     * Output refund properties within `{}` signs. For example: "Refund #{transactionDate|date('ymdHi')}"
     * 
     * @type string
     * @default "Refund #{transactionDate|date('ym')}-{seq('refund:' ~ transactionDate|date('ym'), 4)}"
     * 
     * @since 0.1.0
     */

    'refundReferenceTemplate' => "Refund #{transactionDate|date('ym')}-{seq('refund:' ~ transactionDate|date('ym'), 4)}",

];