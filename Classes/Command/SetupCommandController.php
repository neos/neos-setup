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
        $availableImageHandlers = $this->imageHandlerService->getAvailableImageHandlers();

        if (count($availableImageHandlers) === 0) {
            $enableGdOnWindowsHelpText = PHP_OS_FAMILY === 'Windows'
                ? ' To enabled Gd for basic image driver support during development, uncomment (remove the <em>;</em>) <em>;extension=gd</em> in your php.ini.'
                : '';

            $this->outputLine(
                sprintf(
                    'No supported image handler found.%s',
                    $enableGdOnWindowsHelpText
                )
            );
            $this->quit(1);
        }

        $availableDriversWithDescription = [];
        foreach ($availableImageHandlers as $imageHandler) {
            $availableDriversWithDescription[$imageHandler->driverName] = $imageHandler->description;
        }

        if ($driver === null || $driver === '') {
            $preferredImageHandler = $this->imageHandlerService->getPreferredImageHandler();
            $driver = $this->output->select(
                sprintf('Select Image Handler (<info>%s</info>): ', $preferredImageHandler->driverName),
                $availableDriversWithDescription,
                $preferredImageHandler->driverName
            );
        }

        $settingsToWrite = [
            'driver' => $driver
        ];

        if ($this->imageHandlerService->isDriverEnabledInConfiguration($driver) === false) {
            $this->outputLine('Enabled driver.');
            $settingsToWrite['enabledDrivers'][$driver] = true;
        }

        $filename = sprintf('Configuration/%s/Settings.Imagehandling.yaml', $this->bootstrap->getContext()->__toString());
        $this->outputLine();
        $this->output(sprintf('<info>%s</info>', $this->writeSettings($filename, 'Neos.Imagine', $settingsToWrite)));
        $this->outputLine();
        $this->outputLine(sprintf('The new image handler setting were written to <info>%s</info>', $filename));
    }

    /**
     * Write the settings to the given path, existing configuration files are created or modified
     *
     * @param string $filename The filename the settings are stored in
     * @param string $path The configuration path
     * @param mixed $settings The actual settings to write
     * @return string The added yaml code
     */
    private function writeSettings(string $filename, string $path, mixed $settings): string
    {
        if (file_exists($filename)) {
            $previousSettings = Yaml::parseFile($filename) ?? [];
        } else {
            $previousSettings = [];
        }
        $newSettings = Arrays::setValueByPath($previousSettings, $path, $settings);
        file_put_contents($filename, YAML::dump($newSettings, 10, 2));
        return YAML::dump(Arrays::setValueByPath([],$path, $settings), 10, 2);
    }
}
