<?php

namespace MakairaConnect\Client;

use Makaira\AbstractQuery;
use Makaira\Aggregation;
use Makaira\Connect\Exception as ConnectException;
use Makaira\Connect\Exceptions\UnexpectedValueException;
use Makaira\Constraints;
use Makaira\HttpClient;
use Makaira\Query;
use Makaira\RecommendationQuery;
use Makaira\Result;
use Makaira\ResultItem;
use function compact;
use function explode;
use function htmlspecialchars_decode;
use function json_decode;
use function json_encode;
use const ENT_QUOTES;
use const JSON_PRETTY_PRINT;

class Api implements ApiInterface
{
    const MAKAIRA_EXPERIMENT_COOKIE_NAME = 'makairaExperiments';
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
     * @param array $config
     * @param string $pluginVersion
     */
    public function __construct(HttpClient $httpClient, array $config, string $pluginVersion)
    {
        $this->baseUrl        = rtrim($config['makaira_application_url'], '/');
        $this->httpClient     = $httpClient;
        $this->pluginVersion  = $pluginVersion;
        $this->defaultHeaders = ["X-Makaira-Instance: {$config['makaira_instance']}"];
    }

    private function callApi($method, $url, $body, $headers): HttpClient\Response
    {
        // Get experiment from session and send to Makaira if exists
        $makairaExperiments = Shopware()->Front()->Request()->getCookie(self::MAKAIRA_EXPERIMENT_COOKIE_NAME);
        if ($makairaExperiments !== null && $body instanceof AbstractQuery) {
            $body->setConstraint(Constraints::AB_EXPERIMENTS, json_decode($makairaExperiments, true));
        }

        // Send request to Makaira
        $response = $this->httpClient->request($method, $url, json_encode($body), $headers);

        // Store experiments into session
        $body = json_decode($response->body, true);
        if (isset($body['experiments'])) {
            Shopware()->Front()->Response()->setCookie(self::MAKAIRA_EXPERIMENT_COOKIE_NAME, json_encode($body['experiments']));
        } else {
            Shopware()->Front()->Response()->removeCookie(self::MAKAIRA_EXPERIMENT_COOKIE_NAME);
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function fetchFilter(
        string $sort = 'id',
        string $direction = 'ASC',
        int $offset = 0,
        int $count = 10000
    ): array {
        $request = "{$this->baseUrl}/filter";

        $body = compact('sort', 'direction', 'offset', 'count');

        $response = $this->callApi('POST', $request, $body, $this->defaultHeaders);

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

        $this->sanitizeLanguage($query);

        $request = "{$this->baseUrl}/search/";

        $headers = $this->defaultHeaders;
        if ($debug) {
            $headers[] = "X-Makaira-Trace: {$debug}";
        }

        $response = $this->callApi('POST', $request, $query, $headers);
        $apiResult = json_decode($response->body, true);

        if ($response->status !== 200) {
            $message = "Connect to '{$request}' failed. HTTP-Status {$response->status}";
            if (null !== $apiResult) {
                $message .= "\n\n" . json_encode($apiResult, JSON_PRETTY_PRINT);
            }
            throw new ConnectException($message);
        }

        if (isset($apiResult['ok']) && $apiResult['ok'] === false) {
            throw new ConnectException("Error in Makaira: {$apiResult['message']}");
        }

        if (!isset($apiResult['product'])) {
            throw new UnexpectedValueException('Product results missing');
        }

        unset($apiResult['experiments']);

        return array_map([$this, 'parseResult'], $apiResult);
    }

    private function sanitizeLanguage(AbstractQuery $query)
    {
        [$language,] = explode('_', $query->constraints[Constraints::LANGUAGE]);
        $query->constraints[Constraints::LANGUAGE] = $language;
    }

    /**
     * @param array|null $hits
     *
     * @return Result
     */
    private function parseResult(?array $hits): ?Result
    {
        if (null === $hits) {
            return $hits;
        }

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

    /**
     * @throws ConnectException
     */
    public function getRecommendedProducts(RecommendationQuery $query, string $debug = ''): array
    {
        $this->sanitizeLanguage($query);

        $url = "{$this->baseUrl}/recommendation/public";

        $headers = $this->defaultHeaders;
        if (!empty($debug)) {
            $headers[] = "X-Makaira-Trace: {$debug}";
        }

        $response = $this->httpClient->request('POST', $url, json_encode($query), $headers);
        $apiResult = json_decode($response->body, true);

        if ($response->status !== 200) {
            $message = "Connect to '{$url}' failed. HTTP-Status {$response->status}";
            if (null !== $apiResult) {
                $message .= "\n\n" . json_encode($apiResult, JSON_PRETTY_PRINT);
            }
            throw new ConnectException($message);
        }

        return $apiResult['items'];
    }

    /**
     * @throws ConnectException
     */
    public function getMakairaRecommendations()
    {
        $url = "{$this->baseUrl}/reco";

        $headers = $this->defaultHeaders;
        if (!empty($debug)) {
            $headers[] = "X-Makaira-Trace: {$debug}";
        }

        $response = $this->httpClient->request('GET', $url, null, $headers);
        $apiResult = json_decode($response->body, true);

        if ($response->status !== 200) {
            $message = "Connect to '{$url}' failed. HTTP-Status {$response->status}";
            if (null !== $apiResult) {
                $message .= "\n\n" . json_encode($apiResult, JSON_PRETTY_PRINT);
            }
            throw new ConnectException($message);
        }

        return $apiResult;
    }
}
