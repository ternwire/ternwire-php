<?php
declare(strict_types=1);

require __DIR__ . '/../src/TernwireClient.php';
require __DIR__ . '/../src/TernwireApiException.php';

if (($argv[1] ?? '') === '--client') {
    $client = new Ternwire\TernwireClient('em_test_proxyrouting', $argv[2], 1);
    try {
        $client->deleteEmailTemplate('tpl_00000001');
        if ($argv[3] === 'https') {
            throw new LogicException('Expected HTTPS proxy rejection');
        }
    } catch (RuntimeException $error) {
        if ($argv[3] !== 'https' || !str_starts_with($error->getMessage(), 'Ternwire transport failed:')) {
            throw $error;
        }
    }
    echo "ok\n";
    exit(0);
}

function listener(string $host): array
{
    $socket = stream_socket_server('tcp://' . $host . ':0', $errno, $error);
    if ($socket === false) {
        throw new RuntimeException($error, $errno);
    }
    $address = stream_socket_get_name($socket, false);
    return [$socket, substr($address, strrpos($address, ':') + 1)];
}

foreach (['127.0.0.1', 'localhost', '[::1]', 'https'] as $host) {
    [$api, $apiPort] = listener($host === '[::1]' ? '[::1]' : '127.0.0.1');
    [$proxy, $proxyPort] = listener('127.0.0.1');
    $proxyUrl = 'http://127.0.0.1:' . $proxyPort;
    $environment = ['PATH' => getenv('PATH') ?: '', 'NO_PROXY' => '', 'no_proxy' => ''];
    foreach (['http_proxy', 'HTTP_PROXY', 'https_proxy', 'HTTPS_PROXY', 'all_proxy', 'ALL_PROXY'] as $name) {
        $environment[$name] = $proxyUrl;
    }
    $baseUrl = $host === 'https'
        ? 'https://127.0.0.1:' . $apiPort
        : 'http://' . $host . ':' . $apiPort;
    $child = proc_open(
        [PHP_BINARY, __FILE__, '--client', $baseUrl, $host],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes,
        null,
        $environment,
    );
    if ($child === false) {
        throw new RuntimeException('Could not start PHP client');
    }
    fclose($pipes[0]);
    $seen = ['api' => [], 'proxy' => []];
    $deadline = microtime(true) + 5;
    try {
        do {
            $read = [$api, $proxy];
            $write = $except = null;
            if (stream_select($read, $write, $except, 0, 50000) > 0) {
                foreach ($read as $listener) {
                    $connection = stream_socket_accept($listener, 1);
                    stream_set_timeout($connection, 1);
                    $request = '';
                    while (($line = fgets($connection)) !== false && $line !== "\r\n") {
                        $request .= $line;
                    }
                    $seen[$listener === $api ? 'api' : 'proxy'][] = $request;
                    fwrite($connection, str_starts_with($request, 'CONNECT ')
                        ? "HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\nConnection: close\r\n\r\n"
                        : "HTTP/1.1 204 No Content\r\nConnection: close\r\n\r\n");
                    fclose($connection);
                }
            }
            $status = proc_get_status($child);
        } while ($status['running'] && microtime(true) < $deadline);
        if ($status['running']) {
            throw new RuntimeException('PHP client exceeded test deadline');
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        if ($status['exitcode'] !== 0 || $stdout !== "ok\n" || $stderr !== '') {
            throw new RuntimeException('PHP child failed: ' . $stderr . $stdout);
        }
        if ($host === 'https') {
            if (count($seen['proxy']) !== 1 || $seen['api'] !== []
                || !str_starts_with($seen['proxy'][0], 'CONNECT ')
                || str_contains($seen['proxy'][0], 'em_test_')) {
                throw new RuntimeException('HTTPS did not retain its credential-free proxy CONNECT');
            }
        } elseif ($seen['proxy'] !== [] || count($seen['api']) !== 1
            || !str_contains($seen['api'][0], "Authorization: Bearer em_test_proxyrouting\r\n")
            || !str_starts_with($seen['api'][0], 'DELETE /v1/templates/tpl_00000001 HTTP/')) {
            throw new RuntimeException('Loopback routing failed for ' . $host . ': ' . json_encode($seen));
        }
        echo 'ok ' . $host . "\n";
    } finally {
        proc_terminate($child);
        foreach ([$pipes[1], $pipes[2], $api, $proxy] as $stream) {
            fclose($stream);
        }
        proc_close($child);
    }
}
echo "4 routing checks passed\n";
