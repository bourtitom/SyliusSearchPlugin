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

use AutoMapper\Event\PropertyMetadataEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/** Search documents are not API responses: API serialization groups must not omit indexed fields. */
final class SearchMappingListener
{
    public function __construct(private array $automapperClasses)
    {
    }

    #[AsEventListener(event: PropertyMetadataEvent::class, priority: -100)]
    public function __invoke(PropertyMetadataEvent $event): void
    {
        if (!\in_array($event->mapperMetadata->target, $this->automapperClasses['targets'], true)) {
            return;
        }
        $event->disableGroupsCheck = true;
    }
}
