<?php

declare(strict_types=1);

namespace Spatial\Notify;

use Closure;
use JsonException;

final class NotifyApiClient
{
    /**
     * @param null|Closure(string, list<string>, string, int): array{status: int, body: string} $transport
     */
    public function __construct(
        private readonly string $url,
        private readonly string $token,
        private readonly int $timeoutSeconds = 10,
        private readonly ?Closure $transport = null,
    ) {
        $parts = parse_url($url);
        if (!is_array($parts)
            || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || trim((string) ($parts['host'] ?? '')) === ''
        ) {
            throw new \InvalidArgumentException('A valid Notify API URL is required.');
        }
        if (strlen($token) < 32) {
            throw new \InvalidArgumentException('The Notify API service token must contain at least 32 characters.');
        }
        if ($timeoutSeconds < 1 || $timeoutSeconds > 60) {
            throw new \InvalidArgumentException('The Notify API timeout must be between 1 and 60 seconds.');
        }
    }

    public static function fromEnvironment(int $timeoutSeconds = 10): self
    {
        return new self(
            getenv('NOTIFY_API_URL') ?: 'http://nx_notify:8080/notify-api/v2/notifications',
            getenv('NOTIFY_API_TOKEN') ?: '',
            $timeoutSeconds,
        );
    }

    /** @param array<string, mixed> $command */
    public function send(array $command): string
    {
        try {
            $body = json_encode($command, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new NotifyApiException('The notification command could not be encoded.', false, null, $exception);
        }

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json',
            'Accept: application/json',
            'Idempotency-Key: ' . (string) ($command['idempotencyKey'] ?? ''),
            'X-Correlation-Id: ' . (string) ($command['correlationId'] ?? ''),
        ];
        $response = $this->transport instanceof Closure
            ? ($this->transport)($this->url, $headers, $body, $this->timeoutSeconds)
            : $this->request($headers, $body);

        $status = $response['status'];
        if ($status !== 202) {
            $retryable = $status === 0 || $status === 408 || $status === 425 || $status === 429 || $status >= 500;
            throw new NotifyApiException('Notify API did not accept the notification command.', $retryable, $status ?: null);
        }

        try {
            $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new NotifyApiException('Notify API returned an invalid response.', true, $status, $exception);
        }

        $notificationId = is_array($payload) ? ($payload['data']['notificationId'] ?? null) : null;
        if (!is_string($notificationId) || !self::isUuid($notificationId)) {
            throw new NotifyApiException('Notify API response did not contain a valid notification reference.', true, $status);
        }

        return $notificationId;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string}
     */
    private function request(array $headers, string $body): array
    {
        $handle = curl_init($this->url);
        if ($handle === false) {
            throw new NotifyApiException('Notify API transport could not be initialized.', true);
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeoutSeconds),
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0,
        ]);
        $responseBody = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        if ($responseBody === false) {
            curl_close($handle);
            throw new NotifyApiException('Notify API is unavailable.', true, $status ?: null);
        }
        curl_close($handle);

        return ['status' => $status, 'body' => (string) $responseBody];
    }

    private static function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }
}
