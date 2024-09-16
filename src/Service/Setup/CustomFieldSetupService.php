<?php declare(strict_types=1);

namespace Wexo\AltaPay\Service\Setup;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Wexo\AltaPay\Service\PaymentService;
use Wexo\AltaPay\WexoAltaPay;

class CustomFieldSetupService
{
    /** @var array<string,int> */
    private array $positionMap = [];

    public function __construct(
        protected EntityRepository $customFieldSetRepository,
        protected EntityRepository $customFieldRepository
    ) {
    }

    public function createFields(Context $context): void
    {
        $this->createOrderCustomField($context);
        $this->createPaymentMethodCustomField($context);
    }

    public function deleteFields(Context $context): void
    {
        /** @var array<string> $setIds */
        $setIds = $this->customFieldSetRepository->searchIds(
            (new Criteria())
                ->addFilter(
                    new EqualsAnyFilter('name', [
                        WexoAltaPay::ALTAPAY_FIELD_SET_NAME,
                        WexoAltaPay::ALTAPAY_PAYMENT_METHOD_FIELD_SET_NAME,
                    ])
                ),
            $context
        )->getIds();

        if ($setIds) {
            $this->customFieldSetRepository->delete(
                array_map(fn(string $id) => ['id' => $id], $setIds),
                $context
            );
        }
    }

    private function createOrderCustomField(Context $context): void
    {
        $fieldSetId = $this->customFieldSetRepository->searchIds(
            (new Criteria)->addFilter(new EqualsFilter('name', WexoAltaPay::ALTAPAY_FIELD_SET_NAME)),
            $context
        )->firstId();
        if (!$fieldSetId) {
            $this->customFieldSetRepository->create([
                [
                    'id' => ($fieldSetId = Uuid::randomHex()),
                    'name' => WexoAltaPay::ALTAPAY_FIELD_SET_NAME,
                    'config' => [
                        'label' => [
                            'da-DK' => 'AltaPay Data',
                            'en-GB' => 'AltaPay Data',
                            'de-DE' => 'AltaPay-Daten'
                        ]
                    ],
                    'relations' => [
                        [
                            'entityName' => 'order'
                        ],
                    ],
                    'position' => 1,
                ],
            ], $context);
        }

        $this->addCustomField(
            name: PaymentService::ALTAPAY_PAYMENT_ID_CUSTOM_FIELD,
            type: CustomFieldTypes::TEXT,
            config: [
                'label' => [
                    'de-DE' => 'AltaPay-Zahlungs-ID',
                    'en-GB' => 'AltaPay Payment ID',
                    'da-DK' => 'AltaPay Betalings-ID',
                ],
                'customFieldPosition' => 1
            ],
            customFieldSetId: $fieldSetId,
            context: $context
        );

        $this->addCustomField(
            name: PaymentService::ALTAPAY_TRANSACTION_ID_CUSTOM_FIELD,
            type: CustomFieldTypes::TEXT,
            config: [
                'label' => [
                    'de-DE' => 'AltaPay-Transaktions-ID',
                    'en-GB' => 'AltaPay Transaction ID',
                    'da-DK' => 'Altapay Transaktions-ID',
                ],
                'customFieldPosition' => 2,
            ],
            customFieldSetId: $fieldSetId,
            context: $context
        );
    }

    private function createPaymentMethodCustomField(Context $context): void
    {
        $fieldSetId = $this->customFieldSetRepository->searchIds(
            (new Criteria)->addFilter(new EqualsFilter('name', WexoAltaPay::ALTAPAY_PAYMENT_METHOD_FIELD_SET_NAME)),
            $context
        )->firstId();
        if (!$fieldSetId) {
            $this->customFieldSetRepository->create([
                [
                    'id' => ($fieldSetId = Uuid::randomHex()),
                    'name' => WexoAltaPay::ALTAPAY_PAYMENT_METHOD_FIELD_SET_NAME,
                    'config' => [
                        'label' => [
                            'da-DK' => 'AltaPay Data',
                            'en-GB' => 'AltaPay Data',
                            'de-DE' => 'AltaPay-Daten'
                        ]
                    ],
                    'relations' => [
                        [
                            'entityName' => 'payment_method'
                        ],
                    ],
                    'position' => 1,
                ],
            ], $context);
        }

        $this->addCustomField(
            name: PaymentService::ALTAPAY_TERMINAL_ID_CUSTOM_FIELD,
            type: CustomFieldTypes::TEXT,
            config: [
                'label' => [
                    'de-DE' => 'AltaPay Terminal-ID',
                    'en-GB' => 'AltaPay Terminal ID',
                    'da-DK' => 'AltaPay Terminal-ID',
                ],
                'customFieldPosition' => 1
            ],
            customFieldSetId: $fieldSetId,
            context: $context
        );

        $this->addCustomField(
            name: PaymentService::ALTAPAY_AUTO_CAPTURE_CUSTOM_FIELD,
            type: CustomFieldTypes::SWITCH,
            config: [
                'label' => [
                    'de-DE' => 'Automatische Erfassung',
                    'en-GB' => 'Auto Capture',
                    'da-DK' => 'Automatisk Optagelse',
                ],
                'customFieldPosition' => 2,
                'defaultValue' => false,
            ],
            customFieldSetId: $fieldSetId,
            context: $context
        );

    }

    private function addCustomField(
        string $name,
        string $type,
        array $config,
        string $customFieldSetId,
        Context $context,
        bool $allowCustomerWrite = false
    ): void {
        $this->positionMap[$customFieldSetId] = ($this->positionMap[$customFieldSetId] ?? 0) + 1;
        $config['customFieldPosition'] = $this->positionMap[$customFieldSetId];

        /** @var CustomFieldEntity|null $customField */
        $customField = $this->customFieldRepository->search(
            (new Criteria)->addFilter(new EqualsFilter('name', $name)),
            $context
        )->first();
        if ($customField) {
            /**
             * Do a custom look up on config, if they're equal skip upsert.
             */
            $hasChanges = $customField->getType() !== $type;
            if (!$hasChanges) {
                foreach ($config as $key => $value) {
                    $cfgValue = $customField->getConfig()[$key] ?? null;

                    if (is_array($value)) {
                        $column = array_column($value, 'value');
                        if ($column) {
                            $diff = array_diff(
                                array_column($value, 'value'),
                                array_column((array)$cfgValue, 'value')
                            );
                        } else {
                            $diff = array_diff($value, (array)$cfgValue);
                        }

                        if ($diff) {
                            $hasChanges = true;
                            break;
                        }
                    } elseif ($cfgValue !== $value) {
                        $hasChanges = true;
                        break;
                    }
                }
            }

            if (!$hasChanges) {
                return;
            }
        }

        $this->customFieldRepository->upsert([
            [
                'id' => $customField?->getId() ?: Uuid::randomHex(),
                'customFieldSetId' => $customFieldSetId,
                'name' => $name,
                'type' => $type,
                'config' => $config,
                'allowCustomerWrite' => $allowCustomerWrite,
            ],
        ], $context);
    }
}
