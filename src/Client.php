<?php

namespace bk203\RgwAdminClient;

use bk203\RgwAdminClient\Authentication\SignatureV2;
use Exception;
use Http\Client\Common\Plugin\AuthenticationPlugin;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpClient;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use InvalidArgumentException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

class Client implements ClientInterface
{
    private RequestFactoryInterface $requestFactory;
    private HttpClient $httpClient;
    private ?PluginClient $pluginClient = null;
    private UriFactoryInterface $uriFactory;

    /**
     * Client constructor.
     * @param string $apiUrl
     * @param string $apiKey
     * @param string $secretKey
     * @param array<string, object> $collaborators
     *
     * @throws NotFoundException when there is no valid MessageFactory or HttpClient found.
     */
    public function __construct(
        private string $apiUrl,
        private string $apiKey,
        private string $secretKey,
        array $collaborators = []
    ) {
        $this->setCollaborators($collaborators);
    }

    /**
     * @param array<string, object> $collaborators
     * @throws NotFoundException when there is no MessageFactory, HttpClient or UriFactory found.
     */
    protected function setCollaborators(array $collaborators): void
    {
        if (empty($collaborators['requestFactory'])) {
            $collaborators['requestFactory'] = Psr17FactoryDiscovery::findRequestFactory();
        }

        $this->setRequestFactory($collaborators['requestFactory']);

        if (empty($collaborators['httpClient'])) {
            $collaborators['httpClient'] = HttpClientDiscovery::find();
        }

        $this->setHttpClient($collaborators['httpClient']);

        if (empty($collaborators['uriFactory'])) {
            $collaborators['uriFactory'] = Psr17FactoryDiscovery::findUriFactory();
        }

        $this->setUriFactory($collaborators['uriFactory']);
    }

    public function setUriFactory(UriFactoryInterface $uriFactory): void
    {
        $this->uriFactory = $uriFactory;
    }

    public function getUriFactory(): UriFactoryInterface
    {
        return $this->uriFactory;
    }

    public function setRequestFactory(RequestFactoryInterface $requestFactory): void
    {
        $this->requestFactory = $requestFactory;
    }

    public function getRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    public function setHttpClient(HttpClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    public function getHttpClient(): PluginClient
    {
        if ($this->pluginClient !== null) {
            return $this->pluginClient;
        }

        $plugins = [
            new ErrorPlugin(),
            new AuthenticationPlugin(
                new SignatureV2($this->apiKey, $this->secretKey)
            ),
        ];

        $this->pluginClient = new PluginClient($this->httpClient, $plugins);

        return $this->pluginClient;
    }

    /**
     * Create uri object instance.
     *
     * @param string $command
     * @param array $options
     *
     * @return UriInterface
     *
     * @throws InvalidArgumentException
     */
    protected function buildUri(string $command, array $options): UriInterface
    {
        $baseUrl = $this->apiUrl.'/'.$command;

        $options['format'] = 'json';

        $baseUrl .= '?'.http_build_query($options);

        return $this->getUriFactory()->createUri($baseUrl);
    }

    /**
     * Create request object instance.
     *
     * @param string $command
     * @param string $method
     * @param array<string, string|int|bool> $options
     *
     * @return RequestInterface
     *
     * @throws InvalidArgumentException
     */
    public function createRequest(string $command, string $method, array $options = []): RequestInterface
    {
        $uri = $this->buildUri($command, $options);

        return $this->getRequestFactory()->createRequest($method, $uri);
    }

    /**
     * Send the HTTP request and return the validated response.
     *
     * @param RequestInterface $request
     *
     * @return string
     *
     * @throws Exception
     * @throws RuntimeException
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request): string
    {
        $response = $this->getHttpClient()->sendRequest($request);

        return $this->validateResponse($response);
    }

    /**
     * Validate and parse response.
     *
     * @param ResponseInterface $response
     *
     * @return string
     *
     * @throws Exception
     */
    protected function validateResponse(ResponseInterface $response): string
    {
        $readable = $response->getBody()->isReadable();

        if ($readable === false) {
            throw new Exception('Unable to parse response.');
        }

        return $response->getBody()->getContents();
    }

    /**
     * Create request and parse response.
     *
     * @param string $method
     * @param array $arguments
     *
     * @return string
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ClientExceptionInterface
     */
    public function __call(string $method, array $arguments): string
    {
        $method = strtolower($method);

        if (!in_array($method, ['delete','get','post','put'])) {
            throw new InvalidArgumentException('Unsupported HTTP method specified.');
        }

        if (empty($arguments[0])) {
            throw new InvalidArgumentException('No resource specified.');
        }

        list ($resource, $parameters) = $arguments;

        return $this->sendRequest(
            $this->createRequest($resource, $method, $parameters)
        );
    }
}
