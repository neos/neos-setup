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
     * @var ImagineFactory
     */
    protected $imagineFactory;

    /**
     * Array of ImageHandlerDescriptor objects converted from YAML configuration
     * The ordering is from "least-fitting" to "best-fitting"
     * @var ImageHandlerDescriptor[]
     */
    private readonly array $supportedImageHandlersByPreference;

    private const REQUIRED_IMAGE_FORMATS = [
        'jpg' => 'resource://Neos.Neos/Private/Installer/TestImages/Test.jpg',
        'gif' => 'resource://Neos.Neos/Private/Installer/TestImages/Test.gif',
        'png' => 'resource://Neos.Neos/Private/Installer/TestImages/Test.png',
    ];

    public function __construct()
    {
        //
        // FIXME: It seems there is this hack and in the image factory there is a hack too now (: https://github.com/neos/imagine/pull/11
        // Hack. We instantiate the unproxied class without injected settings.
        // This is to allow to still reconfigure the image driver, even if it is disabled.
        // The "driver" Gd for Imagine must be enabled by settings, check Neos.Imagine.enabledDrivers. Or use ./flow setup:imagehandler
        // otherwise ImagineFactory::injectSettings will be called by the object framework and we validate inside `injectSettings` that the driver must be enabled.
        //
        class_exists(ImagineFactory::class);
        $this->imagineFactory = new (ImagineFactory::class . '_Original')();


        // sorted from worst-fitting to best-fitting
        $this->supportedImageHandlersByPreference = [
            new ImageHandlerDescriptor(
                driverName: 'Gd',
                description: 'GD Library - generally slow, not recommended in production',
                requiredPhpExtension: 'gd',
                requiredPhpConfiguration: [],
            ),
            new ImageHandlerDescriptor(
                driverName: 'Gmagick',
                description: '- Gmagick php module',
                requiredPhpExtension: 'gmagick',
                requiredPhpConfiguration: [],
            ),
            // Imagick seems to be better maintained on PECL than gmagick, that's why we prefer to use it over gmagick.
            new ImageHandlerDescriptor(
                driverName: 'Imagick',
                description: '- ImageMagick php module',
                requiredPhpExtension: 'imagick',
                requiredPhpConfiguration: [],
            ),
            new ImageHandlerDescriptor(
                driverName: 'Vips',
                description: '(legacy Extension Mode) - fast and memory efficient, needs rokka/imagine-vips + jcupitt/vips:^1.0',
                requiredPhpExtension: 'vips',
                requiredPhpConfiguration: [],
            ),
            new ImageHandlerDescriptor(
                driverName: 'Vips',
                description: '(future-proof FFI mode) - fast and memory efficient, needs rokka/imagine-vips and FFI enabled',
                // no PHP Extension needed
                requiredPhpExtension: '',
                requiredPhpConfiguration: [
                    'ffi.enable' => 'true',
                    // from https://github.com/libvips/php-vips?tab=readme-ov-file:
                    //    Finally, on php 8.3 and later you need to disable stack overflow tests.
                    //    php-vips executes FFI callbacks off the main thread and this confuses those checks, at least in php 8.3.0.
                    'zend.max_allowed_stack_size' => '-1',
                ],
            ),
        ];
    }

    public function determineAvailabilityForImageHandlers(): ImageHandlerDiagnosticsCollection
    {
        $imageHandlerDiagnostics = [];

        foreach ($this->supportedImageHandlersByPreference as $supportedImageHandler) {
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

        foreach (self::REQUIRED_IMAGE_FORMATS as $imageFormat => $testFile) {
            try {
                $imagine->load(file_get_contents($testFile));
            } /** @noinspection BadExceptionsProcessingInspection */ catch (\Exception $exception) {
                $unsupportedBecause[] = 'Image format "' . $imageFormat . '" not supported: ' . $exception->getMessage();
            }
        }
    }
}
