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
    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var PluginClient
     */
    private $pluginClient;

    /**
     * @var UriFactoryInterface
     */
    private $uriFactory;

    /**
     * Create rgw admin client instance.
     *
     * @param array $options
     * @param array $collaborators
     *
     * @throws InvalidArgumentException when the required options are not set.
     * @throws NotFoundException when there is no valid MessageFactory or HttpClient found.
     */
    public function __construct(array $options = [], array $collaborators = [])
    {
        $this->assertRequiredOptions($options);
        $this->setOptions($options);
        $this->setCollaborators($collaborators);
    }

    /**
     * Set options.
     *
     * @param array $options
     *
     * @return void
     */
    protected function setOptions(array $options): void
    {
        $possible = $this->getRequiredOptions();

        $configured = array_intersect_key($options, array_flip($possible));

        foreach ($configured as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Set collaborators.
     *
     * @param array $collaborators
     *
     * @return void
     *
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
            $collaborators['uriFactory'] = Psr17FactoryDiscovery::findUrlFactory();
        }

        $this->setUriFactory($collaborators['uriFactory']);
    }

    /**
     * Set the UriFactory instance.
     *
     * @param UriFactoryInterface $uriFactory
     *
     * @return void
     */
    public function setUriFactory(UriFactoryInterface $uriFactory): void
    {
        $this->uriFactory = $uriFactory;
    }

    /**
     * Returns the current UriFactory instance.
     *
     * @return UriFactoryInterface
     */
    public function getUriFactory(): UriFactoryInterface
    {
        return $this->uriFactory;
    }

    /**
     * Set the RequestFactory instance.
     *
     * @param RequestFactoryInterface $messageFactory
     *
     * @return void
     */
    public function setRequestFactory(RequestFactoryInterface $requestFactory): void
    {
        $this->requestFactory = $requestFactory;
    }

    /**
     * Returns the current RequestFactory instance.
     *
     * @return RequestFactoryInterface
     */
    public function getRequestFactory(): RequestFactoryInterface
    {
        return $this->requestFactory;
    }

    /**
     * Set the HttpClient instance.
     *
     * @param HttpClient $httpClient
     *
     * @return void
     */
    public function setHttpClient(HttpClient $httpClient): void
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Returns the current HttpClient instance.
     *
     * @return PluginClient
     *
     * @throws RuntimeException
     */
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
     * Return all the required options.
     *
     * @return array
     */
    protected function getRequiredOptions(): array
    {
        return ['apiUrl', 'apiKey', 'secretKey'];
    }

    /**
     * Verifies that all required options have been provided.
     *
     * @param array $options
     *
     * @return void
     *
     * @throws InvalidArgumentException
     */
    private function assertRequiredOptions(array $options): void
    {
        $missing = array_diff_key(array_flip($this->getRequiredOptions()), $options);

        if (!empty($missing)) {
            throw new InvalidArgumentException(
                'Required option(s) not defined: '.implode(',', array_keys($missing))
            );
        }
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
     * @param array $options
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
     * Send the HTTP request and return the parsed response.
     *
     * @param RequestInterface $request
     *
     * @return mixed
     *
     * @throws Exception
     * @throws RuntimeException
     * @throws ClientExceptionInterface
     */
    public function sendRequest(RequestInterface $request)
    {
        $response = $this->getHttpClient()->sendRequest($request);

        return $this->parseResponse($response);
    }

    /**
     * Validate and parse response.
     *
     * @param ResponseInterface $response
     *
     * @return mixed
     *
     * @throws Exception
     */
    protected function parseResponse(ResponseInterface $response)
    {
        $readable = $response->getBody()->isReadable();

        if ($readable === false) {
            throw new Exception('Unable to parse response.');
        }

        return json_decode($response->getBody()->getContents());
    }

    /**
     * Create request and parse response.
     *
     * @param string $method
     * @param array $arguments
     *
     * @return RequestInterface
     *
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ClientExceptionInterface
     */
    public function __call(string $method, array $arguments): RequestInterface
    {
        $method = strtolower($method);

        if ($method !== 'delete' && $method !== 'get' &&
            $method !== 'post' && $method !== 'put'
        ) {
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
