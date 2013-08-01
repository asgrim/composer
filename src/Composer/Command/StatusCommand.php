<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Composer\Downloader\VcsDownloader;
use Composer\Downloader\DvcsDownloaderInterface;

/**
 * @author Tiago Ribeiro <tiago.ribeiro@seegno.com>
 * @author Rui Marinho <rui.marinho@seegno.com>
 */
class StatusCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('status')
            ->setDescription('Show a list of locally modified packages')
            ->setDefinition(array(
                new InputOption('verbose', 'v|vv|vvv', InputOption::VALUE_NONE, 'Show modified files for each directory that contains changes.'),
            ))
            ->setHelp(<<<EOT
The status command displays a list of dependencies that have
been modified locally.

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // init repos
        $composer = $this->getComposer();
        $installedRepo = $composer->getRepositoryManager()->getLocalRepository();

        $dm = $composer->getDownloadManager();
        $im = $composer->getInstallationManager();

        $errors = array();
        $unpushedChanges = array();

        // list packages
        foreach ($installedRepo->getPackages() as $package) {
            $downloader = $dm->getDownloaderForInstalledPackage($package);

            if ($downloader instanceof VcsDownloader) {
                $targetDir = $im->getInstallPath($package);

                if ($changes = $downloader->getLocalChanges($targetDir)) {
                    $errors[$targetDir] = $changes;
                }

                if ($downloader instanceof DvcsDownloaderInterface) {
                    if ($unpushed = $downloader->getUnpushedChanges($targetDir)) {
                        $unpushedChanges[$targetDir] = $unpushed;
                    }
                }
            }
        }

        // output errors/warnings
        if (!$errors && !$unpushed) {
            $output->writeln('<info>No local changes</info>');
        } else {

            if ($errors) {
                $output->writeln('<error>You have changes in the following dependencies:</error>');

                foreach ($errors as $path => $changes) {
                    if ($input->getOption('verbose')) {
                        $indentedChanges = implode("\n", array_map(function ($line) {
                            return '    ' . $line;
                        }, explode("\n", $changes)));
                        $output->writeln('<info>'.$path.'</info>:');
                        $output->writeln($indentedChanges);
                    } else {
                        $output->writeln($path);
                    }
                }
            }

            if ($unpushedChanges) {
                $output->writeln('<warning>You have unpushed changes on the current branch in the following dependencies:</warning>');

                foreach ($unpushedChanges as $path => $changes) {
                    if ($input->getOption('verbose')) {
                        $indentedChanges = implode("\n", array_map(function ($line) {
                            return '    ' . $line;
                        }, explode("\n", $changes)));
                        $output->writeln('<info>'.$path.'</info>:');
                        $output->writeln($indentedChanges);
                    } else {
                        $output->writeln($path);
                    }
                }
            }

            if (!$input->getOption('verbose')) {
                $output->writeln('Use --verbose (-v) to see modified files');
            }
        }

        return ($errors ? 1 : 0) + ($unpushedChanges ? 2 : 0);
    }
}
