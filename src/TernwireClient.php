<?php
declare(strict_types=1);

namespace Ternwire;

final class TernwireClient
{
    public const DEFAULT_BASE_URL = 'https://ternwire.com';

    private readonly string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        ?string $baseUrl = null,
        private readonly int $timeoutSeconds = 30,
    ) {
        $baseUrl ??= self::DEFAULT_BASE_URL;
        if (!preg_match('/\Aem_(live|test)_[A-Za-z0-9_-]+\z/', $apiKey)) {
            throw new \InvalidArgumentException('Use an em_live_ or em_test_ API key');
        }
        $url = parse_url($baseUrl);
        if ($url === false || !in_array($url['scheme'] ?? '', ['http', 'https'], true)
            || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment'])
            || !in_array($url['path'] ?? '', ['', '/'], true)) {
            throw new \InvalidArgumentException('baseUrl must be an HTTP(S) origin without /v1');
        }
        if ($url['scheme'] === 'http' && !self::isLoopbackHost($url['host'])) {
            throw new \InvalidArgumentException(
                'baseUrl must use https unless it points at localhost or a loopback address'
            );
        }
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('timeoutSeconds must be positive');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private static function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return true;
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return str_starts_with($host, '127.');
        }
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && inet_pton($host) === inet_pton('::1');
    }

    private static function id(string $value): string
    {
        if (!preg_match('/\A[a-z0-9][a-z0-9_-]{7,127}\z/', $value)) {
            throw new \InvalidArgumentException('Invalid Ternwire resource ID');
        }
        return $value;
    }

    private static function query(array $values): string
    {
        $query = http_build_query($values, '', '&', PHP_QUERY_RFC3986);
        return $query === '' ? '' : '?' . $query;
    }

    // BEGIN GENERATED OPERATIONS
    public function submitEmail(array $input, string $idempotencyKey): ?array
    {
        return $this->request("POST", "/v1/emails", 202, $input, $idempotencyKey);
    }

    public function getMessage(string $messageId): ?array
    {
        return $this->request("GET", "/v1/emails/" . self::id($messageId), 200, null, null);
    }

    public function createEmailTemplate(array $input): ?array
    {
        return $this->request("POST", "/v1/templates", 201, $input, null);
    }

    public function listEmailTemplates(?string $cursor = null, ?int $limit = null): ?array
    {
        return $this->request("GET", "/v1/templates" . self::query(['cursor' => $cursor, 'limit' => $limit]), 200, null, null);
    }

    public function getEmailTemplate(string $templateId): ?array
    {
        return $this->request("GET", "/v1/templates/" . self::id($templateId), 200, null, null);
    }

    public function replaceEmailTemplate(string $templateId, array $input): ?array
    {
        return $this->request("PUT", "/v1/templates/" . self::id($templateId), 200, $input, null);
    }

    public function deleteEmailTemplate(string $templateId): ?array
    {
        return $this->request("DELETE", "/v1/templates/" . self::id($templateId), 204, null, null);
    }

    public function createAttachmentUploadSlot(array $input): ?array
    {
        return $this->request("POST", "/v1/attachments", 201, $input, null);
    }

    public function getAttachment(string $attachmentId): ?array
    {
        return $this->request("GET", "/v1/attachments/" . self::id($attachmentId), 200, null, null);
    }

    public function completeAttachmentUpload(string $attachmentId): ?array
    {
        return $this->request("POST", "/v1/attachments/" . self::id($attachmentId) . "/complete", 202, null, null);
    }

    public function createSendingDomain(array $input): ?array
    {
        return $this->request("POST", "/v1/domains", 201, $input, null);
    }

    public function getSendingDomain(string $domainId): ?array
    {
        return $this->request("GET", "/v1/domains/" . self::id($domainId), 200, null, null);
    }

    public function deleteSendingDomain(string $domainId): ?array
    {
        return $this->request("DELETE", "/v1/domains/" . self::id($domainId), 202, null, null);
    }

    public function createWebhookEndpoint(array $input): ?array
    {
        return $this->request("POST", "/v1/webhook-endpoints", 201, $input, null);
    }

    public function rotateWebhookEndpointSigningSecret(string $endpointId): ?array
    {
        return $this->request("POST", "/v1/webhook-endpoints/" . self::id($endpointId) . "/rotate-signing-secret", 200, null, null);
    }

    public function getWebhookAttempt(string $attemptId): ?array
    {
        return $this->request("GET", "/v1/webhook-attempts/" . self::id($attemptId), 200, null, null);
    }

    public function replayWebhookAttempt(string $attemptId): ?array
    {
        return $this->request("POST", "/v1/webhook-attempts/" . self::id($attemptId) . "/replay", 202, null, null);
    }
    // END GENERATED OPERATIONS

    private function request(
        string $method,
        string $path,
        int $expectedStatus,
        ?array $body = null,
        ?string $idempotencyKey = null,
    ): ?array {
        $headers = ['Authorization: Bearer ' . $this->apiKey, 'Accept: application/json'];
        if ($idempotencyKey !== null) {
            if (!preg_match('/\A[\x21-\x7e]{1,128}\z/', $idempotencyKey)) {
                throw new \InvalidArgumentException('idempotencyKey must contain 1-128 visible ASCII characters');
            }
            $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
        }
        $responseHeaders = [];
        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ];
        if (str_starts_with($this->baseUrl, 'http://')) {
            $options[CURLOPT_PROXY] = '';
        }
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
            $options[CURLOPT_POSTFIELDS] = json_encode((object) $body, JSON_THROW_ON_ERROR);
        }
        $options[CURLOPT_HTTPHEADER] = $headers;
        $curl = curl_init($this->baseUrl . $path);
        if ($curl === false) {
            throw new \RuntimeException('Could not initialize cURL');
        }
        curl_setopt_array($curl, $options);
        $rawBody = curl_exec($curl);
        if ($rawBody === false) {
            throw new \RuntimeException('Ternwire transport failed: ' . curl_error($curl));
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status !== $expectedStatus) {
            throw new TernwireApiException($status, $rawBody, $responseHeaders);
        }
        if ($status === 204) {
            return null;
        }
        $decoded = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof \stdClass) {
            throw new \UnexpectedValueException('Ternwire API returned a non-object JSON response');
        }
        return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
    }
}
