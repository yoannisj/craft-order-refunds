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


return [

    /**
     * Template used to create the order refund reference string
     * 
     * Properties of \yoannisj\orderrefunds\models\Refund can be output within
     * `{}` signs. For example: "#{seq('refund:' ~ dateCreated|date('ym'), 4)}"
     * 
     * @type string
     * @default "Refund #{dateCreated|date('ymdHi')}"
     * 
     * @since 0.1.0
     */

    'refundReferenceTemplate' => "Refund #{seq('refund:' ~ dateCreated|date('ym'), 4)}",

];