<?php declare(strict_types=1);

namespace ActiveAnstsReturnLabelPlugin\Library;

use mysql_xdevapi\Exception;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MayaService
{
    /**
     * @var \GuzzleHttp\Client
     */
    private \GuzzleHttp\Client $client;

    /**
     * @var string
     */
    private string $token;

    /**
     * @var string
     */
    private string $serviceBaseUrl;

    /**
     * @var SystemConfigService
     */
    private SystemConfigService $systemConfigService;

    public function __construct(SystemConfigService $systemConfigService, ContainerInterface $container)
    {
        $this->systemConfigService = $systemConfigService;

        $this->serviceBaseUrl = $this->getServiceUrl();

        $this->client = $this->getClient();

        $this->config = $container->get('Shopware\Core\System\SystemConfig\SystemConfigService');

    }

    /**
     * @return \GuzzleHttp\Client
     */
    private function getClient(): \GuzzleHttp\Client
    {
        if (empty($this->token)) {
            $this->getToken();
        }
        return new \GuzzleHttp\Client([
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Bearer ' . $this->token
            ]
        ]);
    }

    /**
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function getToken()
    {
        $token = $this->systemConfigService->get('ActiveAnstsReturnLabelPlugin.bearer_token');
        if (empty($token)) {
            $client = new \GuzzleHttp\Client([
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);

            $apiKey = $this->systemConfigService->get('ActiveAnstsReturnLabelPlugin.config.apiKey');
            $apiSecret = $this->systemConfigService->get('ActiveAnstsReturnLabelPlugin.config.apiSecret');

            if (empty($apiKey) || empty($apiSecret)) {
                throw new \Exception('Please configure Active Ansts Return Label Plugin', 500);
            }

            $response = $client->request('POST', $this->serviceBaseUrl . '/token', [
                'form_params' => [
                    'grant_type' => 'password',
                    'username' => $apiKey,
                    'password' => $apiSecret
                ]
            ]);
            $data = $this->extractResponseData($response);
            $token = $data->access_token;
            $this->systemConfigService->set('ActiveAnstsReturnLabelPlugin.bearer_token', $token);
        }

        $this->token = $token;
    }

    /**
     * @param $orderId
     * @return int
     */
    public function getShippingId($externalOrderNumber)
    {
        try {
            $response = $this->shipmentSearch($externalOrderNumber);
        } catch (\Exception $e) {
            if ($e->getCode() == 401) {
                $this->systemConfigService->set('ActiveAnstsReturnLabelPlugin.bearer_token', '');
                //Refresh Token
                $this->getToken();
                $response = $this->shipmentSearch($externalOrderNumber);
            }
        }

        $data = $this->extractResponseData($response);
        $data = $data->result;
        return $data[0]->id;
    }

    /**
     * @param $externalOrderNumber
     * @return false|string
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getReturnLabelPDF($externalOrderNumber)
    {
        $shippingId = $this->getShippingId($externalOrderNumber);
        $response = $this->client
            ->request('GET', $this->serviceBaseUrl . '/v2/returnlabel/get/' . $shippingId);
        return $this->extractResponseData($response)->result;
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return false|string
     */
    private function extractResponseData(\Psr\Http\Message\ResponseInterface $response)
    {
        return json_decode($response->getBody()->getContents());
    }

    /**
     * @return string
     * @throws \Exception
     */
    private function getServiceUrl()
    {
        $serviceUrl = $this->systemConfigService->get('ActiveAnstsReturnLabelPlugin.config.apiURL');
        if (empty($serviceUrl)) {
            throw new \Exception('Please configure Active Ansts Return Label Plugin', 500);
        }
        return $serviceUrl;
    }

    /**
     * @param $externalOrderNumber
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function shipmentSearch($externalOrderNumber): \Psr\Http\Message\ResponseInterface
    {
        $response = $this->client->request('POST', $this->serviceBaseUrl . '/shipment/search', [
            'form_params' => [
                'ExternalOrderNumber' => $externalOrderNumber,
            ]
        ]);
        return $response;
    }
}
