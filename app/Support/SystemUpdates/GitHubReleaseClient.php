<?php

namespace App\Support\SystemUpdates;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubReleaseClient
{
    /**
     * @return array{
     *     version: string,
     *     reference: string,
     *     release_url: string,
     *     published_at: string|null,
     *     channel: string
     * }
     */
    public function latest(): array
    {
        $repository = (string) config('system-updates.repository');
        $release = $this->request()->get("/repos/{$repository}/releases/latest");

        if ($release->successful()) {
            $tag = (string) $release->json('tag_name');
            $commit = $this->resolveCommit($repository, $tag);

            return [
                'version' => $tag,
                'reference' => $commit,
                'release_url' => (string) $release->json('html_url'),
                'published_at' => $release->json('published_at'),
                'channel' => 'release',
            ];
        }

        if (! $release->notFound()) {
            $release->throw();
        }

        $branch = (string) config('system-updates.branch', 'main');
        $commit = $this->request()
            ->get("/repos/{$repository}/commits/{$branch}")
            ->throw();
        $reference = (string) $commit->json('sha');

        $this->assertCommitReference($reference);

        return [
            'version' => $branch.' @ '.mb_substr($reference, 0, 8),
            'reference' => $reference,
            'release_url' => (string) $commit->json('html_url'),
            'published_at' => $commit->json('commit.committer.date'),
            'channel' => 'branch',
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('system-updates.github_api_url'), '/'))
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'CRM369-System-Updater',
            ])
            ->connectTimeout((int) config('system-updates.connect_timeout_seconds', 3))
            ->timeout((int) config('system-updates.timeout_seconds', 10))
            ->retry([200, 500], throw: false);

        $token = trim((string) config('system-updates.github_token'));

        return $token === '' ? $request : $request->withToken($token);
    }

    private function resolveCommit(string $repository, string $reference): string
    {
        $commit = (string) $this->request()
            ->get("/repos/{$repository}/commits/{$reference}")
            ->throw()
            ->json('sha');

        $this->assertCommitReference($commit);

        return $commit;
    }

    private function assertCommitReference(string $reference): void
    {
        if (preg_match('/\A[0-9a-f]{40}\z/', $reference) !== 1) {
            throw new RuntimeException('GitHub returned an invalid commit reference.');
        }
    }
}
