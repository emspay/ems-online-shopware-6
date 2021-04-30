<?php

namespace GingerPlugin\emspay\Controller\Api;

use GingerPlugin\emspay\Service\ClientBuilder;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"administration"})
 */
class ApiTestController
{
    private $clientBuilder;

    public function __construct(ClientBuilder $clientBuilder)
    {
        $this->clientBuilder = $clientBuilder;
    }

    /**
     * @Route(path="/api/v{version}/_action/emspay/verify")
     */
    public function check(RequestDataBag $dataBag): JsonResponse
    {
        $success = true;

        if (!$dataBag->has('emspay.config.emsOnlineApikey')) {
            $success = false;
            return new JsonResponse(['success' => $success]);
        }

        try {
            $apiKey = $dataBag->get('emspay.config.emsOnlineApikey');
            $ginger_client = $this->clientBuilder->getGignerClinet($apiKey);
            $ginger_client->getIdealIssuers();
        } catch (\Exception $exception) {
            $success = false;
            return new JsonResponse(['success' => $success]);
        }
        return new JsonResponse(['success' => $success]);
    }
}
