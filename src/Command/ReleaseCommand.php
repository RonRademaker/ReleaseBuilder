<?php

namespace RonRademaker\ReleaseBuilder\Command;

use Github\Client;
use Github\Exception\RuntimeException;
use RonRademaker\ReleaseBuilder\Changelog\Changelog;
use RonRademaker\ReleaseBuilder\Modifier\ConstantModifier;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
    const VERSION = '0.5.0-RC';

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
            ->addArgument('development-version', InputArgument::REQUIRED, 'The version to set the released branch to after the release')
            ->addOption('version-constant', NULL, InputOption::VALUE_OPTIONAL, 'Class file and constant to set the version in (for example src/Command/ReleaseCommand.php::VERSION)')
            ->addOption('branch', NULL, InputOption::VALUE_OPTIONAL, 'The branch to release', 'master');
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
        if (empty($repository)) {
            return;
        }
        if (strpos($repository, '/') === false) {
            $output->write('<error>The repository should be written in <vendor>/<repository> form.</error>', true);
            exit(1);
        }

        list($this->vendor, $this->repo) = explode('/', $repository);
        $this->version = $input->getArgument('version');

        if ($input->hasOption('version-constant')) {
            $versionConstant = $input->getOption('version-constant');
            if (!empty($versionConstant)) {
                if (strpos($versionConstant, '::') === false) {
                    $output->write('<error>The version constant should be written in <file>::<constant name> form.</error>', true);
                    exit(1);
                }

                list($this->versionFile, $this->versionConstant) = explode('::', $versionConstant);
            }
        }

        $this->devVersion = $input->getArgument('development-version');
        $this->branch = $input->hasOption('branch') ? $input->getOption('branch') : 'master';
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

        if (isset($this->versionFile)) {
            $this->setVersion($output, $client, $this->version, 'Updated version number for release');
        }

        $changelog = new Changelog($client);

        $stable = strpos($this->version, '-') === false;

        $client->api('repo')->releases()->create(
            $this->vendor,
            $this->repo,
            [
                'tag_name' => $this->version,
                'target_commitish' => $this->branch,
                'name' => 'Release ' . $this->version,
                'body' => $changelog->get($this->vendor, $this->repo, $this->branch, $stable),
                'draft' => false,
                'prerelease' => !$stable
            ]
        );

        if (isset($this->versionFile)) {
            $this->setVersion($output, $client, $this->devVersion, 'Updated version number for development');
        }
    }

    /**
     * Updates the version in the configured version file
     *
     * @param OutputInterface $output
     * @param Client $client
     * @param string $version
     * @param string $message
     */
    private function setVersion(OutputInterface $output, Client $client, $version, $message)
    {
        try {
            $currentFile = $client->api('repo')->contents()->show($this->vendor, $this->repo, $this->versionFile, $this->branch);
            $releaseContent = $this->updateVersionNumber($currentFile['content'], $version);
            $client->api('repo')->contents()->update(
                $this->vendor,
                $this->repo,
                $this->versionFile,
                $releaseContent,
                $message,
                $currentFile['sha'],
                $this->branch,
                $this->committer
            );
        } catch (RuntimeException $exception) {
            $output->write(
                sprintf(
                    '<error>Error updating %s: %s</error>',
                    $this->versionFile,
                    $exception->getMessage()
                ),
                true
            );
            exit(0);
        }

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
