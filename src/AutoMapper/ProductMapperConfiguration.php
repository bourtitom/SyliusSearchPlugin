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

namespace MonsieurBiz\SyliusSearchPlugin\AutoMapper;

use AutoMapper\AutoMapperInterface;
use AutoMapper\Event\GenerateMapperEvent;
use AutoMapper\Event\PropertyMetadataEvent;
use AutoMapper\Event\SourcePropertyMetadata;
use AutoMapper\Event\TargetPropertyMetadata;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformer;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerInterface;
use InvalidArgumentException;
use LogicException;
use MonsieurBiz\SyliusSearchPlugin\Context\ChannelSimulationContext;
use MonsieurBiz\SyliusSearchPlugin\Entity\Product\SearchableInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductTaxonInterface;
use Sylius\Component\Core\Model\ProductVariantInterface as ModelProductVariantInterface;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Inventory\Model\StockableInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Sylius\Component\Product\Resolver\ProductVariantResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class ProductMapperConfiguration implements PropertyTransformerInterface
{
    private const FIELD_CONTEXT = 'monsieurbiz.search.product_mapping_field';

    private ConfigurationInterface $configuration;

    private AutoMapperInterface $autoMapper;

    private ProductVariantResolverInterface $productVariantResolver;

    private AvailabilityCheckerInterface $availabilityChecker;

    private ChannelSimulationContext $channelSimulationContext;

    public function __construct(
        ConfigurationInterface $configuration,
        #[Autowire(service: AutoMapperInterface::class, lazy: true)]
        AutoMapperInterface $autoMapper,
        ProductVariantResolverInterface $productVariantResolver,
        AvailabilityCheckerInterface $availabilityChecker,
        ChannelSimulationContext $channelSimulationContext
    ) {
        $this->configuration = $configuration;
        $this->autoMapper = $autoMapper;
        $this->productVariantResolver = $productVariantResolver;
        $this->availabilityChecker = $availabilityChecker;
        $this->channelSimulationContext = $channelSimulationContext;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    #[AsEventListener(event: GenerateMapperEvent::class)]
    public function process(GenerateMapperEvent $event): void
    {
        if ($event->mapperMetadata->source !== $this->getSource() || $event->mapperMetadata->target !== $this->getTarget()) {
            return;
        }

        foreach (['id', 'code', 'enabled', 'slug', 'name', 'description', 'created_at', 'images', 'mainTaxon', 'product_taxons', 'channels', 'attributes', 'options', 'variants', 'prices'] as $property) {
            $event->properties[$property] = new PropertyMetadataEvent(
                mapperMetadata: $event->mapperMetadata,
                source: new SourcePropertyMetadata($property),
                target: new TargetPropertyMetadata($property),
                transformer: new PropertyTransformer(self::class, [self::FIELD_CONTEXT => $property]),
            );
        }
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) Mapping uses the complete source rather than the individual value. */
    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        if (!$source instanceof ProductInterface) {
            throw new InvalidArgumentException('Expected a Sylius product.');
        }

        return match ($context[self::FIELD_CONTEXT] ?? null) {
            'id' => $source->getId(),
            'code' => $source->getCode(),
            'enabled' => $source->isEnabled(),
            'slug' => $source->getSlug(),
            'name' => $source->getName(),
            'description' => $source->getDescription(),
            'created_at' => $source->getCreatedAt(),
            'images' => $this->getImages($source),
            'mainTaxon' => $this->getMainTaxon($source),
            'product_taxons' => $this->getProductTaxons($source),
            'channels' => $this->getChannels($source),
            'attributes' => $this->getAttributes($source),
            'options' => $this->getOptions($source),
            'variants' => $this->getVariants($source),
            'prices' => $this->getPrices($source),
            default => throw new LogicException('Unknown product mapping field.'),
        };
    }

    private function getImages(ProductInterface $product): array
    {
        $images = [];
        foreach ($product->getImages() as $image) {
            $images[] = $this->autoMapper->map($image, $this->configuration->getTargetClass('image'));
        }

        return $images;
    }

    private function getMainTaxon(ProductInterface $product): mixed
    {
        $taxon = $product->getMainTaxon();
        if (null === $taxon) {
            return null;
        }
        $locale = $product->getTranslation()->getLocale();
        if (null !== $locale) {
            $taxon->setCurrentLocale($locale);
        }

        return $this->autoMapper->map($taxon, $this->configuration->getTargetClass('taxon'));
    }

    private function getProductTaxons(ProductInterface $product): array
    {
        return array_map(function (ProductTaxonInterface $productTaxon) use ($product) {
            $taxon = $productTaxon->getTaxon();
            $locale = $product->getTranslation()->getLocale();
            if (null !== $locale && null !== $taxon) {
                $taxon->setCurrentLocale($locale);
            }

            return $this->autoMapper->map($productTaxon, $this->configuration->getTargetClass('product_taxon'));
        }, $product->getProductTaxons()->toArray());
    }

    private function getChannels(ProductInterface $product): array
    {
        return array_map(function ($channel) {
            return $this->autoMapper->map($channel, $this->configuration->getTargetClass('channel'));
        }, $product->getChannels()->toArray());
    }

    public function getSource(): string
    {
        return $this->configuration->getSourceClass('product');
    }

    public function getTarget(): string
    {
        return $this->configuration->getTargetClass('product');
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getAttributes(ProductInterface $product): array
    {
        $attributes = [];
        $currentLocale = $product->getTranslation()->getLocale();
        if (null === $currentLocale) {
            return $attributes;
        }
        $productAttributeDTOClass = $this->configuration->getTargetClass('product_attribute');
        foreach ($product->getAttributesByLocale($currentLocale, $currentLocale) as $attributeValue) {
            $attribute = $attributeValue->getAttribute();
            $currentLocale = $product->getTranslation()->getLocale();
            if (null !== $currentLocale && null !== $attribute) {
                $attribute->setCurrentLocale($currentLocale);
            }
            if (null === $attributeValue->getName() || null === $attributeValue->getValue()) {
                continue;
            }
            $attribute = $attributeValue->getAttribute();
            if (!$attribute instanceof SearchableInterface || (!$attribute->isSearchable() && !$attribute->isFilterable())) {
                continue;
            }
            $attributes[$attributeValue->getCode()] = $this->autoMapper->map($attributeValue, $productAttributeDTOClass);
        }

        return $attributes;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getOptions(ProductInterface $product): array
    {
        $options = [];
        $currentLocale = $product->getTranslation()->getLocale();
        foreach ($product->getVariants() as $variant) {
            foreach ($variant->getOptionValues() as $optionValue) {
                if (null === $optionValue->getOption()) {
                    continue;
                }
                if (!isset($options[$optionValue->getOptionCode()])) {
                    $options[$optionValue->getOptionCode()] = [
                        'name' => $optionValue->getOption()->getTranslation($currentLocale)->getName(),
                        'values' => [],
                    ];
                }
                $isEnabled = ($options[$optionValue->getOptionCode()]['values'][$optionValue->getCode()]['enabled'] ?? false)
                    || $variant->isEnabled();
                // A variant option is considered to be in stock if the current option is enabled and is in stock
                $isInStock = ($options[$optionValue->getOptionCode()]['values'][$optionValue->getCode()]['is_in_stock'] ?? false)
                    || ($variant->isEnabled() && $this->isProductVariantInStock($variant));
                $options[$optionValue->getOptionCode()]['values'][$optionValue->getCode()] = [
                    'value' => $optionValue->getTranslation($currentLocale)->getValue(),
                    'enabled' => $isEnabled,
                    'is_in_stock' => $isInStock,
                ];
            }
        }

        foreach ($options as $optionCode => $optionValues) {
            $options[$optionCode]['values'] = array_values($optionValues['values']);
        }

        return $options;
    }

    public function getVariants(ProductInterface $product): array
    {
        $variants = [];
        $productVariantDTOClass = $this->configuration->getTargetClass('product_variant');
        foreach ($product->getEnabledVariants() as $variant) {
            $variants[] = $this->autoMapper->map($variant, $productVariantDTOClass);
        }

        return $variants;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getPrices(ProductInterface $product): array
    {
        $prices = [];
        foreach ($product->getChannels() as $channel) {
            /** @var ChannelInterface $channel */
            $this->channelSimulationContext->setChannel($channel);

            try {
                if (
                    null === ($variant = $this->productVariantResolver->getVariant($product))
                    || !$variant instanceof ModelProductVariantInterface
                    || null === ($channelPricing = $variant->getChannelPricingForChannel($channel))
                ) {
                    continue;
                }
            } finally {
                $this->channelSimulationContext->setChannel(null);
            }
            $prices[] = $this->autoMapper->map(
                $channelPricing,
                $this->configuration->getTargetClass('pricing')
            );
        }

        return $prices;
    }

    private function isProductVariantInStock(ProductVariantInterface $productVariant): bool
    {
        if (!$productVariant instanceof StockableInterface) {
            return true;
        }

        return $this->availabilityChecker->isStockAvailable($productVariant);
    }
}
