<?php

namespace MakairaConnect\Subscriber;

use Enlight\Event\SubscriberInterface;
use Enlight_Controller_EventArgs;
use Enlight_Controller_Request_RequestHttp;
use Shopware\Components\Routing\Context;
use Shopware\Components\Routing\MatcherInterface;
use function array_filter;
use function explode;
use function implode;
use function preg_match;

class FilterSEOUrls implements SubscriberInterface, MatcherInterface
{
    private $filters = [];

    /**
     * Returns an array of event names this subscriber wants to listen to.
     * The array keys are event names and the value can be:
     *  * The method name to call (position defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     * For instance:
     * <code>
     * return array(
     *     'eventName0' => 'callback0',
     *     'eventName1' => array('callback1'),
     *     'eventName2' => array('callback2', 10),
     *     'eventName3' => array(
     *         array('callback3_0', 5),
     *         array('callback3_1'),
     *         array('callback3_2')
     *     )
     * );
     * </code>
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Front_RouteStartup' => 'onFrontRouteStartup',
        ];
    }

    public function onFrontRouteStartup(Enlight_Controller_EventArgs $args)
    {
        /** @var Enlight_Controller_Request_RequestHttp $request */
        $request   = $args->getRequest();
        $pathParts = array_filter(explode('/', $request->getPathInfo()));
        foreach ($pathParts as $index => $pathPart) {
            if (preg_match('/^(\w+)_(.*)$/', $pathPart, $matches)) {
                $this->filters["makairaFilter_{$matches[1]}"][] = $matches[2];
                unset($pathParts[$index]);
            }
        }

        $newPathInfo = '/' . implode('/', $pathParts) . '/';
        $request->setPathInfo($newPathInfo);
    }

    /**
     * @param string  $pathInfo
     * @param Context $context
     *
     * @return string|array|false
     */
    public function match($pathInfo, Context $context)
    {
        foreach ($this->filters as $name => $value) {
            $context->setParam($name, implode('|', $value));
        }

        return $pathInfo;
    }
}
