<?php

namespace MakairaConnect\Client;

use Makaira\Aggregation;
use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\HttpClient;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use function htmlspecialchars_decode;
use function json_decode;
use function json_encode;
use const ENT_QUOTES;

class Api implements ApiInterface
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $pluginVersion;

    /**
     * Api constructor.
     *
     * @param HttpClient $httpClient
     * @param array      $config
     * @param string     $pluginVersion
     */
    public function __construct(HttpClient $httpClient, array $config, string $pluginVersion)
    {
        $this->baseUrl       = rtrim($config['makaira_application_url'], '/');
        $this->httpClient    = $httpClient;
        $this->config        = $config;
        $this->pluginVersion = $pluginVersion;
    }

    /**
     * @param Query  $query
     * @param string $debug
     *
     * @return array
     * @throws ConnectException
     * @throws UnexpectedValueException
     */
    public function search(Query $query, string $debug = ''): array
    {
        $query->searchPhrase = htmlspecialchars_decode($query->searchPhrase, ENT_QUOTES);
        $query->apiVersion   = $this->pluginVersion;
        $request             = "{$this->baseUrl}/search/";

        $headers = ["X-Makaira-Instance: {$this->config['makaira_instance']}"];
        if ($debug) {
            $headers[] = "X-Makaira-Trace: {$debug}";
        }

        $response = $this->httpClient->request('POST', $request, json_encode($query), $headers);

        if ($response->status !== 200) {
            throw new ConnectException("Connect to '{$request}' failed. HTTP-Status {$response->status}");
        }

        $apiResult = json_decode($response->body, true);

        if (isset($apiResult['ok']) && $apiResult['ok'] === false) {
            throw new ConnectException("Error in Makaira: {$apiResult['message']}");
        }

        if (!isset($apiResult['product'])) {
            throw new UnexpectedValueException('Product results missing');
        }

        return array_map([$this, 'parseResult'], $apiResult);
    }

    /**
     * @param array $hits
     *
     * @return Result
     */
    private function parseResult(array $hits): Result
    {
        $hits['items'] = array_map(
            static function ($hit) {
                return new ResultItem($hit);
            },
            $hits['items']
        );

        $hits['aggregations'] = array_map(
            static function ($hit) {
                return new Aggregation($hit);
            },
            $hits['aggregations']
        );

        return new Result($hits);
    }
}
