# Truelist PHP SDK

[![Free tier](https://img.shields.io/badge/free_plan-100_validations-4A7C59?style=flat-square)](https://truelist.io/pricing)
PHP SDK for the [Truelist](https://truelist.io) email validation API.

[![CI](https://github.com/Truelist-io-Email-Validation/truelist-php/actions/workflows/ci.yml/badge.svg)](https://github.com/Truelist-io-Email-Validation/truelist-php/actions/workflows/ci.yml)

## Requirements

- PHP 8.1+
- Guzzle 7.0+

> **Start free** — 100 validations + 10 enhanced credits, no credit card required.
> [Get your API key →](https://app.truelist.io/signup?utm_source=github&utm_medium=readme&utm_campaign=free-plan&utm_content=truelist-php)

## Installation

```bash
composer require truelist/truelist-php
```

## Quick Start

```php
use Truelist\Truelist;

$client = new Truelist('your-api-key');

$result = $client->validate('user@example.com');

if ($result->isValid()) {
    echo "Email is valid!";
}
```

## Configuration

```php
$client = new Truelist('your-api-key', [
    'base_url'       => 'https://api.truelist.io', // API base URL
    'timeout'        => 10,                         // Request timeout in seconds
    'max_retries'    => 2,                          // Retries on 429/5xx errors
    'raise_on_error' => false,                      // Throw on transient errors
]);
```

| Option | Default | Description |
|--------|---------|-------------|
| `base_url` | `https://api.truelist.io` | API base URL |
| `timeout` | `10` | Request timeout in seconds |
| `max_retries` | `2` | Number of retries on 429/5xx errors (with exponential backoff) |
| `raise_on_error` | `false` | When `false`, transient errors return an unknown result. When `true`, they throw. |

## Methods

### `validate(string $email): ValidationResult`

Validates an email address. Sends a `POST` to `/api/v1/verify_inline` with the email as a query parameter.

```php
$result = $client->validate('user@example.com');

$result->email;       // 'user@example.com'
$result->state;       // 'ok', 'email_invalid', 'accept_all', 'unknown'
$result->subState;    // 'email_ok', 'is_disposable', 'is_role', etc.
$result->suggestion;  // Suggested correction or null
$result->domain;      // 'example.com'
$result->canonical;   // 'user'
$result->mxRecord;    // MX record or null
$result->firstName;   // First name or null
$result->lastName;    // Last name or null
$result->verifiedAt;  // ISO 8601 timestamp or null
```

### `account(): AccountInfo`

Retrieves account information from `GET /me`.

```php
$account = $client->account();

$account->email;        // 'team@company.com'
$account->name;         // 'Team Lead'
$account->uuid;         // 'a3828d19-...'
$account->timeZone;     // 'America/New_York'
$account->isAdminRole;  // true
$account->accountName;  // 'Company Inc'
$account->paymentPlan;  // 'pro'
```

## Result Predicates

```php
$result = $client->validate('user@example.com');

// State checks
$result->isValid();       // true if state is 'ok'
$result->isInvalid();     // true if state is 'email_invalid'
$result->isAcceptAll();   // true if state is 'accept_all'
$result->isUnknown();     // true if state is 'unknown'
$result->isError();       // true if result came from a transient error

// Sub-state checks
$result->isRole();        // true if sub-state is 'is_role'
$result->isDisposable();  // true if sub-state is 'is_disposable'
```

## Response States

| State | Description |
|-------|-------------|
| `ok` | Email is valid and deliverable |
| `email_invalid` | Email is not deliverable |
| `accept_all` | Domain accepts all emails (catch-all) |
| `unknown` | Could not determine validity |

## Response Sub-States

| Sub-State | Description |
|-----------|-------------|
| `email_ok` | Email is valid |
| `is_disposable` | Disposable/temporary email |
| `is_role` | Role-based address (info@, admin@) |
| `failed_smtp_check` | SMTP check failed |
| `unknown_error` | Could not determine |

## Error Handling

The SDK uses a hierarchy of exceptions:

- `TruelistException` -- base exception
  - `AuthenticationException` -- invalid API key (401). **Always thrown, never suppressed.**
  - `RateLimitException` -- rate limit exceeded (429)
  - `ApiException` -- server errors (5xx), connection errors

### Auth Errors Always Throw

Authentication errors (HTTP 401) always throw an `AuthenticationException`, regardless of the `raise_on_error` setting.

```php
use Truelist\Exceptions\AuthenticationException;

try {
    $result = $client->validate('user@example.com');
} catch (AuthenticationException $e) {
    // Invalid API key - always thrown
}
```

### Transient Error Behavior

For transient errors (429, 5xx, timeouts), behavior depends on `raise_on_error`:

```php
// raise_on_error: false (default)
// Returns a ValidationResult with state='unknown' and error=true
$result = $client->validate('user@example.com');
if ($result->isError()) {
    // Handle transient failure gracefully
}

// raise_on_error: true
// Throws RateLimitException or ApiException
$client = new Truelist('key', ['raise_on_error' => true]);
try {
    $result = $client->validate('user@example.com');
} catch (RateLimitException $e) {
    // 429
} catch (ApiException $e) {
    // 5xx or connection error
}
```

### Retry Behavior

The SDK automatically retries on 429 and 5xx errors with exponential backoff. Configure with `max_retries` (default: 2). Auth errors (401) are never retried.

## Testing

```bash
composer install
vendor/bin/phpunit
```


## Getting Started

Sign up for a [free Truelist account](https://app.truelist.io/signup?utm_source=github&utm_medium=readme&utm_campaign=free-plan&utm_content=truelist-php) to get your API key. The free plan includes 100 validations and 10 enhanced credits — no credit card required.
## License

MIT
