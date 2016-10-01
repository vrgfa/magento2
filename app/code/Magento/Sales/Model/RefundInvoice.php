<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Sales\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\CreditmemoRepositoryInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\RefundInvoiceInterface;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Magento\Sales\Model\Order\Creditmemo\CreditmemoValidatorInterface;
use Magento\Sales\Model\Order\Creditmemo\ItemCreationValidatorInterface;
use Magento\Sales\Model\Order\Creditmemo\NotifierInterface;
use Magento\Sales\Model\Order\Creditmemo\Item\Validation\CreationQuantityValidator;
use Magento\Sales\Model\Order\Creditmemo\Validation\QuantityValidator;
use Magento\Sales\Model\Order\Creditmemo\Validation\TotalsValidator;
use Magento\Sales\Model\Order\CreditmemoDocumentFactory;
use Magento\Sales\Model\Order\Invoice\InvoiceValidatorInterface;
use Magento\Sales\Model\Order\OrderStateResolverInterface;
use Magento\Sales\Model\Order\OrderValidatorInterface;
use Magento\Sales\Model\Order\PaymentAdapterInterface;
use Magento\Sales\Model\Order\Validation\CanRefund;
use Psr\Log\LoggerInterface;

/**
 * Class RefundInvoice
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class RefundInvoice implements RefundInvoiceInterface
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var OrderStateResolverInterface
     */
    private $orderStateResolver;

    /**
     * @var OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var InvoiceRepositoryInterface
     */
    private $invoiceRepository;

    /**
     * @var OrderValidatorInterface
     */
    private $orderValidator;

    /**
     * @var InvoiceValidatorInterface
     */
    private $invoiceValidator;

    /**
     * @var CreditmemoValidatorInterface
     */
    private $creditmemoValidator;

    /**
     * @var ItemCreationValidatorInterface
     */
    private $itemCreationValidator;

    /**
     * @var CreditmemoRepositoryInterface
     */
    private $creditmemoRepository;

    /**
     * @var Order\PaymentAdapterInterface
     */
    private $paymentAdapter;

    /**
     * @var CreditmemoDocumentFactory
     */
    private $creditmemoDocumentFactory;

    /**
     * @var Order\Creditmemo\NotifierInterface
     */
    private $notifier;

    /**
     * @var OrderConfig
     */
    private $config;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * RefundInvoice constructor.
     *
     * @param ResourceConnection $resourceConnection
     * @param OrderStateResolverInterface $orderStateResolver
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderValidatorInterface $orderValidator
     * @param InvoiceValidatorInterface $invoiceValidator
     * @param CreditmemoValidatorInterface $creditmemoValidator
     * @param Order\Creditmemo\ItemCreationValidatorInterface $itemCreationValidator
     * @param CreditmemoRepositoryInterface $creditmemoRepository
     * @param PaymentAdapterInterface $paymentAdapter
     * @param CreditmemoDocumentFactory $creditmemoDocumentFactory
     * @param NotifierInterface $notifier
     * @param OrderConfig $config
     * @param LoggerInterface $logger
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        OrderStateResolverInterface $orderStateResolver,
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderValidatorInterface $orderValidator,
        InvoiceValidatorInterface $invoiceValidator,
        CreditmemoValidatorInterface $creditmemoValidator,
        ItemCreationValidatorInterface $itemCreationValidator,
        CreditmemoRepositoryInterface $creditmemoRepository,
        PaymentAdapterInterface $paymentAdapter,
        CreditmemoDocumentFactory $creditmemoDocumentFactory,
        NotifierInterface $notifier,
        OrderConfig $config,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->orderStateResolver = $orderStateResolver;
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->orderValidator = $orderValidator;
        $this->creditmemoValidator = $creditmemoValidator;
        $this->itemCreationValidator = $itemCreationValidator;
        $this->creditmemoRepository = $creditmemoRepository;
        $this->paymentAdapter = $paymentAdapter;
        $this->creditmemoDocumentFactory = $creditmemoDocumentFactory;
        $this->notifier = $notifier;
        $this->config = $config;
        $this->logger = $logger;
        $this->invoiceValidator = $invoiceValidator;
    }

    /**
     * @inheritdoc
     */
    public function execute(
        $invoiceId,
        array $items = [],
        $isOnline = false,
        $notify = false,
        $appendComment = false,
        \Magento\Sales\Api\Data\CreditmemoCommentCreationInterface $comment = null,
        \Magento\Sales\Api\Data\CreditmemoCreationArgumentsInterface $arguments = null
    ) {
        $connection = $this->resourceConnection->getConnection('sales');
        $invoice = $this->invoiceRepository->get($invoiceId);
        $order = $this->orderRepository->get($invoice->getOrderId());
        $creditmemo = $this->creditmemoDocumentFactory->createFromInvoice(
            $invoice,
            $items,
            $comment,
            ($appendComment && $notify),
            $arguments
        );
        $orderValidationResult = $this->orderValidator->validate(
            $order,
            [
                CanRefund::class
            ]
        );
        $invoiceValidationResult = $this->invoiceValidator->validate(
            $invoice,
            [
                \Magento\Sales\Model\Order\Invoice\Validation\CanRefund::class
            ]
        );
        $creditmemoValidationResult = $this->creditmemoValidator->validate(
            $creditmemo,
            [
                QuantityValidator::class,
                TotalsValidator::class
            ]
        );
        $itemsValidation = [];
        foreach ($items as $item) {
            $itemsValidation = array_merge(
                $itemsValidation,
                $this->itemCreationValidator->validate(
                    $item,
                    [CreationQuantityValidator::class],
                    $order
                )
            );
        }
        $validationMessages = array_merge(
            $orderValidationResult,
            $invoiceValidationResult,
            $creditmemoValidationResult,
            $itemsValidation
        );
        if (!empty($validationMessages )) {
            throw new \Magento\Sales\Exception\DocumentValidationException(
                __("Creditmemo Document Validation Error(s):\n" . implode("\n", $validationMessages))
            );
        }
        $connection->beginTransaction();
        try {
            $creditmemo->setState(\Magento\Sales\Model\Order\Creditmemo::STATE_REFUNDED);
            $order->setCustomerNoteNotify($notify);
            $order = $this->paymentAdapter->refund($creditmemo, $order, $isOnline);
            $order->setState(
                $this->orderStateResolver->getStateForOrder($order, [])
            );
            $order->setStatus($this->config->getStateDefaultStatus($order->getState()));
            if (!$isOnline) {
                $invoice->setIsUsedForRefund(true);
                $invoice->setBaseTotalRefunded(
                    $invoice->getBaseTotalRefunded() + $creditmemo->getBaseGrandTotal()
                );
            }
            $this->invoiceRepository->save($invoice);
            $order = $this->orderRepository->save($order);
            $creditmemo = $this->creditmemoRepository->save($creditmemo);
            $connection->commit();
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $connection->rollBack();
            throw new \Magento\Sales\Exception\CouldNotRefundException(
                __('Could not save a Creditmemo, see error log for details')
            );
        }
        if ($notify) {
            if (!$appendComment) {
                $comment = null;
            }
            $this->notifier->notify($order, $creditmemo, $comment);
        }

        return $creditmemo->getEntityId();
    }
}
