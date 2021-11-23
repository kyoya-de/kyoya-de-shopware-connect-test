<?php

class Shopware_Controllers_Backend_MakairaConnect extends Shopware_Controllers_Backend_Application
{
    protected $model = 'NotEmptyModel';

    public function recommendationIdentifiersAction()
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

        $this->view->assign([
            'data' => $data,
            'total' => count($data),
        ]);
    }
}
