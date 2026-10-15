<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Ternwire\TernwireClient;

$client = new TernwireClient(getenv('TERNWIRE_API_KEY'), getenv('TERNWIRE_API_ORIGIN') ?: null);
$accepted = $client->submitEmail([
    'from' => getenv('TERNWIRE_FROM'),
    'to' => [getenv('TERNWIRE_TO')],
    'subject' => 'Your receipt',
    'text' => 'Thanks for your order.',
], 'order-123-receipt');
echo json_encode($accepted, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
echo json_encode($client->getMessage($accepted['id']), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
