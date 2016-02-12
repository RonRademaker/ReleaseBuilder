<?php

namespace RonRademaker\ReleaseBuilder\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
    const VERSION = '0.1.1-alpha1';

    /**
     * Configure the command
     */
    protected function configure()
    {
        $this->setName('release:build');
    }

    /**
     * Create the release in github
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }
}
