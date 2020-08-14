<?php
namespace bk203\RgwAdminClient;

use Http\Client\Common\PluginClient;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Http\Message\UriFactory;
use Psr\Http\Message\RequestInterface;

interface ClientInterface
{
    /**
     * Set the UriFactory instance.
     *
     * @param UriFactory $uriFactory
     *
     * @return void
     */
    public function setUriFactory(UriFactory $uriFactory): void;

    /**
     * Returns the current UriFactory instance.
     *
     * @return UriFactory
     */
    public function getUriFactory(): UriFactory;

    /**
     * Set the MessageFactory instance.
     *
     * @param MessageFactory $messageFactory
     *
     * @return void
     */
    public function setMessageFactory(MessageFactory $messageFactory): void;

    /**
     * Returns the current MessageFactory instance.
     *
     * @return MessageFactory
     */
    public function getMessageFactory(): MessageFactory;

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
