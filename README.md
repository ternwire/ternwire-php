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

$client = new TernwireClient(getenv('TERNWIRE_API_KEY'), getenv('TERNWIRE_API_ORIGIN'));
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

All 17 public operations use camelCase names. Bodies and responses are associative
arrays with camelCase JSON keys. Use `(object) []` for an empty JSON object in nested
fields such as `values`, `tags`, or `headers`. Template deletion returns `null`.
The third constructor argument is timeout seconds, default 30.
`listEmailTemplates(?string $cursor = null, ?int $limit = null)` returns one page of
summaries; pass the previous `page.nextCursor` as `$cursor` to fetch the next page.

API exceptions expose `status`, `body`, `rawBody`, and lowercase response `headers`.
Transport failures raise `RuntimeException`.

Use `Ternwire\Webhooks::verify($signatureHeader, $rawBody, $signingSecret)` before
parsing JSON. `$rawBody` is the binary-safe string read from `php://input`.
Tolerance is an optional fourth argument in seconds, default 300.

The complete example is `examples/send.php`. Set `TERNWIRE_API_KEY`,
`TERNWIRE_API_ORIGIN`, `TERNWIRE_FROM`, and `TERNWIRE_TO` before running it.

Read the full documentation at [ternwire.com/docs](https://ternwire.com/docs), or contact
support@ternwire.com. Released under the MIT License.
