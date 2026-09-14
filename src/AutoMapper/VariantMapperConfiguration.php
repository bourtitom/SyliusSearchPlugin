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

use AutoMapper\Event\GenerateMapperEvent;
use AutoMapper\Event\PropertyMetadataEvent;
use AutoMapper\Event\SourcePropertyMetadata;
use AutoMapper\Event\TargetPropertyMetadata;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformer;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerInterface;
use InvalidArgumentException;
use Sylius\Component\Inventory\Checker\AvailabilityCheckerInterface;
use Sylius\Component\Inventory\Model\StockableInterface;
use Sylius\Component\Product\Model\ProductVariantInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class VariantMapperConfiguration implements PropertyTransformerInterface
{
    private ConfigurationInterface $configuration;

    private AvailabilityCheckerInterface $availabilityChecker;

    public function __construct(ConfigurationInterface $configuration, AvailabilityCheckerInterface $availabilityChecker)
    {
        $this->configuration = $configuration;
        $this->availabilityChecker = $availabilityChecker;
    }

    #[AsEventListener(event: GenerateMapperEvent::class)]
    public function process(GenerateMapperEvent $event): void
    {
        if ($event->mapperMetadata->source !== $this->getSource() || $event->mapperMetadata->target !== $this->getTarget()) {
            return;
        }

        $event->properties['is_in_stock'] = new PropertyMetadataEvent(
            mapperMetadata: $event->mapperMetadata,
            source: new SourcePropertyMetadata('is_in_stock'),
            target: new TargetPropertyMetadata('is_in_stock'),
            transformer: new PropertyTransformer(self::class),
        );
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) AutoMapper's transformer contract supplies the unused value/context. */
    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        if (!$source instanceof ProductVariantInterface) {
            throw new InvalidArgumentException('Expected a product variant.');
        }

        return !$source instanceof StockableInterface || $this->availabilityChecker->isStockAvailable($source);
    }

    public function getSource(): string
    {
        return $this->configuration->getSourceClass('product_variant');
    }

    public function getTarget(): string
    {
        return $this->configuration->getTargetClass('product_variant');
    }
}
