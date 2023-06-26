<?php

/**
 * @file
 * Contains \WordPressComposerManaged\ComposerScripts.
 */

namespace WordPressComposerManaged;

use Composer\Script\Event;

class ComposerScripts
{

   /**
    * Add a dependency to the upstream-configuration section of a custom upstream.
    *
    * The upstream-configuration/composer.json is a place to put modules, themes
    * and other dependencies that will be inherited by all sites created from
    * the upstream. Separating the upstream dependencies from the site dependencies
    * has the advantage that changes can be made to the upstream without causing
    * conflicts in the downstream sites.
    *
    * To add a dependency to an upstream:
    *
    *    composer upstream-require wpackagist-plugin/plugin-name
    *
    * Important: Dependencies should only be removed from upstreams with caution.
    * The module / theme must be uninstalled from all sites that are using it
    * before it is removed from the code base; otherwise, the module cannot be
    * cleanly uninstalled.
    */
    public static function upstreamRequire(Event $event)
    {
        $io = $event->getIO();
        $composer = $event->getComposer();
        $name = $composer->getPackage()->getName();
        $gitRepoUrl = exec('git config --get remote.origin.url');

        // Refuse to run if:
        //   - This is a clone of the standard Pantheon upstream, and it hasn't been renamed
        //   - This is an local working copy of a Pantheon site instread of the upstream
        $isPantheonStandardUpstream = (strpos($name, 'pantheon-systems/wordpress-composer-managed') !== false);
        $isPantheonSite = (strpos($gitRepoUrl, '@codeserver') !== false);

        if ($isPantheonStandardUpstream || $isPantheonSite) {
            $io->writeError("<info>The upstream-require command can only be used with a custom upstream</info>");
            $io->writeError("<info>See https://pantheon.io/docs/create-custom-upstream for information on how to create a custom upstream.</info>" . PHP_EOL);
            throw new \RuntimeException("Cannot use upstream-require command with this project.");
        }

        // Find arguments that look like projects.
        $packages = [];
        foreach ($event->getArguments() as $arg) {
            if (preg_match('#[a-zA-Z][a-zA-Z0-9_-]*/[a-zA-Z][a-zA-Z0-9]:*[~^]*[0-9a-z._-]*#', $arg)) {
                $packages[] = $arg;
            }
        }

        // Insert the new projects into the upstream-configuration composer.json
        // without updating the lock file or downloading the projects
        $packagesParam = implode(' ', $packages);
        $cmd = "composer --working-dir=upstream-configuration require --no-update $packagesParam";
        $io->writeError($cmd . PHP_EOL);
        passthru($cmd);

        // Update composer.lock & etc. if present
        static::updateLocalDependencies($io, $packages);
    }

   /**
    * Prepare for Composer to update dependencies.
    */
    public static function preUpdate(Event $event)
    {
    }

   /**
    * Update the composer.lock file and so on.
    *
    * Upstreams should *not* commit the composer.lock file. If a local working
    * copy
    */
    private static function updateLocalDependencies($io, $packages)
    {
        if (!file_exists('composer.lock')) {
            return;
        }

        $io->writeError("<warning>composer.lock file present; do not commit composer.lock to a custom upstream, but updating for the purpose of local testing.");

        // Remove versions from the parameters, if any
        $versionlessPackages = array_map(
            function ($package) {
                return preg_replace('/:.*/', '', $package);
            },
            $packages
        );

        // Update the project-level composer.lock file
        $versionlessPackagesParam = implode(' ', $versionlessPackages);
        $cmd = "composer update $versionlessPackagesParam";
        $io->writeError($cmd . PHP_EOL);
        passthru($cmd);
    }
}
