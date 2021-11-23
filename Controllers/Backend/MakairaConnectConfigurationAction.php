<?php

use MakairaConnect\Service\UpdateFilters;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

class Shopware_Controllers_Backend_MakairaConnectConfigurationAction extends Shopware_Controllers_Backend_ExtJs
{
    /**
     * @var UpdateFilters
     */
    private $filterUpdater;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @throws Exception
     */
    public function preDispatch()
    {
        parent::preDispatch();

        $this->filterUpdater = $this->get(UpdateFilters::class);
        $this->logger        = $this->get('pluginlogger');
    }

    /**
     *
     */
    public function updateFiltersAction()
    {
        try {
            $this->filterUpdater->update();

            $this->View()->assign(['ok' => true]);
        } catch (Throwable $t) {
            $this->logger->error((string) $t);

            $this->Response()->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->View()->assign('response', $t->getMessage());
        }
    }
}
