<?php declare(strict_types=1);

namespace GingerPlugin\Controller\Api;

use GingerPlugin\Components\BankConfig;
use GingerPlugin\Components\Redefiner;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

# don't remove this uses, important for shopware classes!
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"administration"}})
 */
class ApiTestController
{
    private $client;

    public function __construct(Redefiner $redefiner)
    {
        $this->client = $redefiner;
    }

    /**
     * @Route(path="/api/_action/emspay/verify", methods={"POST"})
     */
    public function check(RequestDataBag $dataBag): JsonResponse
    {
        $success = true;
        $key = implode('.',[BankConfig::PLUGIN_TECH_PREFIX,"config","GingerAPIKey"]);
        if (!$dataBag->has($key)) {
            $success = false;
            return new JsonResponse(['success' => $success]);
        }

        try {
            $apiKey = $dataBag->get($key);
            $ginger_client = $this->client->getGingerClient($apiKey, false);
            $ginger_client->getIdealIssuers();
        } catch (\Exception $exception) {
            $success = false;
            return new JsonResponse(['success' => $success]);
        }
        return new JsonResponse(['success' => $success]);
    }
}
