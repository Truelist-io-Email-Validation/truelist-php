<?php

declare(strict_types=1);

namespace Truelist\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Truelist\Truelist;
use Truelist\ValidationResult;
use Truelist\AccountInfo;
use Truelist\Exceptions\AuthenticationException;
use Truelist\Exceptions\RateLimitException;
use Truelist\Exceptions\ApiException;
use Truelist\Exceptions\TruelistException;

class TruelistTest extends TestCase
{
    private function createClient(array $responses, array &$history = [], array $options = []): Truelist
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($history));

        $httpClient = new Client(['handler' => $handlerStack]);

        return new Truelist('test-api-key', $options, $httpClient);
    }

    private function validResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'emails' => [[
                'address' => 'user@example.com',
                'domain' => 'example.com',
                'canonical' => 'user',
                'mx_record' => null,
                'first_name' => null,
                'last_name' => null,
                'email_state' => 'ok',
                'email_sub_state' => 'email_ok',
                'verified_at' => '2026-02-21T10:00:00.000Z',
                'did_you_mean' => null,
            ]],
        ]));
    }

    private function invalidResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'emails' => [[
                'address' => 'bad@example.com',
                'domain' => 'example.com',
                'canonical' => 'bad',
                'mx_record' => null,
                'first_name' => null,
                'last_name' => null,
                'email_state' => 'email_invalid',
                'email_sub_state' => 'failed_smtp_check',
                'verified_at' => '2026-02-21T10:00:00.000Z',
                'did_you_mean' => null,
            ]],
        ]));
    }

    private function acceptAllResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'emails' => [[
                'address' => 'catchall@example.com',
                'domain' => 'example.com',
                'canonical' => 'catchall',
                'mx_record' => null,
                'first_name' => null,
                'last_name' => null,
                'email_state' => 'accept_all',
                'email_sub_state' => 'email_ok',
                'verified_at' => '2026-02-21T10:00:00.000Z',
                'did_you_mean' => null,
            ]],
        ]));
    }

    private function unknownResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'emails' => [[
                'address' => 'unknown@example.com',
                'domain' => 'example.com',
                'canonical' => 'unknown',
                'mx_record' => null,
                'first_name' => null,
                'last_name' => null,
                'email_state' => 'unknown',
                'email_sub_state' => 'unknown_error',
                'verified_at' => null,
                'did_you_mean' => null,
            ]],
        ]));
    }

    private function accountResponse(): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'email' => 'team@company.com',
            'name' => 'Team Lead',
            'uuid' => 'a3828d19-1234-5678-9abc-def012345678',
            'time_zone' => 'America/New_York',
            'is_admin_role' => true,
            'token' => 'test_token',
            'api_keys' => [],
            'account' => [
                'name' => 'Company Inc',
                'payment_plan' => 'pro',
                'users' => [],
            ],
        ]));
    }

    private function errorResponse(int $status, string $message = 'Error'): RequestException
    {
        $response = new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'message' => $message,
        ]));

        return new RequestException($message, new Request('POST', '/api/v1/verify_inline'), $response);
    }

    // --- Validate Tests ---

    public function test_validate_valid_email(): void
    {
        $history = [];
        $client = $this->createClient([$this->validResponse()], $history);

        $result = $client->validate('user@example.com');

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
        $this->assertSame('ok', $result->state);
        $this->assertSame('email_ok', $result->subState);
        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('example.com', $result->domain);

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/v1/verify_inline', $request->getUri()->getPath());
        $this->assertSame('email=user%40example.com', $request->getUri()->getQuery());
        $this->assertSame('Bearer test-api-key', $request->getHeaderLine('Authorization'));
    }

    public function test_validate_invalid_email(): void
    {
        $client = $this->createClient([$this->invalidResponse()]);

        $result = $client->validate('bad@example.com');

        $this->assertTrue($result->isInvalid());
        $this->assertSame('email_invalid', $result->state);
        $this->assertSame('failed_smtp_check', $result->subState);
    }

    public function test_validate_accept_all_email(): void
    {
        $client = $this->createClient([$this->acceptAllResponse()]);

        $result = $client->validate('catchall@example.com');

        $this->assertTrue($result->isAcceptAll());
        $this->assertFalse($result->isValid());
    }

    public function test_validate_unknown_email(): void
    {
        $client = $this->createClient([$this->unknownResponse()]);

        $result = $client->validate('unknown@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertFalse($result->isValid());
    }

    // --- Account Tests ---

    public function test_account_returns_account_info(): void
    {
        $history = [];
        $client = $this->createClient([$this->accountResponse()], $history);

        $account = $client->account();

        $this->assertInstanceOf(AccountInfo::class, $account);
        $this->assertSame('team@company.com', $account->email);
        $this->assertSame('Team Lead', $account->name);
        $this->assertSame('a3828d19-1234-5678-9abc-def012345678', $account->uuid);
        $this->assertSame('America/New_York', $account->timeZone);
        $this->assertTrue($account->isAdminRole);
        $this->assertSame('Company Inc', $account->accountName);
        $this->assertSame('pro', $account->paymentPlan);

        $request = $history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/me', $request->getUri()->getPath());
        $this->assertSame('Bearer test-api-key', $request->getHeaderLine('Authorization'));
    }

    public function test_account_throws_on_auth_error(): void
    {
        $client = $this->createClient([$this->errorResponse(401, 'Unauthorized')]);

        $this->expectException(AuthenticationException::class);

        $client->account();
    }

    // --- Error Handling Tests ---

    public function test_401_always_throws_regardless_of_raise_on_error(): void
    {
        $client = $this->createClient(
            [$this->errorResponse(401, 'Unauthorized')],
            options: ['raise_on_error' => false]
        );

        $this->expectException(AuthenticationException::class);

        $client->validate('user@example.com');
    }

    public function test_401_always_throws_with_raise_on_error_true(): void
    {
        $client = $this->createClient(
            [$this->errorResponse(401, 'Unauthorized')],
            options: ['raise_on_error' => true]
        );

        $this->expectException(AuthenticationException::class);

        $client->validate('user@example.com');
    }

    public function test_429_returns_unknown_when_raise_on_error_false(): void
    {
        $responses = array_fill(0, 3, $this->errorResponse(429, 'Rate limited'));
        $client = $this->createClient(
            $responses,
            options: ['raise_on_error' => false, 'max_retries' => 0]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    public function test_429_throws_when_raise_on_error_true(): void
    {
        $client = $this->createClient(
            [$this->errorResponse(429, 'Rate limited')],
            options: ['raise_on_error' => true, 'max_retries' => 0]
        );

        $this->expectException(RateLimitException::class);

        $client->validate('user@example.com');
    }

    public function test_500_returns_unknown_when_raise_on_error_false(): void
    {
        $client = $this->createClient(
            [$this->errorResponse(500, 'Server error')],
            options: ['raise_on_error' => false, 'max_retries' => 0]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    public function test_500_throws_when_raise_on_error_true(): void
    {
        $client = $this->createClient(
            [$this->errorResponse(500, 'Server error')],
            options: ['raise_on_error' => true, 'max_retries' => 0]
        );

        $this->expectException(ApiException::class);

        $client->validate('user@example.com');
    }

    public function test_connection_error_returns_unknown_when_raise_on_error_false(): void
    {
        $exception = new ConnectException(
            'Connection refused',
            new Request('POST', '/api/v1/verify_inline')
        );

        $client = $this->createClient(
            [$exception],
            options: ['raise_on_error' => false, 'max_retries' => 0]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    public function test_connection_error_throws_when_raise_on_error_true(): void
    {
        $exception = new ConnectException(
            'Connection refused',
            new Request('POST', '/api/v1/verify_inline')
        );

        $client = $this->createClient(
            [$exception],
            options: ['raise_on_error' => true, 'max_retries' => 0]
        );

        $this->expectException(ApiException::class);

        $client->validate('user@example.com');
    }

    // --- Retry Tests ---

    public function test_retries_on_429_then_succeeds(): void
    {
        $history = [];
        $client = $this->createClient(
            [
                $this->errorResponse(429, 'Rate limited'),
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isValid());
        $this->assertCount(2, $history);
    }

    public function test_retries_on_500_then_succeeds(): void
    {
        $history = [];
        $client = $this->createClient(
            [
                $this->errorResponse(500, 'Server error'),
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isValid());
        $this->assertCount(2, $history);
    }

    public function test_respects_max_retries_limit(): void
    {
        $history = [];
        $responses = array_fill(0, 4, $this->errorResponse(500, 'Server error'));
        $client = $this->createClient(
            $responses,
            $history,
            ['max_retries' => 2, 'raise_on_error' => true]
        );

        $this->expectException(ApiException::class);

        $client->validate('user@example.com');
    }

    public function test_does_not_retry_on_401(): void
    {
        $history = [];
        $client = $this->createClient(
            [
                $this->errorResponse(401, 'Unauthorized'),
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2]
        );

        try {
            $client->validate('user@example.com');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            $this->assertCount(1, $history);
        }
    }

    public function test_retry_with_no_retries_configured(): void
    {
        $history = [];
        $client = $this->createClient(
            [$this->errorResponse(500, 'Server error')],
            $history,
            ['max_retries' => 0, 'raise_on_error' => true]
        );

        $this->expectException(ApiException::class);

        $client->validate('user@example.com');
    }

    public function test_does_not_retry_on_400(): void
    {
        $history = [];
        $client = $this->createClient(
            [
                $this->errorResponse(400, 'Bad request'),
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2, 'raise_on_error' => true]
        );

        try {
            $client->validate('user@example.com');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertCount(1, $history);
            $this->assertSame(400, $e->getCode());
        }
    }

    public function test_does_not_retry_on_403(): void
    {
        $history = [];
        $client = $this->createClient(
            [
                $this->errorResponse(403, 'Forbidden'),
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2, 'raise_on_error' => true]
        );

        try {
            $client->validate('user@example.com');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertCount(1, $history);
            $this->assertSame(403, $e->getCode());
        }
    }

    public function test_does_not_retry_on_404(): void
    {
        $history = [];
        $client = $this->createClient(
            [
                $this->errorResponse(404, 'Not found'),
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2, 'raise_on_error' => true]
        );

        try {
            $client->validate('user@example.com');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertCount(1, $history);
            $this->assertSame(404, $e->getCode());
        }
    }

    public function test_400_returns_unknown_when_raise_on_error_false(): void
    {
        $client = $this->createClient(
            [$this->errorResponse(400, 'Bad request')],
            options: ['raise_on_error' => false, 'max_retries' => 2]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isUnknown());
        $this->assertTrue($result->isError());
    }

    public function test_retries_connection_errors(): void
    {
        $history = [];
        $connectException = new ConnectException(
            'Connection refused',
            new Request('POST', '/api/v1/verify_inline')
        );

        $client = $this->createClient(
            [
                $connectException,
                $this->validResponse(),
            ],
            $history,
            ['max_retries' => 2]
        );

        $result = $client->validate('user@example.com');

        $this->assertTrue($result->isValid());
        $this->assertCount(2, $history);
    }

    // --- Request Format Tests ---

    public function test_sends_json_content_type(): void
    {
        $history = [];
        $client = $this->createClient([$this->validResponse()], $history);

        $client->validate('user@example.com');

        $request = $history[0]['request'];
        $this->assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function test_sends_email_as_query_param(): void
    {
        $history = [];
        $client = $this->createClient([$this->validResponse()], $history);

        $client->validate('test@example.com');

        $request = $history[0]['request'];
        $this->assertSame('email=test%40example.com', $request->getUri()->getQuery());
        $this->assertSame('/api/v1/verify_inline', $request->getUri()->getPath());
    }

    // --- Account Error Tests ---

    public function test_account_throws_on_server_error_with_retries_exhausted(): void
    {
        $responses = array_fill(0, 3, $this->errorResponse(500, 'Server error'));
        $client = $this->createClient($responses, options: ['max_retries' => 2]);

        $this->expectException(ApiException::class);

        $client->account();
    }
}
