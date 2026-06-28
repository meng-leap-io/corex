<?php

declare(strict_types=1);

namespace App\Services\Webhook\Handlers;

use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GitHubHandler
{
    public function handle(Request $request, WebhookLog $log): array
    {
        $eventType = $request->header('x-github-event', 'unknown');
        $payload = $log->payload;
        $action = $payload['action'] ?? null;

        return match ($eventType) {
            'push' => $this->handlePush($payload, $log),
            'pull_request' => $this->handlePullRequest($payload, $action, $log),
            'issues' => $this->handleIssue($payload, $action, $log),
            'star' => $this->handleStar($payload, $log),
            'ping' => $this->handlePing($payload, $log),
            default => ['status' => 200, 'response' => ['handled' => false, 'event' => $eventType]],
        };
    }

    public function process(WebhookLog $log): array
    {
        return $this->handle(Request::create('', 'POST', $log->payload ?? []), $log);
    }

    private function handlePush(array $payload, WebhookLog $log): array
    {
        $repo = $payload['repository']['full_name'] ?? 'unknown';
        $ref = $payload['ref'] ?? 'unknown';
        $commitCount = count($payload['commits'] ?? []);

        Log::info('webhook.github.push', [
            'repository' => $repo,
            'ref' => $ref,
            'commits' => $commitCount,
            'pusher' => $payload['pusher']['name'] ?? 'unknown',
        ]);

        return ['status' => 200, 'response' => [
            'handled' => true,
            'repository' => $repo,
            'branch' => $ref,
            'commits' => $commitCount,
        ]];
    }

    private function handlePullRequest(array $payload, ?string $action, WebhookLog $log): array
    {
        $repo = $payload['repository']['full_name'] ?? 'unknown';
        $prNumber = $payload['number'] ?? 0;
        $prTitle = $payload['pull_request']['title'] ?? 'Untitled';
        $prAuthor = $payload['pull_request']['user']['login'] ?? 'unknown';

        Log::info('webhook.github.pull_request', [
            'repository' => $repo,
            'action' => $action,
            'pr' => $prNumber,
            'title' => $prTitle,
            'author' => $prAuthor,
        ]);

        return ['status' => 200, 'response' => [
            'handled' => true,
            'action' => $action,
            'pr' => $prNumber,
            'title' => $prTitle,
        ]];
    }

    private function handleIssue(array $payload, ?string $action, WebhookLog $log): array
    {
        $repo = $payload['repository']['full_name'] ?? 'unknown';
        $issueNumber = $payload['issue']['number'] ?? 0;
        $issueTitle = $payload['issue']['title'] ?? 'Untitled';

        Log::info('webhook.github.issue', [
            'repository' => $repo,
            'action' => $action,
            'issue' => $issueNumber,
            'title' => $issueTitle,
        ]);

        return ['status' => 200, 'response' => [
            'handled' => true,
            'action' => $action,
            'issue' => $issueNumber,
        ]];
    }

    private function handleStar(array $payload, WebhookLog $log): array
    {
        $repo = $payload['repository']['full_name'] ?? 'unknown';
        $action = $payload['action'] ?? 'created';
        $starCount = $payload['repository']['stargazers_count'] ?? 0;

        Log::info('webhook.github.star', [
            'repository' => $repo,
            'action' => $action,
            'total_stars' => $starCount,
        ]);

        return ['status' => 200, 'response' => [
            'handled' => true,
            'action' => $action,
            'stars' => $starCount,
        ]];
    }

    private function handlePing(array $payload, WebhookLog $log): array
    {
        $repo = $payload['repository']['full_name'] ?? 'unknown';
        $hookId = $payload['hook_id'] ?? 'unknown';

        Log::info('webhook.github.ping', [
            'repository' => $repo,
            'hook_id' => $hookId,
        ]);

        return ['status' => 200, 'response' => [
            'handled' => true,
            'event' => 'ping',
            'hook_id' => $hookId,
        ]];
    }
}
