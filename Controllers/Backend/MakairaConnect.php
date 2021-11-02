<?php

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * This file is part of a marmalade GmbH project
 * It is not Open Source and may not be redistributed.
 * For contact information please visit http://www.marmalade.de
 *
 * @version    0.1
 * @author     Stefan Krenz <krenz@marmalade.de>
 * @link       http://www.marmalade.de
 */
class Shopware_Controllers_Backend_MakairaConnect extends Shopware_Controllers_Api_Rest
{
    public function identifierAction()
    {
        $api = $this->container->get('makaira.api');
        $recommendations = $api->getMakairaRecommendations();

        $data = [];
        foreach ($recommendations as $recommendation) {
            $data[] = [
                'id' => $recommendation['recommendationId'],
                'name' => $recommendation['name']
            ];
        }
        $jsonResponse = new JsonResponse();
        $jsonResponse->setData($data);
        $jsonResponse->send();
    }
}
