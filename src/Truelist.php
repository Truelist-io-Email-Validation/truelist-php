<?php

declare(strict_types=1);

namespace Truelist;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Truelist\Exceptions\ApiException;
use Truelist\Exceptions\AuthenticationException;
use Truelist\Exceptions\RateLimitException;
use Truelist\Exceptions\TruelistException;

class Truelist
{
    private ClientInterface $httpClient;
    private string $baseUrl;
    private int $timeout;
    private int $maxRetries;
    private bool $raiseOnError;
    private ?string $formApiKey;

    public function __construct(
        private readonly string $apiKey,
        array $options = [],
        ?ClientInterface $httpClient = null,
    ) {
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.truelist.io', '/');
        $this->timeout = $options['timeout'] ?? 10;
        $this->maxRetries = $options['max_retries'] ?? 2;
        $this->raiseOnError = $options['raise_on_error'] ?? false;
        $this->formApiKey = $options['form_api_key'] ?? null;

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => $this->baseUrl,
            'timeout' => $this->timeout,
        ]);
    }

    public function validate(string $email): ValidationResult
    {
        return $this->performValidation('/api/v1/verify', $email, $this->apiKey);
    }

    public function formValidate(string $email): ValidationResult
    {
        $key = $this->formApiKey ?? throw new TruelistException(
            'Form API key is required for form validation. Set form_api_key in options.'
        );

        return $this->performValidation('/api/v1/form_verify', $email, $key);
    }

    public function account(): AccountInfo
    {
        $response = $this->requestWithRetry('GET', '/api/v1/account', $this->apiKey);
        $data = json_decode($response->getBody()->getContents(), true);

        return AccountInfo::fromArray($data);
    }

    private function performValidation(string $endpoint, string $email, string $token): ValidationResult
    {
        try {
            $response = $this->requestWithRetry('POST', $endpoint, $token, [
                'email' => $email,
            ]);
            $data = json_decode($response->getBody()->getContents(), true);

            return ValidationResult::fromArray($data);
        } catch (AuthenticationException $e) {
            throw $e;
        } catch (TruelistException $e) {
            if ($this->raiseOnError) {
                throw $e;
            }

            return ValidationResult::unknownError();
        }
    }

    private function requestWithRetry(string $method, string $uri, string $token, ?array $body = null): ResponseInterface
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $this->sendRequest($method, $uri, $token, $body);
            } catch (TruelistException $e) {
                if (!$this->isRetryable($e)) {
                    throw $e;
                }

                $lastException = $e;
                $attempt++;

                if ($attempt > $this->maxRetries) {
                    break;
                }

                $delay = (int) (100 * pow(2, $attempt - 1));
                usleep($delay * 1000);
            }
        }

        throw $lastException;
    }

    private function isRetryable(TruelistException $e): bool
    {
        if ($e instanceof AuthenticationException) {
            return false;
        }

        if ($e instanceof RateLimitException) {
            return true;
        }

        $code = $e->getCode();

        // Connection errors (code 0) and server errors (5xx) are retryable
        return $code === 0 || $code >= 500;
    }

    private function sendRequest(string $method, string $uri, string $token, ?array $body = null): ResponseInterface
    {
        $options = [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ];

        if ($body !== null) {
            $options['json'] = $body;
        }

        try {
            $response = $this->httpClient->request($method, $uri, $options);
        } catch (ConnectException $e) {
            throw new ApiException(
                "Connection error: {$e->getMessage()}",
                0,
                $e
            );
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response === null) {
                throw new ApiException(
                    "Request failed: {$e->getMessage()}",
                    0,
                    $e
                );
            }

            $this->handleErrorResponse($response);

            return $response;
        }

        return $response;
    }

    private function handleErrorResponse(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode === 401) {
            throw new AuthenticationException(
                'Invalid API key. Check your Truelist API credentials.',
                401
            );
        }

        if ($statusCode === 429) {
            throw new RateLimitException(
                'Rate limit exceeded. Please slow down your requests.',
                429
            );
        }

        if ($statusCode >= 500) {
            throw new ApiException(
                "Server error (HTTP {$statusCode})",
                $statusCode
            );
        }

        if ($statusCode >= 400) {
            $body = json_decode($response->getBody()->getContents(), true);
            $message = $body['message'] ?? "HTTP {$statusCode} error";
            throw new ApiException($message, $statusCode);
        }
    }
}
