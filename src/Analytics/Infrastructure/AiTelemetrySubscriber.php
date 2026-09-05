<?php

declare(strict_types=1);

namespace App\Analytics\Infrastructure;

use App\Analytics\Domain\AiInteraction;
use App\Analytics\Domain\AiInteractionRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class AiTelemetrySubscriber implements EventSubscriberInterface
{
    private const string AI_BOT_PATTERN = '/gptbot|chatgpt-user|claudebot|claude-web|perplexitybot'
        . '|anthropic-ai|applebot-extended|bytespider|cohere-ai|meta-externalagent|diffbot/i';

    public function __construct(
        private AiInteractionRepository $aiTelemetry,
        private string $secret,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -5]];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();
        $userAgent = strtolower(trim((string) $request->headers->get('User-Agent')));

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $dateSalt = $now->format('Y-m-d');
        $clientIp = $request->getClientIp() ?? 'unknown';
        $visitorHash = hash_hmac('sha256', $dateSalt . '|' . $clientIp . '|' . $userAgent, $this->secret);

        if ($path === '/llms.txt' || $path === '/llms-full.txt') {
            $this->aiTelemetry->save(new AiInteraction(
                $now,
                AiInteraction::TYPE_AI_DOCUMENT,
                $path,
                $path,
                $visitorHash,
            ));

            return;
        }

        if ($userAgent !== '' && preg_match(self::AI_BOT_PATTERN, $userAgent, $matches) === 1) {
            $botName = strtolower($matches[0]);
            $this->aiTelemetry->save(new AiInteraction(
                $now,
                AiInteraction::TYPE_AI_CRAWLER,
                $botName,
                $path,
                $visitorHash,
            ));
        }
    }
}
