<?php

namespace MakairaConnect\Client;

use Makaira\Aggregation;
use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Constraints;
use Makaira\HttpClient;
use Makaira\Query;
use Makaira\Result;
use Makaira\ResultItem;
use function explode;
use function get_class;
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
     * @var string
     */
    private $pluginVersion;

    /**
     * @var array
     */
    private $defaultHeaders;

    /**
     * Api constructor.
     *
     * @param HttpClient $httpClient
     * @param array      $config
     * @param string     $pluginVersion
     */
    public function __construct(HttpClient $httpClient, array $config, string $pluginVersion)
    {
        $this->baseUrl        = rtrim($config['makaira_application_url'], '/');
        $this->httpClient     = $httpClient;
        $this->pluginVersion  = $pluginVersion;
        $this->defaultHeaders = ["X-Makaira-Instance: {$config['makaira_instance']}"];
    }

    /**
     * @inheritDoc
     */
    public function fetchFilter(): array
    {
        $request = "{$this->baseUrl}/aggregation?_end=1000&_order=ASC&_sort=position&_start=0";

        $response = $this->httpClient->request('GET', $request, '{}', $this->defaultHeaders);

        return (array) json_decode($response->body, true);
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

        $this->sanatizeLanguage($query);

        $request = "{$this->baseUrl}/search/";

        $headers = $this->defaultHeaders;
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

    private function sanatizeLanguage(Query $query)
    {
        [$language,] = explode('_', $query->constraints[Constraints::LANGUAGE]);
        $query->constraints[Constraints::LANGUAGE] = $language;
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
            $hits['items'] ?? []
        );

        $hits['aggregations'] = array_map(
            static function ($hit) {
                return new Aggregation($hit);
            },
            $hits['aggregations'] ?? []
        );

        return new Result($hits);
    }
}
