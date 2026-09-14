<?php

/*
 * This file is part of Monsieur Biz' Search plugin for Sylius.
 *
 * (c) Monsieur Biz <sylius@monsieurbiz.com>
 *
 * For the full copyright and license information, please view the LICENSE.txt
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace MonsieurBiz\SyliusSearchPlugin\Tests\Unit;

use AutoMapper\AutoMapper;
use AutoMapper\AutoMapperInterface;
use AutoMapper\Event\GenerateMapperEvent;
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\Configuration;
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\ProductMapperConfiguration;
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\VariantMapperConfiguration;
use MonsieurBiz\SyliusSearchPlugin\Context\ChannelSimulationContext;
use MonsieurBiz\SyliusSearchPlugin\Model\Product\ProductDTO;
use MonsieurBiz\SyliusSearchPlugin\Model\Product\VariantDTO;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariant;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class AutoMapperTest extends TestCase
{
    public function testProductAndVariantFieldsUseTheExistingDtoStorage(): void
    {
        $configuration = new Configuration([
            'sources' => ['product' => Product::class, 'product_variant' => ProductVariant::class],
            'targets' => ['product' => ProductDTO::class, 'product_variant' => VariantDTO::class, 'product_attribute' => \MonsieurBiz\SyliusSearchPlugin\Generated\Model\ProductAttributeDTO::class],
        ]);
        $mapper = null;
        $nestedMapper = $this->createMock(AutoMapperInterface::class);
        $nestedMapper->method('map')->willReturnCallback(static function ($source, $target, $context = []) use (&$mapper) {
            return $mapper->map($source, $target, $context);
        });
        $availability = $this->createMock(AvailabilityCheckerInterface::class);
        $availability->method('isStockAvailable')->willReturn(true);
        $productMapping = new ProductMapperConfiguration($configuration, $nestedMapper, $this->createMock(ProductVariantResolverInterface::class), $availability, new ChannelSimulationContext());
        $variantMapping = new VariantMapperConfiguration($configuration, $availability);
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(GenerateMapperEvent::class, [$productMapping, 'process']);
        $dispatcher->addListener(GenerateMapperEvent::class, [$variantMapping, 'process']);
        $mapper = AutoMapper::create(
            propertyTransformers: [ProductMapperConfiguration::class => $productMapping, VariantMapperConfiguration::class => $variantMapping],
            eventDispatcher: $dispatcher,
            removeDefaultProperties: true,
        );

        $product = new Product();
        (new ReflectionProperty($product, 'id'))->setValue($product, 42);
        $product->setCurrentLocale('en_US');
        $product->setFallbackLocale('en_US');
        $product->setCode('MIGRATION_TEST');
        $product->setName('Migration product');
        $product->setSlug('migration-product');
        $product->setDescription('A searchable product');
        $variant = new ProductVariant();
        $variant->setCurrentLocale('en_US');
        $variant->setFallbackLocale('en_US');
        $variant->setCode('MIGRATION_VARIANT');
        $product->addVariant($variant);

        $dto = $mapper->map($product, ProductDTO::class);

        self::assertInstanceOf(ProductDTO::class, $dto);
        self::assertSame(42, $dto->getId());
        self::assertSame('MIGRATION_TEST', $dto->getCode());
        self::assertSame('Migration product', $dto->getName());
        self::assertSame('migration-product', $dto->getSlug());
        self::assertSame('A searchable product', $dto->getDescription());
        self::assertTrue($dto->getEnabled());
        self::assertSame([], $dto->getImages());
        self::assertSame([], $dto->getPrices());
        self::assertNull($dto->getMainTaxon());
        self::assertCount(1, $dto->getVariants());
        self::assertSame('MIGRATION_VARIANT', $dto->getVariants()[0]->getCode());
        self::assertTrue($dto->getVariants()[0]->isInStock());
    }
}
