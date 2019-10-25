<?php

namespace MakairaConnect\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MapperPoolCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('makaira_connect.mapper')) {
            return;
        }

        $definition = $container->findDefinition('makaira_connect.mapper');

        foreach ($container->findTaggedServiceIds('makaira_connect.mapper') as $id => $tags) {
            $definition->addMethodCall('addMapper', [new Reference($id)]);
        }
    }
}
