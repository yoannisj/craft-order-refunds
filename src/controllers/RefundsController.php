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

use yoannisj\orderrefunds\OrderRefunds;
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

        $request = Craft::$app->getRequest();
        $params = $params ?? $request->getBodyParams();

        $refund = $this->createRefundForParams($params);
        $isValid = $refund->validate();
        $validationErrors = $refund->getErrors();

        return $this->createResponse($request, [
            'params' => $params,
            'refund' => $refund,
            'success' => true,
            'isValid' => $isValid,
            'validationErrors' => $validationErrors,
        ]);
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

        $request = Craft::$app->getRequest();
        $params = $params ?? $request->getBodyParams();

        $refund = $this->createRefundForParams($params);
        $success = OrderRefunds::$plugin->getRefunds()->saveRefund($refund);
        $isValid = $refund->hasErrors();
        $validationErrors = $refund->getErrors();

        return $this->createResponse($request, [
            'params' => $params,
            'refund' => $refund,
            'success' => $success,
            'isValid' => $isValid,
            'validationErrors' => $validationErrors,
        ]);
    }

    // =Protected Methods
    // =========================================================================

    /**
     * Creates a Refund model based on given parameters
     * 
     * @param array $params
     * 
     * @return Refund|null
     * 
     * @throws NotFoundHttpException If params contain a refund id but no corresponding refund exists
     */

    protected function createRefundForParams( array $params )
    {
        $refund = null;

        try {
            $refund = RefundHelper::createRefundForParams($params);       
        } catch (InvalidArgumentException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }

        return $refund;
    }

    /**
     * @param Request $request
     * @param array $data
     * 
     * @return Response
     */

    protected function createResponse( Request $request, array $data = [] ): Response
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

        // set response data and optionally redirect
        $response->data = $data;
        // pass in data to redirect url object template
        $this->redirectToPostelUrl($data);
    }
}