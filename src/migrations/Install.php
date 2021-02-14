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

namespace yoannisj\orderrefunds\migrations;

use Craft;
use craft\db\Table;
use craft\db\Migration;
use craft\helpers\ArrayHelper;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\elements\Order;
use craft\commerce\elements\db\OrderQuery;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\records\RefundRecord;
use yoannisj\orderrefunds\helpers\RefundHelper;

/**
 * Database migration class, to run upon plugin's installation
 * 
 * @since 0.1.0
 */

class Install extends Migration
{
    // =Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */

    public function safeUp()
    {
        try
        {
            $this->createRefundsTable();
            $this->createRefundRecords();
        }

        catch (\Exception $exception)
        {
            if (Craft::$app->getRequest()->getIsConsoleRequest()) {
                echo $exception->getMessage();
                var_dump($exception);
            } else {
                Craft::error($exception->getMessage(), __METHOD__);
                Craft::$app->getErrorHandler()->logException($exception);
            }

            return false;
        }

        return true;
    }

    /**
     * @inheritdoc
     */

    public function safeDown()
    {
        $this->dropTableIfExists(OrderRefunds::TABLE_REFUNDS);
        return true;
    }

    // =Protected Methods
    // =========================================================================

    /**
     * Creates the database table used to store Refund records
     */

    protected function createRefundsTable()
    {
        $refundsTable = OrderRefunds::TABLE_REFUNDS;

        // create db table for refund records
        if (!$this->db->tableExists($refundsTable))
        {
            $this->createTable($refundsTable, [
                'id' => $this->primaryKey(),
                'transactionId' => $this->integer()->notNull(),
                'reference' => $this->string()->notNull(),
                'lineItemsData' => $this->json(),
                'includesShipping' => $this->boolean()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, $refundsTable, 'transactionId', false);
            $this->addForeignKey(
                null, $refundsTable, 'transactionId',
                CommerceTable::TRANSACTIONS, 'id', 'CASCADE'
            );
        }
    }

    /**
     * Creates a refund record for existing Craft Commerce refund transactions
     */

    protected function createRefundRecords()
    {
        $orders = (new OrderQuery(Order::class))
            ->with('transactions')
            ->limit(null)
            ->all();

        $transactions = [];
        foreach ($orders as $order) {
            $transactions += RefundHelper::getRefundTransactions($order);
        }

        // sort refund transactions in ascending order to create references
        ArrayHelper::multisort($transactions, 'dateCreated', SORT_ASC);

        // create missing Refund records for each refund transaction
        $view = Craft::$app->getView();
        $template = OrderRefunds::$plugin->getSettings()->refundReferenceTemplate;

        foreach ($transactions as $transaction)
        {
            // get Refund model and record for order's refund transaction
            $config = [ 'transactionId' => (int)$transaction->id ];
            $record = RefundRecord::findOne($config);
            $model = new Refund($config);

            // transfer model attributes to record or vice-versa
            if (!$record) {
                $record = new RefundRecord();
                $record->setAttributes($model->getAttributes(), false);
            } else {
                $model->setAttributes($record->getAttributes());
            }

            // give each refund record a reference
            if (empty($record->reference))
            {
                $record->reference = $view->renderObjectTemplate($template, $model);
                // save record
                $record->save();
            }
        }
    }
}
