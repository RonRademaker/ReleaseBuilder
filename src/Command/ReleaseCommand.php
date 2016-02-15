<?php

namespace RonRademaker\ReleaseBuilder\Command;

use Github\Client;
use RonRademaker\ReleaseBuilder\Modifier\ConstantModifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Command to create releases in Github
 * Allows setting the release version as a constant in a class
 *
 * @author Ron Rademaker
 */
class ReleaseCommand extends Command
{
    /**
     * The current version
     */
    const VERSION = '0.1-dev';

    /**
     * Vendor or the repo to release
     *
     * @var string
     */
    private $vendor;

    /**
     * Repo to release
     *
     * @var string
     */
    private $repo;

    /**
     * Version to create
     *
     * @var string
     */
    private $version;

    /**
     * Version is a prerelease
     *
     * @var bool
     */
    private $preRelease;

    /**
     * Branch to release
     *
     * @var string
     */
    private $branch = 'master';

    /**
     * Version constant to update
     *
     * @var string
     */
    private $versionConstant;

    /**
     * File with the version constant
     *
     * @var string
     */
    private $versionFile;

    /**
     * Dev version to revert to
     *
     * @var string
     */
    private $devVersion;

    /**
     * Github token
     *
     * @var string
     */
    private $token;

    /**
     * Committer
     *
     * @var array
     */
    private $committer;

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setName('release:build')
            ->setDescription('Utility to create releases in Github')
            ->addArgument('repository', InputArgument::REQUIRED, 'The GITHUB repository to release (for example RonRademaker/ReleaseBuilder')
            ->addArgument('version', InputArgument::REQUIRED, 'The version to create (for example 1.0.0)')
            ->addArgument('version-constant', InputArgument::REQUIRED, 'Class file and constant to set the version in (for example src/Command/ReleaseCommand.php::VERSION)')
            ->addArgument('development-version', InputArgument::REQUIRED, 'The version to set the released branch to after the release')
            ->addArgument('branch', InputArgument::REQUIRED, 'The branch to release');
    }

    /**
     * Load options into variables and request other information
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        parent::initialize($input, $output);

        $repository = $input->getArgument('repository');
        if (strpos($repository, '/') === false) {

        }

        list($this->vendor, $this->repo) = explode('/', $repository);
        $this->version = $input->getArgument('version');

        $versionConstant = $input->getArgument('version-constant');
        if (!empty($versionConstant)) {
            if (strpos($versionConstant, '::') === false) {

            }

            list($this->versionFile, $this->versionConstant) = explode('::', $versionConstant);
        }

        $this->devVersion = $input->getArgument('development-version');
        $this->branch = $input->getArgument('branch');
        $this->token = $this->retrieveToken($input, $output);
        $this->committer = $this->retrieveCommitter($input, $output);

    }

    /**
     * Create the release in github
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $client = new Client();
        $client->authenticate($this->token, null, Client::AUTH_URL_TOKEN);

        $currentFile = $client->api('repo')->contents()->show($this->vendor, $this->repo, $this->versionFile, $this->branch);
        $releaseContent = $this->updateVersionNumber($currentFile['content'], $this->version);
        $client->api('repo')->contents()->update(
            $this->vendor,
            $this->repo,
            $this->versionFile,
            $releaseContent,
            'Updated version number for release',
            $currentFile['sha'],
            $this->branch,
            $this->committer
        );

        $client->api('repo')->releases()->create(
            $this->vendor,
            $this->repo,
            [
                'tag_name' => $this->version,
                'target_commitish' => $this->branch,
                'name' => 'Release ' . $this->version,
                'body' => 'Released using the ReleaseBuiler',
                'draft' => false,
                'prerelease' => strpbrk($this->version, '-') !== false
            ]
        );

        $releaseFile = $client->api('repo')->contents()->show($this->vendor, $this->repo, $this->versionFile, $this->branch);
        $developmentContent = $this->updateVersionNumber($releaseFile['content'], $this->devVersion);
        $client->api('repo')->contents()->update(
            $this->vendor,
            $this->repo,
            $this->versionFile,
            $developmentContent,
            'Updated version number for development',
            $releaseFile['sha'],
            $this->branch,
            $this->committer
        );
    }

    /**
     * Updates the version constant in $content
     *
     * @param string $base64Content
     * @return string
     */
    private function updateVersionNumber($base64Content, $newVersion)
    {
        $code = base64_decode($base64Content);
        $modifier = new ConstantModifier($code);

        return $modifier->modify($this->versionConstant, $newVersion);
    }

    /**
     * Read token from file or ask the user
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string
     */
    private function retrieveToken(InputInterface $input, OutputInterface $output)
    {
        $homedir = $this->getUserHomeDir();

        if (file_exists($homedir . '/.releaseBuilder/auth.json')) {
            $data = json_decode(file_get_contents($homedir . '/.releaseBuilder/auth.json'));

            return $data->token;
        } else {
            $helper = $this->getHelper('question');
            $question = new Question(
                sprintf(
                    'Please enter your github token (go to %s to generate one):',
                    'https://www.github.com/settings/tokens/new?scopes=repo&description=' . str_replace('%20', '+', rawurlencode('ReleaseBuilder ' . date('Y-m-d G:i:s')))
                )
            );

            $token = $helper->ask($input, $output, $question);

            $storeQuestion = new ConfirmationQuestion(
                sprintf(
                    'Do you want to store the token in %s/.releaseBuilder/auth.json? [Yn] ',
                    $homedir
                ),
                true
            );
            $store = $helper->ask($input, $output, $storeQuestion);

            if ($store) {
                if (!is_dir($homedir . '/.releaseBuilder')) {
                    mkdir($homedir . '/.releaseBuilder', 0700);
                }

                file_put_contents($homedir . '/.releaseBuilder/auth.json', json_encode(['token' => $token]));
            }

            return $token;
        }
    }

    /**
     * Gets the committer name and email
     *
     * @return array
     */
    private function retrieveCommitter()
    {
        $name = trim(exec('git config user.name'));
        $email = trim(exec('git config user.email'));

        return ['name' => $name, 'email' => $email];
    }

    /**
     * Gets the homedir of the current user
     *
     * @return string
     */
    private function getUserHomeDir()
    {
        return $_SERVER['HOME'];
    }
}
