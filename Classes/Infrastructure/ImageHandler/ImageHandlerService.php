<?php
declare(strict_types=1);

namespace Neos\Neos\Setup\Infrastructure\ImageHandler;

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
use Neos\Imagine\ImagineFactory;

class ImageHandlerService
{
    /**
     * @Flow\InjectConfiguration(path="supportedImageHandlersByPreference")
     * @var array<int, array{driverName: string, description: string}>
     */
    protected array $supportedImageHandlersByPreference;

    /**
     * @Flow\InjectConfiguration(path="requiredImageFormats")
     * @var string[]
     */
    protected array $requiredImageFormats;

    /**
     * @Flow\InjectConfiguration(path="enabledDrivers", package="Neos.Imagine")
     * @var array<string, bool>
     */
    protected array $enabledDrivers;

    /**
     * @var ImagineFactory
     */
    protected $imagineFactory;

    /**
     * @var array<int,ImageHandler>
     */
    protected array $availableImageHandlers;

    public function __construct()
    {
        //
        // Hack. We instantiate the unproxied class without injected settings.
        // This is to allow to still reconfigure the image driver, even if it is disabled.
        // The "driver" Gd for Imagine must be enabled by settings, check Neos.Imagine.enabledDrivers. Or use ./flow setup:imagehandler
        // otherwise ImagineFactory::injectSettings will be called by the object framework and we validate inside `injectSettings` that the driver must be enabled.
        //
        class_exists(ImagineFactory::class);
        $this->imagineFactory = new (ImagineFactory::class . '_Original')();
    }

    /**
     * Return all Imagine drivers that support the loading of the required images
     *
     * Ignoring the configuration `Neos.Imagine.enabledDrivers`
     *
     * @return array<int,ImageHandler>
     */
    public function getAvailableImageHandlers(): array
    {
        if (isset($this->availableImageHandlers)) {
            return $this->availableImageHandlers;
        }
        $availableImageHandlers = [];
        foreach ($this->supportedImageHandlersByPreference as [
            'driverName' => $driverName,
            'description' => $description
        ]) {
            if (\extension_loaded(strtolower($driverName)) && $this->imagineFactory->isDriverAvailable(ucfirst($driverName))) {
                $unsupportedFormats = $this->findUnsupportedImageFormats($driverName);
                if (\count($unsupportedFormats) === 0) {
                    $availableImageHandlers[] = new ImageHandler(
                        driverName: $driverName,
                        description: $description
                    );
                }
            }
        }
        return $this->availableImageHandlers = $availableImageHandlers;
    }

    public function getPreferredImageHandler(): ImageHandler
    {
        $availableImageHandlers = $this->getAvailableImageHandlers();
        return reset($availableImageHandlers)
            ?: throw new \RuntimeException('No supported image handler found.');
    }

    public function isDriverEnabledInConfiguration(string $driverName): bool
    {
        return (bool)($this->enabledDrivers[$driverName] ?? false);
    }

    /**
     * @param string $driver
     * @return array Not supported image formats
     */
    private function findUnsupportedImageFormats(string $driver): array
    {
        $imagine = $this->imagineFactory->createDriver(ucfirst($driver));
        $unsupportedFormats = [];

        foreach ($this->requiredImageFormats as $imageFormat => $testFile) {
            try {
                $imagine->load(file_get_contents($testFile));
            } /** @noinspection BadExceptionsProcessingInspection */ catch (\Exception $exception) {
                $unsupportedFormats[] = $imageFormat;
            }
        }
        return $unsupportedFormats;
    }
}
