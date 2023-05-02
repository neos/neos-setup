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
     * @Flow\Inject
     */
    protected ImagineFactory $imagineFactory;

    /**
     * @var array<int,ImageHandler>
     */
    protected array $availableImageHandlers;

    /**
     * Return all Imagine drivers that support the loading of the required images
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
            if (\extension_loaded(strtolower($driverName))) {
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

    public function getPreferredImageHandler(): ?ImageHandler
    {
        $availableImageHandlers = $this->getAvailableImageHandlers();
        return reset($availableImageHandlers)
            ?: throw new \RuntimeException('No supported image handler found.');
    }

    /**
     * @param string $driver
     * @return array Not supported image formats
     */
    private function findUnsupportedImageFormats(string $driver): array
    {
        $this->imagineFactory->injectSettings(['driver' => ucfirst($driver)]);
        $imagine = $this->imagineFactory->create();
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
