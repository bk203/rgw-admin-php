<?php

namespace bk203\RgwAdminClient\Authentication;

use Http\Message\Authentication;
use Psr\Http\Message\RequestInterface;

class SignatureV2 implements Authentication
{
    /** @var string[] */
    private array $singableHeaders = ['Content-MD5', 'Content-Type'];

    public function __construct(private string $apiKey, private string $secretKey)
    {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $request = $request->withHeader('Date', gmdate(\DateTimeInterface::RFC2822));
        $signed = $this->signString($this->createCanonicalizedString($request), $this->secretKey);

        return $request->withHeader('Authorization', 'AWS '.$this->apiKey.':'.$signed);
    }

    public function signString(string $string, string $secretKey): string
    {
        return base64_encode(
            hash_hmac('sha1', $string, $secretKey, true)
        );
    }

    public function createCanonicalizedString(RequestInterface $request, ?string $expires = null): string
    {
        $buffer = $request->getMethod().PHP_EOL;

        // Add the interesting headers
        foreach ($this->singableHeaders as $header) {
            $buffer .= $request->getHeaderLine($header).PHP_EOL;
        }

        // Choose dates from left to right based on what's set
        $date = $expires ?: $request->getHeaderLine('date');

        $buffer .= "$date\n"
            .$this->createCanonicalizedAmzHeaders($request)
            .$this->createCanonicalizedResource($request);

        return $buffer;
    }

    private function createCanonicalizedAmzHeaders(RequestInterface $request): string
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $header) {
            $name = strtolower($name);
            if (str_starts_with($name, 'x-amz-')) {
                $value = trim((string)$header);
                if ($value) {
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

    private function createCanonicalizedResource(RequestInterface $request): string
    {
        return $request->getUri()->getPath();
    }
}
