<?php
declare(strict_types=1);

namespace Ternwire;

final class TernwireApiException extends \RuntimeException
{
    public readonly mixed $body;

    public function __construct(
        public readonly int $status,
        public readonly string $rawBody,
        public readonly array $headers,
    ) {
        parent::__construct("Ternwire API returned HTTP {$status}");
        $this->body = json_decode($rawBody, true);
    }
}
