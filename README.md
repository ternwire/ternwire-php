# Ternwire PHP SDK

Composer package `ternwire/ternwire`. Requires PHP 8.1+ with cURL, JSON, and hash
extensions.

```sh
composer require ternwire/ternwire
```

## Usage

```php
<?php
require 'vendor/autoload.php';

use Ternwire\TernwireClient;
use Ternwire\TernwireApiException;

$client = new TernwireClient(getenv('TERNWIRE_API_KEY'));
try {
    $message = $client->submitEmail([
        'from' => 'notifications@your-verified-domain.com',
        'to' => ['recipient@example.com'],
        'subject' => 'Your receipt',
        'text' => 'Thanks for your order.',
    ], 'order-123-receipt');
    print_r($client->getMessage($message['id']));
} catch (TernwireApiException $error) {
    echo $error->status;
    print_r($error->body);
}
```

The client calls the API at `TernwireClient::DEFAULT_BASE_URL`, `https://ternwire.com`.
Pass an origin as the second argument only to target a local or staging API, such as
`new TernwireClient($apiKey, 'http://localhost:3001')`. An origin must use https unless
it points at localhost or a loopback address, and must not include `/v1`. `getenv()`
returns `false` for an unset variable, so read an optional override with
`getenv('TERNWIRE_API_ORIGIN') ?: null`.

All 17 public operations use camelCase names. Bodies and responses are associative
arrays with camelCase JSON keys. Use `(object) []` for an empty JSON object in nested
fields such as `values`, `tags`, or `headers`. Template deletion returns `null`.
Set the timeout in seconds with `timeoutSeconds`, default 30, as in
`new TernwireClient($apiKey, timeoutSeconds: 10)`.
`listEmailTemplates(?string $cursor = null, ?int $limit = null)` returns one page of
summaries; pass the previous `page.nextCursor` as `$cursor` to fetch the next page.

API exceptions expose `status`, `body`, `rawBody`, and lowercase response `headers`.
Transport failures raise `RuntimeException`.

Use `Ternwire\Webhooks::verify($signatureHeader, $rawBody, $signingSecret)` before
parsing JSON. `$rawBody` is the binary-safe string read from `php://input`.
Tolerance is an optional fourth argument in seconds, default 300.

The complete example is `examples/send.php`. Set `TERNWIRE_API_KEY`, `TERNWIRE_FROM`,
and `TERNWIRE_TO` before running it. Set `TERNWIRE_API_ORIGIN` only to point it at a
local or staging API.

Read the full documentation at [ternwire.com/docs](https://ternwire.com/docs), or contact
support@ternwire.com. Released under the MIT License.
