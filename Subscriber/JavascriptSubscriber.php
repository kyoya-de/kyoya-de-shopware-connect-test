<?php

namespace MakairaConnect\Subscriber;

use Doctrine\Common\Collections\ArrayCollection;
use Enlight\Event\SubscriberInterface;

class JavascriptSubscriber implements SubscriberInterface
{

    /**
     * only add Events::<classes>
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'Theme_Compiler_Collect_Plugin_Javascript', 'collectJs'
        ];
    }

    public function collectJs(\Enlight_Event_EventArgs $args)
    {
        $jsFiles = glob(__DIR__ . '/../Resources/frontend/js/*.js');
        return new ArrayCollection($jsFiles);
    }
}
