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

namespace yoannisj\orderrefunds\controllers;

use yii\base\InvalidArgumentException;
use yii\web\NotFoundHttpException;

use Craft;
use craft\web\Controller;
use craft\web\Request;
use craft\web\Response;

use craft\commerce\errors\RefundException;

use yoannisj\orderrefunds\OrderRefunds;
use yoannisj\orderrefunds\models\Refund;
use yoannisj\orderrefunds\helpers\RefundHelper;

/**
 * Controller implementing web actions for Refunds
 * 
 * @since 0.1.0
 */

class RefundsController extends Controller
{
    // =Properties
    // =========================================================================

    /**
     * @inheritdoc
     */

    public $allowAnonymous = false;

    // =Public Methods
    // =========================================================================

    /**
     * Calculates a refund without saving it
     * 
     * @param array $params
     * 
     * @return Response
     * 
     * @throws NotFoundHttpException If params contain a refund id but no corresponding refund exists
     */

    public function actionCalculate( array $params = null ): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-refundPayment');

        $request = Craft::$app->getRequest();
        $params = $params ?? $request->getBodyParams();

        $refund = null;

        try
        {
            $refund = RefundHelper::buildRefundFromParams($params);
            $refund->scenario = Refund::SCENARIO_CALCULATE;
    
            $isValid = $refund->validate();
            $validationErrors = $refund->getErrors();
    
            return $this->buildSuccessResponse($request, [
                'params' => $params,
                'success' => true,
                'successMessage' => null,
                'refund' => $refund,
                'isValid' => $isValid,
                'validationErrors' => $validationErrors,
            ]);
        }

        catch (\Throwable $exception)
        {
            $error = $exception->getMessage();

            Craft::error($error, 'order-refunds');
            Craft::$app->getErrorHandler()->logException($exception);

            return $this->buildErrorResponse($request, $error, [
                'refund' => $refund,
            ]);
        }
    }

    /**
     * Saves Refund record based on given config/params
     *
     * @param array $params
     *  
     * @return Response
     */

    public function actionSave( array $params = null ): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('commerce-refundPayment');

        $request = Craft::$app->getRequest();
        $params = $params ?? $request->getBodyParams();

        $refund = null;

        try
        {
            $refund = RefundHelper::buildRefundFromParams($params);
            $success = OrderRefunds::$plugin->getRefunds()->saveRefund($refund);
            $isValid = $refund->hasErrors();

            if (!$success)
            {
                $error = Craft::t('order-refunds', "Could not save refund");
                if (!$isValid)
                {
                    $error = Craft::t('order-refunds', "Refund is not valid: {error}", [
                        'error' => $refund->getFirstError(),
                    ]);
                }

                return $this->buildErrorResponse($request, $error, [
                    'params' => $params,
                    'refund' => $refund,
                ]);
            }

            return $this->buildSuccessResponse($request, [
                'params' => $params,
                'success' => $success,
                'successMessage' => Craft::t('order-refunds', "Refund saved"),
                'refund' => $refund,
                'isValid' => $isValid,
                'errors' => $refund->getErrors(),
            ]);
        }

        catch (\Throwable $exception)
        {
            Craft::error($exception->getMessage(), 'order-refunds');
            Craft::$app->getErrorHandler()->logException($exception);

            $error = Craft::t('order-refunds', "Could not save refund");

            if ($exception instanceof RefundException) {
                $error = $exception->getMessage();
            }

            return $this->buildErrorResponse($request, $error, [
                'params' => $params,
                'refund' => $refund,
            ]);
        }
    }

    // =Protected Methods
    // =========================================================================

    /**
     * @param Request $request
     * @param array $data
     * 
     * @return Response
     */

    protected function buildSuccessResponse( Request $request, array $data = [] ): Response
    {
        // add request action to returned data
        if (!isset($data['action']))
        {
            $segments = $request->getActionSegments();
            $data['action'] = ($segments ? implode('/', $segments) : null);
        }

        // optionally return data as Json
        if ($request->getAcceptsJson()) {
            return $this->asJson($data);
        }

        $this->setSuccessFlash($data['successMessage'] ?? null);

        // set response data and optionally redirect
        // Craft::$app->getResponse()->data = $data;

        // pass in data to redirect url object template
        return $this->redirectToPostedUrl($data);
    }

    /**
     * @param Request $request
     * @param string $error
     * 
     * @return Response
     */

    protected function buildErrorResponse( Request $request, string $error, array $data = [] ): Response
    {
        if ($request->getAcceptsJson()) {
            return $this->asErrorJson($error);
        }

        $this->setFailFlash($error);

        // pass in data to redirect url object template
        return $this->redirectToPostedUrl($data);
    }
}