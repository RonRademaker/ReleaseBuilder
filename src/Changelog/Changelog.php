<?php

namespace RonRademaker\ReleaseBuilder\Changelog;

use Github\Client;

/**
 * Generate a changelog for a release
 *
 * @author Ron Rademaker
 */
class Changelog
{
    /**
     * Github API client
     *
     * @param Client
     */
    private $client;

    /**
     * Creates a new Changelog
     *
     * @param Client $client
     **/
    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Gets the release notes since $version
     *
     * @param string $vendor
     * @param string $repo
     * @param string $branch
     * @param boolean $stable
     * @return string
     */
    public function get($vendor, $repo, $branch, $stable = true)
    {
        $releases = $this->client->api('repo')->releases()->all($vendor, $repo);
        foreach ($releases as $release) {
            if ($release['prerelease'] !== $stable) {
                return $this->getFromVersion($vendor, $repo, $branch, $release);
            }
        }

        return $this->getFromCommits(
            $vendor,
            $repo,
            $commits = $this->client->api('repo')->commits()->all(
                $vendor,
                $repo,
                [
                    'sha' => $branch
                ]
            )
        );
    }

    /**
     * Gets a changelog from $release
     *
     * @param string $vendor
     * @param string $repo
     * @param string $branch
     * @param array $release
     */
    private function getFromVersion($vendor, $repo, $branch, array $release)
    {
        $commits = $this->client->api('repo')->commits()->all(
            $vendor,
            $repo,
            [
                'sha' => $branch,
                'since' => $release['published_at']
            ]
        );

        return $this->getFromCommits($vendor, $repo, $commits);
    }

    /**
     * Gets a changelog from $commits
     *
     * @param string $vendor
     * @param string $repo
     * @param array $commits
     */
    private function getFromCommits($vendor, $repo, array $commits)
    {
        $prs = $this->extractPRMerges($commits);

        if (count($prs) === 0) {
            return $this->getCommitMessages($commits);
        } else {
            return $this->getPullRequests($vendor, $repo, $prs);
        }
    }

    /**
     * Extract PR merge commits
     *
     * @array $commits
     * @return array
     */
    private function extractPRMerges(array $commits)
    {
        $prs = [];

        foreach ($commits as $commit) {
            $message = $commit['commit']['message'];
            if (strpos($message, 'Merge pull request #') === 0) {
                $words = explode(' ', $message);
                $prs[] = (int) trim($words[3], '#');
            }
        }

        return $prs;
    }

    /**
     * Create changelog for $prs
     *
     * @param string $vendor
     * @param string $repo
     * @param array $prs
     * @return string
     */
    private function getPullRequests($vendor, $repo, $prs)
    {
        $changelog = "Changelog:\n\n";

        foreach ($prs as $pr) {
            $pullRequest = $this->client->api('pull_request')->show($vendor, $repo, $pr);
            $changelog .= sprintf(
                '* %s (#%d)%s',
                $pullRequest['title'],
                $pr,
                "\n"
            );
        }

        return $changelog;
    }

    /**
     * Create changelog based on commits
     *
     * @param array $commits
     * @return string
     */
    private function getCommitMessages(array $commits)
    {
        $changelog = "Changelog:\n\n";

        foreach ($commits as $commit) {
            $changelog .= sprintf(
                '* %s (%s)%s',
                $commit['commit']['message'],
                $commit['sha'],
                "\n"
            );
        }

	return $changelog;
    }


}

