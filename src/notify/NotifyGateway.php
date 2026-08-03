<?php

declare(strict_types=1);

namespace Spatial\Notify;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Fire-and-forget gateway for queueing notifications through nx_notify v2.
 */
final class NotifyGateway
{
    private readonly NotifyApiClient $client;
    private readonly LoggerInterface $logger;
    private readonly string $source;
    private readonly bool $enabled;

    public function __construct(
        ?NotifyApiClient $client = null,
        ?LoggerInterface $logger = null,
        ?string $source = null,
    ) {
        $token = getenv('NOTIFY_API_TOKEN') ?: '';
        $this->enabled = strlen($token) >= 32;
        $this->logger = $logger ?? new NullLogger();
        $this->source = $source ?? (getenv('APP_NAME') ?: 'unknown');
        $this->client = $client ?? ($this->enabled
            ? NotifyApiClient::fromEnvironment()
            : new NotifyApiClient(
                getenv('NOTIFY_API_URL') ?: 'http://nx_notify:8080/notify-api/v2/notifications',
                str_repeat('x', 32),
            ));
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, scalar|null> $variables
     */
    public function queueTemplatedEmail(
        string $commandId,
        string $recipientEmail,
        string $templateId,
        array $variables,
        ?string $referenceType = null,
        ?string $referenceId = null,
        int $templateVersion = 1,
        string $locale = 'en',
    ): bool {
        if (!$this->enabled) {
            $this->logger->warning('Notify gateway is disabled because NOTIFY_API_TOKEN is not configured.');
            return false;
        }

        try {
            $this->client->send(NotificationCommandBuilder::templatedEmail(
                commandId: $commandId,
                recipientEmail: $recipientEmail,
                templateId: $templateId,
                templateVersion: $templateVersion,
                locale: $locale,
                variables: $variables,
                source: $this->source,
                referenceType: $referenceType,
                referenceId: $referenceId,
            ));

            return true;
        } catch (NotifyApiException $exception) {
            $this->logger->error('Failed to queue templated email notification.', [
                'commandId' => $commandId,
                'templateId' => $templateId,
                'recipient' => $recipientEmail,
                'retryable' => $exception->retryable,
                'statusCode' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function queueRawEmail(
        string $commandId,
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $textBody = null,
    ): bool {
        if (!$this->enabled) {
            $this->logger->warning('Notify gateway is disabled because NOTIFY_API_TOKEN is not configured.');
            return false;
        }

        try {
            $this->client->send(NotificationCommandBuilder::rawEmail(
                commandId: $commandId,
                recipientEmail: $recipientEmail,
                subject: $subject,
                htmlBody: $htmlBody,
                source: $this->source,
                referenceType: $referenceType,
                referenceId: $referenceId,
                textBody: $textBody,
            ));

            return true;
        } catch (NotifyApiException $exception) {
            $this->logger->error('Failed to queue email notification.', [
                'commandId' => $commandId,
                'recipient' => $recipientEmail,
                'retryable' => $exception->retryable,
                'statusCode' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function queuePush(
        string $commandId,
        string $deviceToken,
        string $title,
        string $body,
        ?string $referenceType = null,
        ?string $referenceId = null,
    ): bool {
        if (!$this->enabled) {
            $this->logger->warning('Notify gateway is disabled because NOTIFY_API_TOKEN is not configured.');
            return false;
        }

        try {
            $this->client->send(NotificationCommandBuilder::push(
                commandId: $commandId,
                deviceToken: $deviceToken,
                title: $title,
                body: $body,
                source: $this->source,
                referenceType: $referenceType,
                referenceId: $referenceId,
            ));

            return true;
        } catch (NotifyApiException $exception) {
            $this->logger->error('Failed to queue push notification.', [
                'commandId' => $commandId,
                'retryable' => $exception->retryable,
                'statusCode' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function queueSms(
        string $commandId,
        string $phoneNumber,
        string $message,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?int $businessId = null,
        ?string $gatewayId = null,
        ?string $senderIdentityId = null,
        ?string $senderValue = null,
        string $deliveryBasisType = 'transactional',
        array $metadata = [],
    ): bool {
        if (!$this->enabled) {
            $this->logger->warning('Notify gateway is disabled because NOTIFY_API_TOKEN is not configured.');
            return false;
        }

        try {
            $command = NotificationCommandBuilder::sms(
                commandId: $commandId,
                phoneNumber: $phoneNumber,
                message: $message,
                source: $this->source,
                referenceType: $referenceType,
                referenceId: $referenceId,
                businessId: $businessId,
                metadata: $metadata,
                gatewayId: $gatewayId,
                senderIdentityId: $senderIdentityId,
                senderValue: $senderValue,
            );
            $command['deliveryBasis'] = [
                'type' => $deliveryBasisType,
                'reference' => $commandId,
            ];
            if ($deliveryBasisType === 'explicit_consent') {
                $command['deliveryBasis']['capturedAt'] = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
            }
            $this->client->send($command);

            return true;
        } catch (NotifyApiException $exception) {
            $this->logger->error('Failed to queue SMS notification.', [
                'commandId' => $commandId,
                'retryable' => $exception->retryable,
                'statusCode' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Queue a campaign email (HTML) with optional gateway/sender routing.
     *
     * @param array<string, scalar|null> $metadata
     */
    public function queueCampaignEmail(
        string $commandId,
        string $recipientEmail,
        string $subject,
        string $htmlBody,
        ?int $businessId = null,
        ?string $gatewayId = null,
        ?string $senderIdentityId = null,
        ?string $senderValue = null,
        ?string $referenceType = null,
        ?string $referenceId = null,
        string $deliveryBasisType = 'explicit_consent',
        array $metadata = [],
    ): bool {
        if (!$this->enabled) {
            $this->logger->warning('Notify gateway is disabled because NOTIFY_API_TOKEN is not configured.');
            return false;
        }

        try {
            $command = NotificationCommandBuilder::rawEmail(
                commandId: $commandId,
                recipientEmail: $recipientEmail,
                subject: $subject,
                htmlBody: $htmlBody,
                source: $this->source,
                referenceType: $referenceType,
                referenceId: $referenceId,
                businessId: $businessId,
                metadata: $metadata,
            );
            $command['deliveryBasis'] = [
                'type' => $deliveryBasisType,
                'reference' => $commandId,
                'capturedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ];
            if ($gatewayId !== null) {
                $command['gatewayId'] = $gatewayId;
            }
            if ($senderIdentityId !== null) {
                $command['senderIdentityId'] = $senderIdentityId;
            }
            if ($senderValue !== null && $senderValue !== '') {
                $command['sender'] = $senderValue;
            }
            $this->client->send($command);

            return true;
        } catch (NotifyApiException $exception) {
            $this->logger->error('Failed to queue campaign email notification.', [
                'commandId' => $commandId,
                'retryable' => $exception->retryable,
                'statusCode' => $exception->statusCode,
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
