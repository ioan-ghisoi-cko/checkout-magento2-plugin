<?php

namespace CheckoutCom\Magento2\Controller\Webhook;

use Magento\Sales\Model\Order\Payment\Transaction;

class Callback extends \Magento\Framework\App\Action\Action {
    /**
     * @var array
     */
    protected static $transactionMapper = [
        'payment_approved' => Transaction::TYPE_AUTH,
        'payment_captured' => Transaction::TYPE_CAPTURE,
        'payment_refunded' => Transaction::TYPE_REFUND,
        'payment_voided' => Transaction::TYPE_VOID
    ];

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var apiHandler
     */
    protected $apiHandler;

    /**
     * @var OrderHandlerService
     */
    protected $orderHandler;

    /**
     * @var QuoteHandlerService
     */
    protected $quoteHandler;

    /**
     * @var TransactionHandlerService
     */
    protected $transactionHandler;

    /**
     * @var Config
     */
    protected $config;

	/**
     * Callback constructor
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \CheckoutCom\Magento2\Model\Service\apiHandlerService $apiHandler,
        \CheckoutCom\Magento2\Model\Service\OrderHandlerService $orderHandler,
        \CheckoutCom\Magento2\Model\Service\QuoteHandlerService $quoteHandler,
        \CheckoutCom\Magento2\Model\Service\TransactionHandlerService $transactionHandler,
        \CheckoutCom\Magento2\Gateway\Config\Config $config
    )
    {
        parent::__construct($context);

        $this->jsonFactory = $jsonFactory;
        $this->orderRepository = $orderRepository;
        $this->apiHandler = $apiHandler;
        $this->orderHandler = $orderHandler;
        $this->quoteHandler = $quoteHandler;
        $this->transactionHandler = $transactionHandler;
        $this->config = $config;
    }

    /**
     * Handles the controller method.
     */
    public function execute() {
        try {
            if ($this->config->isValidAuth()) {
                // Get the post data
                $payload = json_decode($this->getRequest()->getContent());
                if (isset($payload->data->id)) {
                    // Get the payment details
                    $response = $this->apiHandler->getPaymentDetails($payload->data->id);

                    $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/payload.log');
                    $logger = new \Zend\Log\Logger();
                    $logger->addWriter($writer);
                    $logger->info(print_r($payload, 1));

                    if ($this->apiHandler->isValidResponse($response)) {
                        // Process the order
                        $order = $this->orderHandler
                            ->setMethodId($response->metadata['methodId'])
                            ->handleOrder(
                                $response->reference,
                                (array) $response,
                                true
                            );

                        if ($this->orderHandler->isOrder($order)) {
                            // Handle the transaction
                            $this->transactionHandler->createTransaction(
                                $order,
                                static::$transactionMapper[$payload->type],
                                (array) $response
                            );

                            // Save the order
                            $order = $this->orderRepository->save($order);
                        }
                    }
                }
            }
        } catch (\Exception $e) {

        }     

        exit();
        
        return $this->jsonFactory->create()->setData([]);

    }
}