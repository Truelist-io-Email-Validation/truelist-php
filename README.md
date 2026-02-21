# Truelist PHP SDK

PHP SDK for the [Truelist](https://truelist.io) email validation API.

[![CI](https://github.com/Truelist-io-Email-Validation/truelist-php/actions/workflows/ci.yml/badge.svg)](https://github.com/Truelist-io-Email-Validation/truelist-php/actions/workflows/ci.yml)

## Requirements

- PHP 8.1+
- Guzzle 7.0+

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
    'form_api_key'   => 'your-form-key',            // API key for form validation
]);
```

| Option | Default | Description |
|--------|---------|-------------|
| `base_url` | `https://api.truelist.io` | API base URL |
| `timeout` | `10` | Request timeout in seconds |
| `max_retries` | `2` | Number of retries on 429/5xx errors (with exponential backoff) |
| `raise_on_error` | `false` | When `false`, transient errors return an unknown result. When `true`, they throw. |
| `form_api_key` | `null` | Separate API key for frontend/form validation |

## Methods

### `validate(string $email): ValidationResult`

Validates an email address using the server-side API.

```php
$result = $client->validate('user@example.com');

$result->state;       // 'valid', 'invalid', 'risky', 'unknown'
$result->subState;    // 'ok', 'accept_all', 'disposable_address', etc.
$result->suggestion;  // Suggested correction or null
$result->freeEmail;   // true if free email provider
$result->role;        // true if role address (e.g. info@)
$result->disposable;  // true if disposable email
```

### `formValidate(string $email): ValidationResult`

Validates an email using the form validation endpoint. Requires `form_api_key` to be set.

```php
$client = new Truelist('server-key', [
    'form_api_key' => 'your-form-key',
]);

$result = $client->formValidate('user@example.com');
```

### `account(): AccountInfo`

Retrieves account information.

```php
$account = $client->account();

$account->email;    // 'you@example.com'
$account->plan;     // 'pro'
$account->credits;  // 9542
```

## Result Predicates

```php
$result = $client->validate('user@example.com');

// State checks
$result->isValid();                   // true if state is 'valid'
$result->isValid(allowRisky: true);   // true if state is 'valid' or 'risky'
$result->isInvalid();                 // true if state is 'invalid'
$result->isRisky();                   // true if state is 'risky'
$result->isUnknown();                 // true if state is 'unknown'
$result->isError();                   // true if result came from a transient error

// Email attribute checks
$result->isFreeEmail();               // true if free email provider
$result->isRole();                    // true if role address
$result->isDisposable();              // true if disposable email
```

## Response States

| State | Description |
|-------|-------------|
| `valid` | Email is valid and deliverable |
| `invalid` | Email is not deliverable |
| `risky` | Email is deliverable but risky (accept-all, role, etc.) |
| `unknown` | Could not determine validity |

## Response Sub-States

| Sub-State | Description |
|-----------|-------------|
| `ok` | Email is valid |
| `accept_all` | Domain accepts all emails |
| `disposable_address` | Disposable/temporary email |
| `role_address` | Role-based address (info@, admin@) |
| `failed_mx_check` | No valid MX records |
| `failed_spam_trap` | Known spam trap |
| `failed_no_mailbox` | Mailbox does not exist |
| `failed_greylisted` | Server returned temporary error |
| `failed_syntax_check` | Invalid email syntax |
| `unknown` | Could not determine |

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

## License

MIT
