<?php

declare(strict_types=1);

namespace Spatial\Notify;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final class NotificationCommandBuilder
{
    /**
     * @param array<string, scalar|null> $metadata
     * @return array<string, mixed>
     */
    public static function rawEmail(
        string $commandId,
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        string $source,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $businessId = null,
        ?string $textBody = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        array $metadata = [],
    ): array {
        self::assertIdentifier($commandId, 'commandId');

        $variables = [
            'subject' => self::text($subject, 255),
            'htmlBody' => $htmlBody,
        ];
        if ($textBody !== null) {
            $variables['textBody'] = self::text($textBody, 10000);
        }

        return self::baseCommand(
            commandId: $commandId,
            channel: 'email',
            recipientAddress: strtolower(trim($recipientEmail)),
            referenceType: $referenceType,
            referenceId: $referenceId,
            businessId: $businessId,
            templateId: 'transactional.raw',
            templateVersion: 1,
            locale: 'en',
            variables: $variables,
            source: $source,
            correlationId: $correlationId,
            causationId: $causationId,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, scalar|null> $data
     * @param array<string, scalar|null> $metadata
     * @return array<string, mixed>
     */
    public static function push(
        string $commandId,
        string $deviceToken,
        string $title,
        string $body,
        string $source,
        array $data = [],
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $businessId = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        array $metadata = [],
    ): array {
        self::assertIdentifier($commandId, 'commandId');

        $variables = [
            'title' => self::text($title, 120),
            'body' => self::text($body, 4000),
        ];
        if ($data !== []) {
            $variables['data'] = $data;
        }

        return self::baseCommand(
            commandId: $commandId,
            channel: 'push',
            recipientAddress: trim($deviceToken),
            referenceType: $referenceType,
            referenceId: $referenceId,
            businessId: $businessId,
            templateId: 'push.generic',
            templateVersion: 1,
            locale: 'en',
            variables: $variables,
            source: $source,
            correlationId: $correlationId,
            causationId: $causationId,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, scalar|null> $metadata
     * @return array<string, mixed>
     */
    public static function sms(
        string $commandId,
        string $phoneNumber,
        string $message,
        string $source,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $businessId = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        array $metadata = [],
    ): array {
        self::assertIdentifier($commandId, 'commandId');

        return self::baseCommand(
            commandId: $commandId,
            channel: 'sms',
            recipientAddress: trim($phoneNumber),
            referenceType: $referenceType,
            referenceId: $referenceId,
            businessId: $businessId,
            templateId: 'sms.generic',
            templateVersion: 1,
            locale: 'en',
            variables: ['body' => self::text($message, 1000)],
            source: $source,
            correlationId: $correlationId,
            causationId: $causationId,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, scalar|null> $metadata
     * @return array<string, mixed>
     */
    public static function templatedEmail(
        string $commandId,
        string $recipientEmail,
        string $templateId,
        int $templateVersion,
        string $locale,
        array $variables,
        string $source,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $businessId = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        array $metadata = [],
    ): array {
        self::assertIdentifier($commandId, 'commandId');

        return self::baseCommand(
            commandId: $commandId,
            channel: 'email',
            recipientAddress: strtolower(trim($recipientEmail)),
            referenceType: $referenceType,
            referenceId: $referenceId,
            businessId: $businessId,
            templateId: $templateId,
            templateVersion: $templateVersion,
            locale: $locale,
            variables: $variables,
            source: $source,
            correlationId: $correlationId,
            causationId: $causationId,
            metadata: $metadata,
        );
    }

    /**
     * @param array<string, mixed> $variables
     * @param array<string, scalar|null> $metadata
     * @return array<string, mixed>
     */
    private static function baseCommand(
        string $commandId,
        string $channel,
        string $recipientAddress,
        ?string $referenceType,
        ?string $referenceId,
        ?int $businessId,
        string $templateId,
        int $templateVersion,
        string $locale,
        array $variables,
        string $source,
        ?string $correlationId,
        ?string $causationId,
        array $metadata,
    ): array {
        if (filter_var($recipientAddress, FILTER_VALIDATE_EMAIL) === false && $channel === 'email') {
            throw new InvalidArgumentException('The recipient email address is invalid.');
        }

        $recipient = ['address' => $recipientAddress];
        if ($referenceType !== null && $referenceId !== null) {
            $recipient['referenceType'] = $referenceType;
            $recipient['referenceId'] = $referenceId;
        }

        $resolvedCorrelationId = $correlationId ?? $commandId;
        self::assertIdentifier($resolvedCorrelationId, 'correlationId');
        if ($causationId !== null) {
            self::assertIdentifier($causationId, 'causationId');
        }

        return [
            'commandId' => $commandId,
            'businessId' => $businessId,
            'channel' => $channel,
            'recipient' => $recipient,
            'template' => [
                'templateId' => $templateId,
                'version' => $templateVersion,
                'locale' => $locale,
                'variables' => $variables,
            ],
            'deliveryBasis' => [
                'type' => 'transactional',
                'reference' => $commandId,
            ],
            'scheduleAt' => null,
            'idempotencyKey' => $commandId,
            'correlationId' => $resolvedCorrelationId,
            'causationId' => $causationId,
            'requestedAt' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'metadata' => array_merge(['source' => $source], $metadata),
        ];
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (strlen($value) < 8 || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            throw new InvalidArgumentException(sprintf('The %s value is invalid.', $field));
        }
    }

    private static function text(string $value, int $maximumLength): string
    {
        $value = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '');
        if ($value === '') {
            throw new InvalidArgumentException('A notification text value cannot be empty.');
        }

        return mb_substr($value, 0, $maximumLength);
    }
}
