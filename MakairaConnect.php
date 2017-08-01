<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version    0.1
 * @author     Stefan Krenz <krenz@marmalade.de>
 * @link       http://www.marmalade.de
 */

namespace MakairaConnect;

use MakairaConnect\Models\ConnectChanges;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\InstallContext;

class MakairaConnect extends Plugin
{
    public function install(InstallContext $context)
    {
        $entityManager = $this->container->get('shopware.model_manager');
        $schemaTool    = new \Doctrine\ORM\Tools\SchemaTool($entityManager);

        $classes = array(
            $entityManager->getClassMetadata(ConnectChanges::class)
        );

        try {
            $schemaTool->createSchema($classes);
        } catch (\Exception $e) {
            //ignore
        }
    }

}
