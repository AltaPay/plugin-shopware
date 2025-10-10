<?php declare(strict_types=1);

namespace Wexo\AltaPay;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Wexo\AltaPay\Service\PaymentService;
use Wexo\AltaPay\Service\Setup\CustomFieldSetupService;

class WexoAltaPay extends Plugin
{
    public const ALTAPAY_FIELD_SET_NAME = "wexoAltaPay";
    public const ALTAPAY_PAYMENT_METHOD_FIELD_SET_NAME = "wexoAltaPayPaymentMethod";
    public const ALTAPAY_CART_TOKEN = "wexoAltaPayCartToken";
    public const ALTAPAY_PLUGIN_VERSION = '2.0.5';
    public const ALTAPAY_PLUGIN_NAME = 'WexoAltaPay';

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        /** @var EntityRepository $customFieldSetRepository */
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');
        /** @var EntityRepository $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');

        (new CustomFieldSetupService($customFieldSetRepository, $customFieldRepository))
            ->createFields($updateContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        $this->setPaymentMethodIsActive(false, $uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        /** @var EntityRepository $customFieldSetRepository */
         $customFieldSetRepository = $this->container->get('custom_field_set.repository');
         /** @var EntityRepository $customFieldRepository */
         $customFieldRepository = $this->container->get('custom_field.repository');

         (new CustomFieldSetupService($customFieldSetRepository, $customFieldRepository))
             ->createFields($activateContext->getContext());

        $this->setPaymentMethodIsActive(true, $activateContext->getContext());
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->setPaymentMethodIsActive(false, $deactivateContext->getContext());
    }

    private function getCustomFieldSet(Context $context, $name): ?string
    {
        $repository = $this->container->get('custom_field_set.repository');
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('name', $name)
        );
        return $repository->searchIds($criteria, $context)->firstId();
    }

    private function getPaymentMethodId(Context $context): ?string
    {
        $repository = $this->container->get('payment_method.repository');
        $paymentCriteria = (new Criteria())->addFilter(
            new EqualsFilter('handlerIdentifier', PaymentService::class)
        );
        return $repository->searchIds($paymentCriteria, $context)->firstId();
    }

    private function setPaymentMethodIsActive(bool $active, Context $context): void
    {
        $paymentRepository = $this->container->get('payment_method.repository');
        $paymentMethodId = $this->getPaymentMethodId($context);
        if (!$paymentMethodId) {
            return;
        }
        $paymentRepository->update([
            [
                'id' => $paymentMethodId,
                'active' => $active,
            ]
        ], $context);
    }

    /**
     * @param ContainerInterface $container
     * @return string
     */
    public static function getShopwareVersionFromComposer(ContainerInterface $container): string
    {
        // Get the kernel instance
        $kernel = $container->get('kernel');

        // Get the project root directory
        $projectDir = $kernel->getProjectDir();

        // Build the path to the composer.lock file
        $composerLockPath = $projectDir . '/composer.lock';

        if (file_exists($composerLockPath)) {
            $composerLock = json_decode(file_get_contents($composerLockPath), true);

            foreach ($composerLock['packages'] as $package) {
                if ($package['name'] === 'shopware/core' || $package['name'] === 'shopware/platform') {
                    return $package['version'];
                }
            }
        }

        return 'Unknown';
    }

    /**
     * Get the Shopware Shop Name.
     */
    public static function getShopName(SystemConfigService $systemConfigService, string $salesChannelId): string
    {
        return $systemConfigService->get('core.basicInformation.shopName', $salesChannelId) ?? 'Unknown';
    }
}
