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
use craft\db\Query;
use craft\db\Migration;
use craft\helpers\ArrayHelper;

use craft\commerce\db\Table as CommerceTable;
use craft\commerce\models\Transaction;
use craft\commerce\records\Transaction as TransactionRecord;
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
                'restockedQuantities' => $this->json(),
                'isRevisable' => $this->boolean()->defaultValue(false),
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
        // CLI feedback
        echo "    > creating missing refund records ...";
        $time = microtime(true);

        // fetch all existing refund transactions (ordered by dateCreated) 
        $transactions = $this->fetchAllRefundTransactions();

        // fetch all existing refund records
        $refunds = RefundRecord::find()->all();

        // create missing Refund records for each refund transaction
        $view = Craft::$app->getView();
        $refTemplate = OrderRefunds::$plugin->getSettings()->refundReferenceTemplate;

        foreach ($transactions as $transaction)
        {
            // get Refund model and record for order's refund transaction
            $config = [ 'transactionId' => (int)$transaction->id ];
            $record = ArrayHelper::firstWhere($refunds, $config);
            $model = new Refund($config);

            // transfer record attributes to model or vice-versa
            if ($record) {
                $model->setAttributes($record->getAttributes(), false);
            } else {
                // refunds created upon install are revisable
                $model->isRevisable = true;

                $record = new RefundRecord();
                $record->setAttributes($model->getAttributes(), false);
            }

            // give each refund record a reference
            if (empty($record->reference)) {
                $record->reference = $view->renderObjectTemplate($refTemplate, $model);
            }

            // save record
            $record->save();
        }

        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    /**
     * Fetches all existing Commerce refund transactions from the database
     * and orders them by date created
     * 
     * @return \craft\commerce\models\Transaction[]
     */

    protected function fetchAllRefundTransactions(): array
    {
        $query = (new Query())
            ->select([
                'id',
                'orderId',
                'hash',
                'gatewayId',
                'type',
                'status',
                'amount',
                'currency',
                'paymentAmount',
                'paymentCurrency',
                'paymentRate',
                'reference',
                'message',
                'note',
                'code',
                'response',
                'userId',
                'parentId',
                'dateCreated',
                'dateUpdated',
            ])
            ->from([ CommerceTable::TRANSACTIONS ])
            ->where([
                'type' => TransactionRecord::TYPE_REFUND,
                'status' => TransactionRecord::STATUS_SUCCESS,
            ])
            ->orderBy([ 'dateCreated' => SORT_ASC ]);

        $transactions = [];

        foreach ($query->all() as $row)
        {
            $transaction = new Transaction($row);
            $transactions[] = $transaction;
        }

        echo (" ". count($transactions) . " refund transactions found ...");

        return $transactions;
    }
}
