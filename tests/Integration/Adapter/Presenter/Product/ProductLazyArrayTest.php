<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace Tests\Integration\Adapter\Presenter\Product;

use Language;
use Link;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShop\PrestaShop\Adapter\HookManager;
use PrestaShop\PrestaShop\Adapter\Image\ImageRetriever;
use PrestaShop\PrestaShop\Adapter\Presenter\Product\ProductLazyArray;
use PrestaShop\PrestaShop\Adapter\Product\PriceFormatter;
use PrestaShop\PrestaShop\Adapter\Product\ProductColorsRetriever;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\ValueObject\OutOfStockType;
use PrestaShop\PrestaShop\Core\Product\ProductPresentationSettings;
use Symfony\Component\Translation\TranslatorInterface;

class ProductLazyArrayTest extends TestCase
{
    protected $runTestInSeparateProcess = false;
    /**
     * @var Configuration|MockObject
     */
    private $mockConfiguration;
    /**
     * @var HookManager
     */
    private $mockHookManager;
    /**
     * @var ImageRetriever
     */
    private $mockImageRetriever;
    /**
     * @var Language
     */
    private $mockLanguage;
    /**
     * @var Link
     */
    private $mockLink;
    /**
     * @var PriceFormatter
     */
    private $mockPriceFormatter;
    /**
     * @var ProductColorsRetriever
     */
    private $mockProductColorsRetriever;
    /**
     * @var ProductPresentationSettings|MockObject
     */
    private $mockProductPresentationSettings;
    /**
     * @var TranslatorInterface
     */
    private $mockTranslatorInterface;
    /**
     * @var array
     */
    private $baseProduct = [
        'id_product_attribute' => 0,
        'price_tax_exc' => 0,
        'specific_prices' => 0,
        'reduction' => 0,
        'price_without_reduction' => 0,
        'new' => 0,
        'pack' => 0,
        'out_of_stock' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
    ];

    private const PRODUCT_AVAILABLE_NOW = 'This product is available now';
    private const PRODUCT_AVAILABLE_LATER = 'This product is available on backorder';
    private const PRODUCT_NOT_AVAILABLE = 'This product is not available for order';
    private const PRODUCT_ATTRIBUTE_NOT_AVAILABLE = 'Product available with different options';
    private const PRODUCT_WITH_NOT_ENOUGH_STOCK = 'There are not enough products in stock';

    public function setUp(): void
    {
        parent::setUp();

        $this->mockConfiguration = $this->getMockBuilder(Configuration::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockImageRetriever = $this->getMockBuilder(ImageRetriever::class)
            ->disableOriginalConstructor()->getMock();
        $this->mockImageRetriever
            ->method('getAllProductImages')
            ->willReturn([])
        ;

        $this->mockHookManager = $this->getMockBuilder(HookManager::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockLanguage = $this->getMockBuilder(Language::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockLanguage->id = 1;
        $this->mockLanguage
            ->method('getId')
            ->willReturn(1)
        ;
        $this->mockLink = $this->getMockBuilder(Link::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockPriceFormatter = $this->getMockBuilder(PriceFormatter::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockProductColorsRetriever = $this->getMockBuilder(ProductColorsRetriever::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockProductPresentationSettings = $this->getMockBuilder(ProductPresentationSettings::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockTranslatorInterface = $this->getMockBuilder(TranslatorInterface::class)
            ->disableOriginalConstructor()->getMock()
        ;
        $this->mockTranslatorInterface
            ->method('trans')
            ->willReturnCallback(function ($id, array $parameters = [], $domain = null, $locale = null) {
                return $id;
            })
        ;
    }

    public function testConstructor(): void
    {
        $this->setDefaultConfiguration();

        $productLazyArray = new ProductLazyArray(
            $this->mockProductPresentationSettings,
            $this->baseProduct,
            $this->mockLanguage,
            $this->mockImageRetriever,
            $this->mockLink,
            $this->mockPriceFormatter,
            $this->mockProductColorsRetriever,
            $this->mockTranslatorInterface,
            $this->mockHookManager,
            $this->mockConfiguration
        );
        $this->assertNotNull($productLazyArray);
    }

    /**
     * @param array $product
     * @param string $availabilityMessage
     *
     * @dataProvider providerQuantityInformationCases
     */
    public function testQuantityInformations(
        array $product,
        string $availabilityMessage
    ): void {
        $language = $this->mockLanguage;

        $this->mockProductPresentationSettings
            ->method('shouldShowPrice')
            ->willReturn(true);

        $this->mockConfiguration
            ->method('get')
            ->willReturnCallback(function (string $key) use ($language) {
                if ('PS_LABEL_OOS_PRODUCTS_BOD' === $key) {
                    return [
                        $language->id => static::PRODUCT_NOT_AVAILABLE,
                    ];
                }

                return true;
            })
        ;

        $this->mockProductPresentationSettings->showLabelOOSListingPages = true;
        $this->mockProductPresentationSettings->stock_management_enabled = true;
        $this->mockProductPresentationSettings->showPrices = true;
        $this->mockProductPresentationSettings->catalog_mode = false;

        $productLazyArray = new ProductLazyArray(
            $this->mockProductPresentationSettings,
            $product,
            $this->mockLanguage,
            $this->mockImageRetriever,
            $this->mockLink,
            $this->mockPriceFormatter,
            $this->mockProductColorsRetriever,
            $this->mockTranslatorInterface,
            $this->mockHookManager,
            $this->mockConfiguration
        );

        $this->assertEquals($availabilityMessage, $productLazyArray->availability_message);
    }

    public function providerQuantityInformationCases(): iterable
    {
        $product = array_merge(
            $this->baseProduct, ['show_price' => 1]
        );

        // Product page: in stock
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 0,
                    'quantity_wanted' => 1,
                    'stock_quantity' => 1000,
                    'quantity' => 1000,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
                ]
            ),
            static::PRODUCT_AVAILABLE_NOW,
        ];

        // not enough stock, not allowed to order when out of stock
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 0,
                    'quantity_wanted' => 11,
                    'stock_quantity' => 10,
                    'quantity' => 10,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_NOT_AVAILABLE,
                ]
            ),
            static::PRODUCT_WITH_NOT_ENOUGH_STOCK,
        ];

        // completely out of stock, not allowed to order when out of stock
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 0,
                    'quantity_wanted' => 1,
                    'stock_quantity' => 0,
                    'quantity' => 0,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_NOT_AVAILABLE,
                ]
            ),
            static::PRODUCT_NOT_AVAILABLE,
        ];

        // out of stock, but allowed to order when out of stock
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 0,
                    'quantity_wanted' => 11,
                    'stock_quantity' => 10,
                    'quantity' => 10,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_AVAILABLE,
                ]
            ),
            static::PRODUCT_AVAILABLE_LATER,
        ];

        // the combination is out of stock,
        // product is not available in different one,
        // not allowed to order when out of stock
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 1,
                    'quantity_all_versions' => 10,
                    'quantity_wanted' => 11,
                    'stock_quantity' => 10,
                    'quantity' => 10,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'availability_message' => static::PRODUCT_ATTRIBUTE_NOT_AVAILABLE,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_NOT_AVAILABLE,
                ]
            ),
            static::PRODUCT_WITH_NOT_ENOUGH_STOCK,
        ];

        // combinations is out of stock,
        // product is available in different one (higher "quantity_all_versions"),
        // allowed to order when out of stock
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 1,
                    'quantity_all_versions' => 1000,
                    'quantity_wanted' => 11,
                    'stock_quantity' => 10,
                    'quantity' => 10,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'availability_message' => static::PRODUCT_ATTRIBUTE_NOT_AVAILABLE,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_AVAILABLE,
                ]
            ),
            static::PRODUCT_AVAILABLE_LATER,
        ];

        // product with combinations
        // the one we want is out of stock, other combinations too
        // not allowed to order when out of stock
        // it should show the availability message for the "out of stock" status
        yield [
            array_merge(
                $product,
                [
                    'cache_default_attribute' => 1,
                    'quantity_wanted' => 5,
                    'stock_quantity' => 4,
                    'quantity' => 4,
                    'quantity_all_versions' => 4,
                    'show_availability' => 1,
                    'available_date' => false,
                    'available_now' => static::PRODUCT_AVAILABLE_NOW,
                    'available_later' => static::PRODUCT_AVAILABLE_LATER,
                    'allow_oosp' => OutOfStockType::OUT_OF_STOCK_NOT_AVAILABLE,
                ]
            ),
            static::PRODUCT_WITH_NOT_ENOUGH_STOCK,
        ];
    }

    /**
     * @param array $product
     * @param array $expectedFlags
     *
     * @dataProvider provideFlagsCases
     */
    public function testFlags(array $product, array $expectedFlags): void
    {
        $this->setDefaultConfiguration();

        $productLazyArray = new ProductLazyArray(
            $this->mockProductPresentationSettings,
            $product,
            $this->mockLanguage,
            $this->mockImageRetriever,
            $this->mockLink,
            $this->mockPriceFormatter,
            $this->mockProductColorsRetriever,
            $this->mockTranslatorInterface,
            $this->mockHookManager,
            $this->mockConfiguration
        );
        $flags = $productLazyArray->getFlags();

        $this->assertIsArray($flags);
        $this->assertEquals($expectedFlags, $flags);
    }

    public function provideFlagsCases(): iterable
    {
        // Label : None
        yield [$this->baseProduct, []];

        // Label : New
        $product = array_merge($this->baseProduct, ['new' => 1]);
        yield [$product, [
            'new' => [
                'type' => 'new',
                'label' => 'New',
            ],
        ]];

        // Label : Pack
        $product = array_merge($this->baseProduct, ['pack' => 1]);
        yield [$product, [
            'pack' => [
                'type' => 'pack',
                'label' => 'Pack',
            ],
        ]];
    }

    /**
     * @param array $product
     * @param array $expected
     * @param bool $configOrderOutOfStock
     *
     * @dataProvider provideFlagOutOfStockCases
     */
    public function testFlagsOutOfStock(
        array $product,
        array $expected,
        bool $configOrderOutOfStock
    ): void {
        $language = $this->mockLanguage;

        $this->mockConfiguration
            ->method('getBoolean')
            ->willReturnCallback(function (string $key) use ($configOrderOutOfStock) {
                if ($key === 'PS_ORDER_OUT_OF_STOCK') {
                    return $configOrderOutOfStock;
                }

                return true;
            })
        ;
        $this->mockConfiguration
            ->method('get')
            ->willReturnCallback(function (string $key) use ($language) {
                if ($key === 'PS_LABEL_OOS_PRODUCTS_BOD') {
                    return [
                        $language->id => 'Out-of-Stock',
                    ];
                }

                return true;
            })
        ;
        $this->mockProductPresentationSettings->showLabelOOSListingPages = true;

        $productLazyArray = new ProductLazyArray(
            $this->mockProductPresentationSettings,
            $product,
            $this->mockLanguage,
            $this->mockImageRetriever,
            $this->mockLink,
            $this->mockPriceFormatter,
            $this->mockProductColorsRetriever,
            $this->mockTranslatorInterface,
            $this->mockHookManager,
            $this->mockConfiguration
        );
        $flags = $productLazyArray->getFlags();

        $this->assertIsArray($flags);
        $this->assertEquals($expected, $flags);
    }

    public function provideFlagOutOfStockCases(): iterable
    {
        yield [array_merge($this->baseProduct, [
            'out_of_stock' => OutOfStockType::OUT_OF_STOCK_NOT_AVAILABLE,
            'quantity' => 0,
        ]), [
            'out_of_stock' => [
                'type' => 'out_of_stock',
                'label' => 'Out-of-Stock',
            ],
        ], true];

        yield [array_merge($this->baseProduct, [
            'out_of_stock' => OutOfStockType::OUT_OF_STOCK_NOT_AVAILABLE,
            'quantity' => 1,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'out_of_stock' => OutOfStockType::OUT_OF_STOCK_AVAILABLE,
            'quantity' => 0,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'out_of_stock' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
            'quantity' => 0,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'out_of_stock' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
            'quantity' => 0,
        ]), [
            'out_of_stock' => [
                'type' => 'out_of_stock',
                'label' => 'Out-of-Stock',
            ],
        ], false];

        yield [array_merge($this->baseProduct, [
            'out_of_stock' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
            'quantity' => 1,
        ]), [], false];
    }

    /**
     * @param array $product
     * @param array $expected
     * @param bool $settingsCatalogMode
     *
     * @dataProvider provideFlagPriceCases
     */
    public function testFlagsPrice(
        array $product,
        array $expected,
        bool $settingsCatalogMode
    ): void {
        $this->mockProductPresentationSettings
            ->method('shouldShowPrice')
            ->willReturn(true);
        $this->mockProductPresentationSettings->catalog_mode = $settingsCatalogMode;

        $productLazyArray = new ProductLazyArray(
            $this->mockProductPresentationSettings,
            $product,
            $this->mockLanguage,
            $this->mockImageRetriever,
            $this->mockLink,
            $this->mockPriceFormatter,
            $this->mockProductColorsRetriever,
            $this->mockTranslatorInterface,
            $this->mockHookManager,
            $this->mockConfiguration
        );
        $flags = $productLazyArray->getFlags();

        $this->assertIsArray($flags);
        $this->assertEquals($expected, $flags);
    }

    public function provideFlagPriceCases(): iterable
    {
        yield [array_merge($this->baseProduct, [
            'show_price' => false,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => false,
            'reduction' => false,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => true,
            'on_sale' => false,
            'reduction' => false,
        ]), [
            'online-only' => [
                'type' => 'online-only',
                'label' => 'Online only',
            ],
        ], true];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => true,
            'reduction' => false,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => true,
            'reduction' => false,
        ]), [], true];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => true,
            'reduction' => false,
        ]), [
            'on-sale' => [
                'type' => 'on-sale',
                'label' => 'On sale!',
            ],
        ], false];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => false,
            'reduction' => true,
        ]), [
            'discount' => [
                'type' => 'discount',
                'label' => 'Reduced price',
            ],
        ], true];

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => false,
            'reduction' => true,
            'discount_type' => '',
        ]), [
            'discount' => [
                'type' => 'discount',
                'label' => 'Reduced price',
            ],
        ], true];

        /*
         *
        No testable because use of Legacy Tools::displayNumber

        yield [array_merge($this->baseProduct, [
            'show_price' => true,
            'online_only' => false,
            'on_sale' => false,
            'reduction' => true,
            'specific_prices' => [
                'reduction_type' => 'percentage',
                'reduction' => '50',
            ],
        ]), [
            'discount' => [
                'type' => 'discount',
                'label' => 'Expected Value',
            ]
        ], true];
        */
    }

    private function setDefaultConfiguration(): void
    {
        $this->mockConfiguration
            ->method('get')
            ->willReturn(true)
        ;
    }
}
