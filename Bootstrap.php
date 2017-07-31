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

class Shopware_Plugins_Frontend_MakairaConnect_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    public function getInfo()
    {
        return array_merge(
            parent::getInfo(),
            [
                'author' => 'marmalade GmbH',
                'link'   => 'https://www.marmalade.de',
            ]
        );
    }

    public function getLabel()
    {
        return 'Makaira :: Connect';
    }

    public function getVersion()
    {
        return '0.0.1';
    }

    public function install()
    {
        //        $this->createConfig();

        return true;
    }
}
