<?php
/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version    1.0
 * @author     Stefan Krenz, Christopher Schnecke. Jennifer Timm <hello@makaira.io>
 * @link       http://www.marmalade.de
 */

namespace MakairaConnect;

use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin;
use Doctrine\ORM\Tools\SchemaTool;

use MakairaConnect\Models\MakRevision as MakRevisionModel;

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

class MakairaConnect extends Plugin {
  /**
   * automatic called (from shopware system)
   * Install plugin method
   *
   * @param InstallContext $installContext
   * @return void
   */
  public function install(InstallContext $installContext) {
    $this->installModels();
  }

  /**
   * automatic called (from shopware system)
   * Uninstall plugin method
   *
   * @param UninstallContext $unInstallContext
   * @return void
   */
  public function uninstall(UninstallContext $unInstallContext) {
    //abbortion if the user chose to keep the userdata
    if ($unInstallContext->keepUserData()) {
      return;
    }

    $this->uninstallModels();
  }

  /**
   * @return SchemaTool
   */
  private function fetchSchemaTool() {
    $entityManager = $this->container->get('models');
    return new SchemaTool($entityManager);
  }

  /**
   * Installs all registered models
   * -> make sure to use the save mode
   */
  private function installModels() {
    $this->fetchSchemaTool()->updateSchema(
      $this->getMappingClassesMetaData(),
      true
    );
  }

  /**
   * Drops all registered models
   */
  private function uninstallModels () {
    $this->fetchSchemaTool()->dropSchema(
      $this->getMappingClassesMetaData()
    );
  }

  /**
   * @return array
   */
  private function getMappingClassesMetaData() {
    return [
      $this->container->get('models')->getClassMetadata(MakRevisionModel::class)
    ];
  }
}
