<?php
namespace MercadoPago\Core\Helper;

use Magento\Framework\View\LayoutFactory;


/**
 * Class Data
 *
 * @package MercadoPago\Core\Helper
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Data
    extends \Magento\Payment\Helper\Data
{

    /**
     *path to access token config
     */
    const XML_PATH_ACCESS_TOKEN = 'payment/mercadopago_custom/access_token';
    /**
     *path to public config
     */
    const XML_PATH_PUBLIC_KEY = 'payment/mercadopago_custom/public_key';
    /**
     *path to client id config
     */
    const XML_PATH_CLIENT_ID = 'payment/mercadopago_standard/client_id';
    /**
     *path to client secret config
     */
    const XML_PATH_CLIENT_SECRET = 'payment/mercadopago_standard/client_secret';

    /**
     *api platform openplatform
     */
    const PLATFORM_OPENPLATFORM = 'openplatform';
    /**
     *api platform stdplatform
     */
    const PLATFORM_STD = 'std';
    /**
     *type
     */
    const TYPE = 'magento';

    /**
     * @var \MercadoPago\Core\Helper\Message\MessageInterface
     */
    protected $_messageInterface;

    /**
     * MercadoPago Logging instance
     *
     * @var \MercadoPago\Core\Logger\Logger
     */
    protected $_mpLogger;

    /**
     * @var \Magento\Framework\Setup\ModuleContextInterface
     */
    protected $_moduleContext;

    /**
     * @var bool flag indicates when status was updated by notifications.
     */
    protected $_statusUpdatedFlag = false;

    /**
     * Data constructor.
     *
     * @param Message\MessageInterface                        $messageInterface
     * @param \Magento\Framework\App\Helper\Context           $context
     * @param LayoutFactory                                   $layoutFactory
     * @param \Magento\Payment\Model\Method\Factory           $paymentMethodFactory
     * @param \Magento\Store\Model\App\Emulation              $appEmulation
     * @param \Magento\Payment\Model\Config                   $paymentConfig
     * @param \Magento\Framework\App\Config\Initial           $initialConfig
     * @param \Magento\Framework\Setup\ModuleContextInterface $moduleContext
     * @param \MercadoPago\Core\Logger\Logger                 $logger
     */
    public function __construct(
        \MercadoPago\Core\Helper\Message\MessageInterface $messageInterface,
        \Magento\Framework\App\Helper\Context $context,
        LayoutFactory $layoutFactory,
        \Magento\Payment\Model\Method\Factory $paymentMethodFactory,
        \Magento\Store\Model\App\Emulation $appEmulation,
        \Magento\Payment\Model\Config $paymentConfig,
        \Magento\Framework\App\Config\Initial $initialConfig,
        \Magento\Framework\Setup\ModuleContextInterface $moduleContext,
        \MercadoPago\Core\Logger\Logger $logger
    )
    {
        parent::__construct($context, $layoutFactory, $paymentMethodFactory, $appEmulation, $paymentConfig, $initialConfig);
        $this->_messageInterface = $messageInterface;
        $this->_mpLogger = $logger;
        $this->_moduleContext = $moduleContext;
    }

    /**
     * Log custom message using MercadoPago logger instance
     *
     * @param        $message
     * @param string $name
     * @param null   $array
     */
    public function log($message, $name = "mercadopago", $array = null)
    {
        //load admin configuration value, default is true
        $actionLog = $this->scopeConfig->getValue('payment/mercadopago/logs', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if (!$actionLog) {
            return;
        }
        //if extra data is provided, it's encoded for better visualization
        if (!is_null($array)) {
            $message .= " - " . json_encode($array);
        }

        //set log
        $this->_mpLogger->setName($name);
        $this->_mpLogger->debug($message);
    }

    /**
     * Return MercadoPago Api instance given AccessToken or ClientId and Secret
     *
     * @return \MercadoPago_Core_Lib_Api
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getApiInstance()
    {
        $params = func_num_args();
        if ($params > 2 || $params < 1) {
            throw new \Magento\Framework\Exception\LocalizedException("Invalid arguments. Use CLIENT_ID and CLIENT SECRET, or ACCESS_TOKEN");
        }
        if ($params == 1) {
            $api = new \MercadoPago_Core_Lib_Api(func_get_arg(0));
            $api->set_platform(self::PLATFORM_OPENPLATFORM);
        } else {
            $api = new \MercadoPago_Core_Lib_Api(func_get_arg(0), func_get_arg(1));
            $api->set_platform(self::PLATFORM_STD);
        }
        if ($this->scopeConfig->getValue('payment/mercadopago_standard/sandbox_mode')) {
            $api->sandbox_mode(true);
        }

        $api->set_type(self::TYPE);

        //$api->set_so((string)$this->_moduleContext->getVersion()); //TODO tracking

        return $api;

    }

    /**
     * AccessToken valid?
     *
     * @param $accessToken
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isValidAccessToken($accessToken)
    {
        $mp = $this->getApiInstance($accessToken);
        $response = $mp->get("/v1/payment_methods");
        if ($response['status'] == 401 || $response['status'] == 400) {
            return false;
        }

        return true;
    }

    /**
     * ClientId and Secret valid?
     *
     * @param $clientId
     * @param $clientSecret
     *
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function isValidClientCredentials($clientId, $clientSecret)
    {
        $mp = $this->getApiInstance($clientId, $clientSecret);
        try {
            $mp->get_access_token();
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Return the access token proved by api
     *
     * @return mixed
     * @throws \Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getAccessToken()
    {
        $clientId = $this->scopeConfig->getValue(self::XML_PATH_CLIENT_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $clientSecret = $this->scopeConfig->getValue(self::XML_PATH_CLIENT_SECRET, \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        try {
            $accessToken = $this->getApiInstance($clientId, $clientSecret)->get_access_token();
        } catch (\Exception $e) {
            $accessToken = false;
        }

        return $accessToken;
    }



    /**
     * Return raw message for payment detail
     *
     * @param $status
     * @param $payment
     *
     * @return \Magento\Framework\Phrase|string
     */
    public function getMessage($status, $payment)
    {
        $rawMessage = __($this->_messageInterface->getMessage($status));
        $rawMessage .= __('<br/> Payment id: %1', $payment['id']);
        $rawMessage .= __('<br/> Status: %1', $payment['status']);
        $rawMessage .= __('<br/> Status Detail: %1', $payment['status_detail']);

        return $rawMessage;
    }

}
