<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Vault\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Payment\Api\Data\PaymentMethodInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Model\CustomerTokenManagement;
use Magento\Vault\Model\VaultPaymentInterface;

/**
 * Class ConfigProvider
 * @api
 */
final class TokensConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string
     */
    private static $vaultCode = 'vault';

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TokenUiComponentProviderInterface[]
     */
    private $tokenUiComponentProviders;

    /**
     * @var CustomerTokenManagement
     */
    private $customerTokenManagement;

    /**
     * @var \Magento\Payment\Api\PaymentMethodListInterface
     */
    private $paymentMethodList;

    /**
     * @var \Magento\Payment\Model\Method\InstanceFactory
     */
    private $paymentMethodInstanceFactory;

    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param CustomerTokenManagement $customerTokenManagement
     * @param TokenUiComponentProviderInterface[] $tokenUiComponentProviders
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CustomerTokenManagement $customerTokenManagement,
        array $tokenUiComponentProviders = []
    ) {
        $this->storeManager = $storeManager;
        $this->tokenUiComponentProviders = $tokenUiComponentProviders;
        $this->customerTokenManagement = $customerTokenManagement;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $vaultPayments = [];
        $providers = $this->getComponentProviders();

        if (empty($providers)) {
            return $vaultPayments;
        }

        $tokens = $this->customerTokenManagement->getCustomerSessionTokens();

        foreach ($tokens as $i => $token) {
            $paymentCode = $token->getPaymentMethodCode();
            if (!isset($providers[$paymentCode])) {
                continue;
            }

            $componentProvider = $providers[$paymentCode];
            $component = $componentProvider->getComponentForToken($token);
            $config = $component->getConfig();
            $vaultPaymentCode = !empty($config['code']) ? $config['code'] : $paymentCode;
            $vaultPayments[$vaultPaymentCode . '_' . $i] = [
                'config' => $config,
                'component' => $component->getName()
            ];
        }

        return [
            'payment' => [
                self::$vaultCode => $vaultPayments
            ]
        ];
    }

    /**
     * Get list of available vault ui token providers.
     *
     * @return TokenUiComponentProviderInterface[]
     */
    private function getComponentProviders()
    {
        $providers = [];
        $vaultPaymentMethods = $this->getVaultPaymentMethodList();

        foreach ($vaultPaymentMethods as $method) {
            $providerCode = $method->getProviderCode();
            $componentProvider = $this->getComponentProvider($providerCode);
            if ($componentProvider === null) {
                continue;
            }
            $providers[$providerCode] = $componentProvider;
        }

        return $providers;
    }

    /**
     * @param string $vaultProviderCode
     * @return TokenUiComponentProviderInterface|null
     */
    private function getComponentProvider($vaultProviderCode)
    {
        $componentProvider = isset($this->tokenUiComponentProviders[$vaultProviderCode])
            ? $this->tokenUiComponentProviders[$vaultProviderCode]
            : null;
        return $componentProvider instanceof TokenUiComponentProviderInterface
            ? $componentProvider
            : null;
    }

    /**
     * Get list of active Vault payment methods.
     *
     * @return VaultPaymentInterface[]
     */
    private function getVaultPaymentMethodList()
    {
        $storeId = $this->storeManager->getStore()->getId();

        $paymentMethods = array_map(
            function (PaymentMethodInterface $paymentMethod) {
                return $this->getPaymentMethodInstanceFactory()->create($paymentMethod);
            },
            $this->getPaymentMethodList()->getActiveList($storeId)
        );

        $availableMethods = array_filter(
            $paymentMethods,
            function (\Magento\Payment\Model\MethodInterface $methodInstance) {
                return $methodInstance instanceof VaultPaymentInterface;
            }
        );

        return $availableMethods;
    }

    /**
     * Get payment method list.
     *
     * @return \Magento\Payment\Api\PaymentMethodListInterface
     * @deprecated
     */
    private function getPaymentMethodList()
    {
        if ($this->paymentMethodList === null) {
            $this->paymentMethodList = ObjectManager::getInstance()->get(
                \Magento\Payment\Api\PaymentMethodListInterface::class
            );
        }
        return $this->paymentMethodList;
    }

    /**
     * Get payment method instance factory.
     *
     * @return \Magento\Payment\Model\Method\InstanceFactory
     * @deprecated
     */
    private function getPaymentMethodInstanceFactory()
    {
        if ($this->paymentMethodInstanceFactory === null) {
            $this->paymentMethodInstanceFactory = ObjectManager::getInstance()->get(
                \Magento\Payment\Model\Method\InstanceFactory::class
            );
        }
        return $this->paymentMethodInstanceFactory;
    }
}
