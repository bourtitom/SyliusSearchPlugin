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
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\ProductAttributeValueReader\ReaderInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use RuntimeException;
use Sylius\Component\Product\Model\ProductAttributeValueInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Traversable;

final class ProductAttributeValueConfiguration implements PropertyTransformerInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ConfigurationInterface $configuration;

    /**
     * @var ReaderInterface[]
     */
    private array $productAttributeValueReaders;

    public function __construct(ConfigurationInterface $configuration, iterable $productAttributeValueReaders)
    {
        $this->logger = new NullLogger();
        $this->configuration = $configuration;
        $this->productAttributeValueReaders = $productAttributeValueReaders instanceof Traversable
            ? iterator_to_array($productAttributeValueReaders)
            : $productAttributeValueReaders;
    }

    #[AsEventListener(event: GenerateMapperEvent::class)]
    public function process(GenerateMapperEvent $event): void
    {
        if ($event->mapperMetadata->source !== $this->getSource() || $event->mapperMetadata->target !== $this->getTarget()) {
            return;
        }
        if (0 === \count($this->productAttributeValueReaders)) {
            throw new RuntimeException('Undefined product attribute value reader');
        }

        $event->properties['value'] = new PropertyMetadataEvent(
            mapperMetadata: $event->mapperMetadata,
            source: new SourcePropertyMetadata('value'),
            target: new TargetPropertyMetadata('value'),
            transformer: new PropertyTransformer(self::class),
        );
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) AutoMapper's transformer contract supplies the unused value/context. */
    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        if (!$source instanceof ProductAttributeValueInterface) {
            throw new InvalidArgumentException('Expected a product attribute value.');
        }

        return $this->getProductAttributeValue($source);
    }

    public function getSource(): string
    {
        return $this->configuration->getSourceClass('product_attribute_value');
    }

    public function getTarget(): string
    {
        return $this->configuration->getTargetClass('product_attribute');
    }

    /**
     * @return array|string|null
     */
    public function getProductAttributeValue(ProductAttributeValueInterface $productAttributeValue)
    {
        if (null === $productAttributeValue->getType()) {
            return null;
        }
        if (!\array_key_exists($productAttributeValue->getType(), $this->productAttributeValueReaders)) {
            // @phpstan-ignore-next-line The logger can't be null here
            $this->logger->alert(\sprintf('Missing product attribute value reader for "%s" type', $productAttributeValue->getType()));

            return null;
        }
        $reader = $this->productAttributeValueReaders[$productAttributeValue->getType()];

        return $reader->getValue($productAttributeValue);
    }
}
