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

namespace MonsieurBiz\SyliusSearchPlugin\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class AutoMapperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /** @var array{sources: array<string, string>, targets: array<string, string>} $classes */
        $classes = $container->getParameterBag()->resolveValue($container->getParameter('monsieurbiz.search.config.automapper_classes'));
        $registry = $container->getDefinition('automapper.config_mapping_registry');
        foreach ($classes['sources'] as $key => $source) {
            $targetKey = 'product_attribute_value' === $key ? 'product_attribute' : $key;
            if (isset($classes['targets'][$targetKey])) {
                $registry->addMethodCall('register', [$source, $classes['targets'][$targetKey]]);
            }
        }
    }
}
