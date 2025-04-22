<?php
declare(strict_types=1);

namespace Neos\Neos\Setup\Command;

/*
 * This file is part of the Neos.CliSetup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Core\Bootstrap;
use Neos\Neos\Setup\Infrastructure\ImageHandler\ImageHandlerService;
use Neos\Utility\Arrays;
use Symfony\Component\Yaml\Yaml;

class SetupCommandController extends CommandController
{
    #[Flow\Inject]
    protected ImageHandlerService $imageHandlerService;

    #[Flow\Inject]
    protected Bootstrap $bootstrap;

    public function imageHandlerCommand(string $driver = null): void
    {
        $imageHandlers = $this->imageHandlerService->determineAvailabilityForImageHandlers();

        $this->outputLine('<info>%d handler(s) ready</info>, <comment>%d unavailable</comment>:', [$imageHandlers->readyCount(), $imageHandlers->unavailableCount()]);
        foreach ($imageHandlers as $h) {
            if ($h->isReady) {
                $this->outputLine('  - ✅ <info>%s</info> %s', [$h->descriptor->driverName, $h->descriptor->description]);
            } else {
                $this->outputLine('  - <comment>%s</comment> %s', [$h->descriptor->driverName, $h->descriptor->description]);
            }

            foreach ($h->statusDetails as $detail) {
                $this->outputLine('    - %s', [$detail]);
            }
        }

        $this->outputLine('');

        if ($imageHandlers->readyCount() === 0) {
            $enableGdOnWindowsHelpText = PHP_OS_FAMILY === 'Windows'
                ? ' To enable Gd for basic image driver support during development, uncomment <em>;extension=gd</em> in your php.ini (remove the <em>;</em>).'
                : '';

            $this->outputLine(
                'No available image handler found.%s',
                [
                    $enableGdOnWindowsHelpText
                ]
            );
            $this->quit(1);
        }

        if ($driver === null || $driver === '') {
            $options = [];
            foreach ($imageHandlers->driverNames() as $driverName) {
                if ($imageHandlers->isReady($driverName)) {
                    $options[$driverName] = 'ready to use';
                } else {
                    $options[$driverName] = 'unavailable on your system';
                }
            }
            $driver = $this->output->select(
                sprintf('<comment>Select Image Handler to use</comment> (ENTER=<info>%s</info>): ', $imageHandlers->preferredDriverName()),
                $options,
                $imageHandlers->preferredDriverName()
            );
        }

        if (!$imageHandlers->isReady($driver)) {
            $this->outputLine('');
            $this->outputLine('<comment>WARNING: Driver %s is not ready to use. We will nevertheless configure it in Neos,</comment>', [$driver]);
            $this->outputLine('<comment>         but you need to fix the prerequisites, else image rendering is BROKEN.</comment>');
        }

        $settingsToWrite = [
            'driver' => $driver
        ];

        $this->outputLine('');
        $this->outputLine('Enabled driver <info>%s</info>:', [$driver]);
        $settingsToWrite['enabledDrivers'][$driver] = true;

        $filename = sprintf('%s%s/Settings.Imagehandling.yaml', FLOW_PATH_CONFIGURATION, $this->bootstrap->getContext()->__toString());
        self::writeSettings($filename, 'Neos.Imagine', $settingsToWrite);
        if (strtolower($driver) === 'vips') {
            self::writeSettings($filename, 'Neos.Media.image.defaultOptions.interlace', null);
        }
        $this->outputSettings($filename);
        $this->outputLine();
        $this->outputLine('The new image handler setting were written to <info>%s</info>', [$filename]);
    }

    /**
     * Write the settings to the given path, existing configuration files are created or modified
     *
     * @param string $filename The filename the settings are stored in
     * @param string $path The configuration path
     * @param mixed $settings The actual settings to write
     */
    private static function writeSettings(string $filename, string $path, mixed $settings): void
    {
        if (file_exists($filename)) {
            $previousSettings = Yaml::parseFile($filename) ?? [];
        } else {
            $previousSettings = [];
        }
        $newSettings = Arrays::setValueByPath($previousSettings, $path, $settings);
        file_put_contents($filename, YAML::dump($newSettings, 10, 2));
    }

    private function outputSettings(string $filename): void
    {
        $this->output('<info>%s</info>', [YAML::dump(Yaml::parseFile($filename), 10, 2)]);
    }
}
