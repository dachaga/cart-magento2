<?php
namespace MercadoPago\Core\Helper;

use Magento\Framework\View\LayoutFactory;


/**
 * Class Data
 *
 * @package MercadoPago\Core\Helper
 */
class Order
    extends \Magento\Payment\Helper\Data
{

    /**
     * @var \Magento\Sales\Model\ResourceModel\Status\Collection
     */
    protected $_statusFactory;

    /**
     * @var bool flag indicates when status was updated by notifications.
     */
    protected $_statusUpdatedFlag = false;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * Order constructor.
     *
     * @param LayoutFactory                                        $layoutFactory
     * @param \Magento\Framework\App\Helper\Context                $context
     * @param \Magento\Payment\Model\Method\Factory                $paymentMethodFactory
     * @param \Magento\Store\Model\App\Emulation                   $appEmulation
     * @param \Magento\Payment\Model\Config                        $paymentConfig
     * @param \Magento\Framework\App\Config\Initial                $initialConfig
     * @param \Magento\Sales\Model\ResourceModel\Status\Collection $statusFactory
     * @param \Magento\Sales\Model\OrderFactory                    $orderFactory
     */
    public function __construct(
        LayoutFactory $layoutFactory,
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Payment\Model\Method\Factory $paymentMethodFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\App\Config\Initial $initialConfig,
        \Magento\Sales\Model\ResourceModel\Status\Collection $statusFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory
    )
    {
        parent::__construct($context, $layoutFactory, $paymentMethodFactory, $appEmulation, $paymentConfig, $initialConfig);
        $this->_statusFactory = $statusFactory;
        $this->_orderFactory = $orderFactory;
    }

    /**
     * @return bool return updated flag
     */
    public function isStatusUpdated()
    {
        return $this->_statusUpdatedFlag;
    }

    /**
     * Set flag status updated
     *
     * @param $notificationData
     */
    public function setStatusUpdated($notificationData)
    {
        $order = $this->_orderFactory->create()->loadByIncrementId($notificationData["external_reference"]);
        $status = $notificationData['status'];
        $currentStatus = $order->getPayment()->getAdditionalInformation('status');
        if ($status == $currentStatus) {
            $this->_statusUpdatedFlag = true;
        }
    }

    /**
     * Return order status mapping based on current configuration
     *
     * @param $status
     *
     * @return mixed
     */
    public function getStatusOrder($status)
    {
        switch ($status) {
            case 'approved': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_approved', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'refunded': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_refunded', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'in_mediation': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_in_mediation', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'cancelled': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_cancelled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'rejected': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_rejected', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            case 'chargeback': {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_chargeback', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
                break;
            }
            default: {
                $status = $this->scopeConfig->getValue('payment/mercadopago/order_status_in_process', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            }
        }

        return $status;
    }

    /**
     * Get the assigned state of an order status
     *
     * @param string $status
     */
    public function _getAssignedState($status)
    {
        $collection = $this->_statusFactory
            ->joinStates()
            ->addFieldToFilter('main_table.status', $status);

        $collectionItems = $collection->getItems();

        return array_pop($collectionItems)->getState();
    }

    /**
     * Calculate and set order MercadoPago specific subtotals based on data values
     *
     * @param $data
     * @param $order
     */
    public function setOrderSubtotals($data, $order)
    {
        if (isset($data['total_paid_amount'])) {
            $balance = $this->_getMultiCardValue($data['total_paid_amount']);
        } else {
            $balance = $data['transaction_details']['total_paid_amount'];
        }

        if (isset($data['shipping_cost'])) {
            $shippingCost = $this->_getMultiCardValue($data['shipping_cost']);
            $order->setBaseShippingAmount($shippingCost);
            $order->setShippingAmount($shippingCost);
        } else {
            $shippingCost = 0;
        }

        $order->setGrandTotal($balance);
        $order->setBaseGrandTotal($balance);
        $order->setBaseShippingAmount($shippingCost);
        $order->setShippingAmount($shippingCost);

        $couponAmount = $this->_getMultiCardValue($data['coupon_amount']);
        $transactionAmount = $this->_getMultiCardValue($data['transaction_amount']);
        if ($couponAmount) {
            $order->setDiscountCouponAmount($couponAmount * -1);
            $order->setBaseDiscountCouponAmount($couponAmount * -1);
            $balance = $balance - ($transactionAmount - $couponAmount + $shippingCost);
        } else {
            $balance = $balance - $transactionAmount - $shippingCost;
        }

        if (\Zend_Locale_Math::round($balance, 4) > 0) {
            $order->setFinanceCostAmount($balance);
            $order->setBaseFinanceCostAmount($balance);
        }

        $order->save();
    }

    /**
     * Modify payment array adding specific fields
     *
     * @param $payment
     *
     * @return mixed
     */
    public function setPayerInfo(&$payment)
    {
        $payment["trunc_card"] = "xxxx xxxx xxxx " . $payment['card']["last_four_digits"];
        $payment["cardholder_name"] = $payment['card']["cardholder"]["name"];
        $payment['payer_first_name'] = $payment['payer']['first_name'];
        $payment['payer_last_name'] = $payment['payer']['last_name'];
        $payment['payer_email'] = $payment['payer']['email'];

        return $payment;
    }

    /**
     * Return sum of fields separated with |
     *
     * @param $fullValue
     *
     * @return int
     */
    protected function _getMultiCardValue($fullValue)
    {
        if (!$fullValue) {
            return 0;
        }
        $finalValue = 0;
        $values = explode('|', $fullValue);
        foreach ($values as $value) {
            $value = (float)str_replace(' ', '', $value);
            $finalValue = $finalValue + $value;
        }

        return $finalValue;
    }

}
