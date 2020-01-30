<?php

namespace MakairaConnect\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use function array_merge;
use function krsort;

class ModifierCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('makaira_connect.mapper')) {
            return;
        }

        $mapperDefinition = $container->getDefinition('makaira_connect.mapper');

        $this->addModifiers(
            $mapperDefinition,
            $container->findTaggedServiceIds('makaira_connect.mapper.modifier.product'),
            'addProductModifier'
        );

        $this->addModifiers(
            $mapperDefinition,
            $container->findTaggedServiceIds('makaira_connect.mapper.modifier.variant'),
            'addVariantModifier'
        );

        $this->addModifiers(
            $mapperDefinition,
            $container->findTaggedServiceIds('makaira_connect.mapper.modifier.category'),
            'addCategoryModifier'
        );

        $this->addModifiers(
            $mapperDefinition,
            $container->findTaggedServiceIds('makaira_connect.mapper.modifier.manufacturer'),
            'addManufacturerModifier'
        );
    }

    /**
     * @param Definition $definition
     * @param array      $modifierIds
     * @param string     $method
     */
    private function addModifiers(Definition $definition, array $modifierIds, string $method)
    {
        $serviceIds = [];
        foreach ($modifierIds as $id => $tags) {
            $priority = (int) ($tags[0]['priority'] ?? 0);
            $serviceIds[$priority][] = $id;
        }

        krsort($serviceIds);

        foreach (array_merge(...$serviceIds) as $serviceId) {
            $definition->addMethodCall($method, [new Reference($serviceId)]);
        }
    }
}
