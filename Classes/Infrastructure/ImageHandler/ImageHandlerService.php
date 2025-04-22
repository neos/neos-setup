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
     * @var array<int, array{driverName: string, description: string, requiredPhpExtension: string|null}>
     */
    protected readonly array $supportedImageHandlersByPreference;

    /**
     * @Flow\InjectConfiguration(path="requiredImageFormats")
     * @var string[]
     */
    protected array $requiredImageFormats;

    /**
     * @var ImagineFactory
     */
    protected $imagineFactory;

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
     * @return ImageHandlerDescriptor[]
     */
    private function readSupportedImageHandlers(): array
    {
        $imageHandlers = [];
        foreach ($this->supportedImageHandlersByPreference as $c) {
            $imageHandlers[] = new ImageHandlerDescriptor(
                driverName: $c['driverName'],
                description: $c['description'],
                requiredPhpExtension: $c['requiredPhpExtension'] ?? '',
                requiredPhpConfiguration: $c['requiredPhpConfiguration'] ?? [],
            );
        }
        return $imageHandlers;
    }

    public function determineAvailabilityForImageHandlers(): ImageHandlerDiagnosticsCollection
    {
        $imageHandlerDiagnostics = [];

        foreach ($this->readSupportedImageHandlers() as $supportedImageHandler) {
            $unsupportedBecause = [];
            if ($supportedImageHandler->requiredPhpExtension != '' && !\extension_loaded($supportedImageHandler->requiredPhpExtension)) {
                $unsupportedBecause[] = 'PHP Extension "' . $supportedImageHandler->requiredPhpExtension . '" is not loaded.';
            }
            if (!$this->imagineFactory->isDriverAvailable(ucfirst($supportedImageHandler->driverName))) {
                $unsupportedBecause[] = 'Imagine driver "' . $supportedImageHandler->driverName . '" is not available.';
            }

            foreach ($supportedImageHandler->requiredPhpConfiguration as $key => $expectedValue) {
                $actual = ini_get($key);
                // Some 1/true juggling for getting the types match, because ffi.enabled=true gets parsed to 1.
                if ($expectedValue === 'true' && $actual === '1') {
                    $actual = 'true';
                }
                if ($expectedValue !== $actual) {
                    $iniPath = php_ini_loaded_file();
                    $unsupportedBecause[] = 'PHP configuration "' . $key . '" is not set to "' . $expectedValue . '", but to "' . ini_get($key) . '" instead.' . PHP_EOL . sprintf('        echo %s=%s >> %s', $key, $expectedValue, $iniPath);

                }
            }

            if (\count($unsupportedBecause) === 0) {
                $this->findUnsupportedImageFormats($supportedImageHandler->driverName, $unsupportedBecause);
            }

            $imageHandlerDiagnostics[] = new ImageHandlerDiagnostics(
                descriptor: $supportedImageHandler,
                isReady: count($unsupportedBecause) === 0,
                statusDetails: $unsupportedBecause,
            );
        }
        return new ImageHandlerDiagnosticsCollection($imageHandlerDiagnostics);
    }

    private function findUnsupportedImageFormats(string $driver, array &$unsupportedBecause): void
    {
        $imagine = $this->imagineFactory->createDriver(ucfirst($driver));

        foreach ($this->requiredImageFormats as $imageFormat => $testFile) {
            try {
                $imagine->load(file_get_contents($testFile));
            } /** @noinspection BadExceptionsProcessingInspection */ catch (\Exception $exception) {
                $unsupportedBecause[] = 'Image format "' . $imageFormat . '" not supported: ' . $exception->getMessage();
            }
        }
    }
}
