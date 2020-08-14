<?php

namespace bk203\RgwAdminClient\Authentication;

use Http\Message\Authentication;
use Psr\Http\Message\RequestInterface;

class SignatureV2 implements Authentication
{
    /**
     * @var array
     */
    private $singableHeaders = ['Content-MD5', 'Content-Type'];

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @param string $apiKey
     * @param string $secretKey
     */
    public function __construct($apiKey, $secretKey)
    {
        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
    }

    /**
     * Authenticates a request.
     *
     * @param RequestInterface $request
     *
     * @return RequestInterface
     */
    public function authenticate(RequestInterface $request): RequestInterface
    {
        $request = $request->withHeader('Date', gmdate(\DateTime::RFC2822));

        $signed = $this->signString($this->createCanonicalizedString($request), $this->secretKey);

        $request = $request->withHeader('Authorization', 'AWS '.$this->apiKey.':'.$signed);

        return $request;
    }

    /**
     * Sign the provided string with the secret key of the user.
     *
     * @param string $string
     * @param string $secretKey
     *
     * @return string
     */
    public function signString(string $string, string $secretKey): string
    {
        return base64_encode(
            hash_hmac('sha1', $string, $secretKey, true)
        );
    }

    /**
     * @param RequestInterface $request
     * @param string|null $expires
     *
     * @return string
     */
    public function createCanonicalizedString(RequestInterface $request, ?string $expires = null): string
    {
        $buffer = $request->getMethod().PHP_EOL;

        // Add the interesting headers
        foreach ($this->singableHeaders as $header) {
            $buffer .= (string)$request->getHeaderLine($header).PHP_EOL;
        }

        // Choose dates from left to right based on what's set
        $date = $expires ?: (string)$request->getHeaderLine('date');

        $buffer .= "{$date}\n"
            .$this->createCanonicalizedAmzHeaders($request)
            .$this->createCanonicalizedResource($request);

        return $buffer;
    }

    /**
     * Create a canonicalized AmzHeaders string for a signature.
     *
     * @param RequestInterface $request Request from which to gather headers
     *
     * @return string Returns canonicalized AMZ headers.
     */
    private function createCanonicalizedAmzHeaders(RequestInterface $request): string
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $header) {
            $name = strtolower($name);
            if (strpos($name, 'x-amz-') === 0) {
                $value = trim((string)$header);
                if ($value || $value === '0') {
                    $headers[$name] = $name.':'.$value;
                }
            }
        }

        if (!$headers) {
            return '';
        }

        ksort($headers);

        return implode(PHP_EOL, $headers).PHP_EOL;
    }

    /**
     * Create a canonicalized resource for a request.
     *
     * @param RequestInterface $request Request for the resource
     *
     * @return string
     */
    private function createCanonicalizedResource(RequestInterface $request): string
    {
        return $request->getUri()->getPath();
    }
}
