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

use AutoMapper\AutoMapperInterface;
use AutoMapper\Event\GenerateMapperEvent;
use AutoMapper\Event\PropertyMetadataEvent;
use AutoMapper\Event\SourcePropertyMetadata;
use AutoMapper\Event\TargetPropertyMetadata;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformer;
use AutoMapper\Transformer\PropertyTransformer\PropertyTransformerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Proxy;
use InvalidArgumentException;
use LogicException;
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\ConfigurationInterface;
use Sylius\Component\Core\Model\TaxonInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final class TaxonMapperConfiguration implements PropertyTransformerInterface
{
    public function __construct(
        private ConfigurationInterface $configuration,
        #[Autowire(service: AutoMapperInterface::class, lazy: true)]
        private AutoMapperInterface $autoMapper,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[AsEventListener(event: GenerateMapperEvent::class)]
    public function process(GenerateMapperEvent $event): void
    {
        if ($event->mapperMetadata->source !== $this->configuration->getSourceClass('taxon') || $event->mapperMetadata->target !== $this->configuration->getTargetClass('app_taxon')) {
            return;
        }
        foreach (['id', 'code', 'enabled', 'slug', 'name', 'description', 'created_at', 'position', 'level', 'left', 'right', 'parent_taxon'] as $property) {
            $event->properties[$property] = new PropertyMetadataEvent(
                mapperMetadata: $event->mapperMetadata,
                source: new SourcePropertyMetadata($property),
                target: new TargetPropertyMetadata($property),
                transformer: new PropertyTransformer(self::class, ['app.taxon_mapping_field' => $property]),
            );
        }
    }

    public function transform(mixed $value, object|array $source, array $context): mixed
    {
        if (!$source instanceof TaxonInterface) {
            throw new InvalidArgumentException('Expected a taxon.');
        }

        return match ($context['app.taxon_mapping_field'] ?? null) {
            'id' => $source->getId(),
            'code' => $source->getCode(),
            'enabled' => $source->isEnabled(),
            'slug' => $source->getSlug(),
            'name' => $source->getName(),
            'description' => $source->getDescription(),
            'created_at' => $source->getCreatedAt(),
            'position' => $source->getPosition(),
            'level' => $source->getLevel(),
            'left' => $source->getLeft(),
            'right' => $source->getRight(),
            'parent_taxon' => $this->getParent($source),
            default => throw new LogicException('Unknown taxon mapping field.'),
        };
    }

    private function getParent(TaxonInterface $taxon): mixed
    {
        $parent = $taxon->getParent();
        if (null === $parent) {
            return null;
        }
        if ($parent instanceof Proxy) {
            $class = $this->entityManager->getClassMetadata($parent::class)->getName();
            $this->entityManager->detach($parent);
            $parent = $this->entityManager->find($class, $parent->getId());
        }
        if (null === $parent) {
            return null;
        }
        $locale = $taxon->getTranslation()->getLocale();
        if (null !== $locale) {
            $parent->setCurrentLocale($locale);
        }

        return $this->autoMapper->map($parent, $this->configuration->getTargetClass('app_taxon'));
    }
}
