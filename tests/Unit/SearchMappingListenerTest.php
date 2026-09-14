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
use AutoMapper\Configuration;
use AutoMapper\Event\PropertyMetadataEvent;
use MonsieurBiz\SyliusSearchPlugin\AutoMapper\SearchMappingListener;
use MonsieurBiz\SyliusSearchPlugin\Generated\Model\ChannelDTO;
use MonsieurBiz\SyliusSearchPlugin\Generated\Normalizer\ChannelDTONormalizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Attribute\Groups;

final class SearchMappingListenerTest extends TestCase
{
    public function testApiGroupsDoNotRemoveFieldsFromConfiguredSearchTargets(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PropertyMetadataEvent::class, new SearchMappingListener(['targets' => ['channel' => ChannelDTO::class]]), -100);
        $mapper = AutoMapper::create(eventDispatcher: $dispatcher, removeDefaultProperties: true);
        $dto = $mapper->map(new GroupedChannel(), ChannelDTO::class);

        self::assertSame('WEB', $dto->getCode());
        self::assertSame(['code' => 'WEB'], (new ChannelDTONormalizer())->normalize($dto, 'json'));
    }

    public function testUnrelatedTargetRetainsItsApiGroupFiltering(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(PropertyMetadataEvent::class, new SearchMappingListener(['targets' => []]), -100);
        $mapper = AutoMapper::create(configuration: new Configuration(classPrefix: 'UnrelatedMapper_'), eventDispatcher: $dispatcher, removeDefaultProperties: true);
        $dto = $mapper->map(new GroupedChannel(), ChannelDTO::class);

        self::assertFalse($dto->isInitialized('code'));
    }
}

final class GroupedChannel
{
    #[Groups(['api:channel:read'])]
    public string $code = 'WEB';
}
