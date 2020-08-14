<?php
namespace bk203\RgwAdminClient;

use Http\Client\Common\PluginClient;
use Http\Client\HttpClient;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;

interface ClientInterface
{
    /**
     * Set the UriFactory instance.
     *
     * @param UriFactoryInterface $uriFactory
     *
     * @return void
     */
    public function setUriFactory(UriFactoryInterface $uriFactory): void;

    /**
     * Returns the current UriFactory instance.
     *
     * @return UriFactoryInterface
     */
    public function getUriFactory(): UriFactoryInterface;

    /**
     * Set the RequestFactory instance.
     *
     * @param RequestFactoryInterface $messageFactory
     *
     * @return void
     */
    public function setRequestFactory(RequestFactoryInterface $messageFactory): void;

    /**
     * Returns the current MessageFactory instance.
     *
     * @return RequestFactoryInterface
     */
    public function getRequestFactory(): RequestFactoryInterface;

    /**
     * Set the HttpClient instance.
     *
     * @param HttpClient $httpClient
     *
     * @return void
     */
    public function setHttpClient(HttpClient $httpClient): void;

    /**
     * Returns the current HttpClient instance.
     *
     * @return PluginClient
     */
    public function getHttpClient(): PluginClient;

    /**
     * Create request object instance.
     *
     * @param string $command
     * @param string $method
     * @param array $options
     *
     * @return RequestInterface
     */
    public function createRequest(string $command, string $method, array $options = []): RequestInterface;

    /**
     * Send the HTTP request and return the parsed response.
     *
     * @param RequestInterface $request
     *
     * @return mixed
     */
    public function sendRequest(RequestInterface $request);
}
