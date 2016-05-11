<?php
namespace MercadoPago\Core\Controller\Api;


/**
 * Class Coupon
 *
 * @package Mercadopago\Core\Controller\Notifications
 */
class Subtotals
    extends \Magento\Framework\App\Action\Action

{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * Quote repository.
     *
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;


    /**
     * Coupon constructor.
     *
     * @param \Magento\Framework\App\Action\Context      $context
     * @param \Magento\Checkout\Model\Session            $checkoutSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Magento\Framework\Registry $registry
    )
    {
        parent::__construct($context);
        $this->_checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * Fetch coupon info
     *
     * Controller Action
     */
    public function execute()
    {
        $quote = $this->_checkoutSession->getQuote();
        $this->quoteRepository->save($quote->collectTotals());
        return;
    }

}