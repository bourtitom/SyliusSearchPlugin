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

namespace App\Search\Automapper;

use AutoMapper\Event\GenerateMapperEvent;
use AutoMapper\Event\PropertyMetadataEvent;
use AutoMapper\Event\SourcePropertyMetadata;
use AutoMapper\Event\TargetPropertyMetadata;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformer;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerInterface;
use InvalidArgumentException;
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\ConfigurationInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** The historical decorator is now an additive metadata listener. */
final class DecorateProductMapperConfiguration implements PropertyTransformerInterface
{
    public function __construct(private ConfigurationInterface $configuration)
    {
    }

    #[AsEventListener(event: GenerateMapperEvent::class, priority: -10)]
    public function process(GenerateMapperEvent $event): void
    {
        if ($event->mapperMetadata->source !== $this->configuration->getSourceClass('product') || $event->mapperMetadata->target !== $this->configuration->getTargetClass('product')) {
            return;
        }
        $event->properties['short_description'] = new PropertyMetadataEvent(
            mapperMetadata: $event->mapperMetadata,
            source: new SourcePropertyMetadata('short_description'),
            target: new TargetPropertyMetadata('short_description'),
            transformer: new PropertyTransformer(self::class),
        );
    }

    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        if (!$source instanceof ProductInterface) {
            throw new InvalidArgumentException('Expected a product.');
        }

        return $source->getShortDescription();
    }
}
