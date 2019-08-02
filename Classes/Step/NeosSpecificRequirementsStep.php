<?php
namespace Neos\Neos\Setup\Step;

/*
 * This file is part of the Neos.Neos.Setup package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Configuration\Source\YamlSource;
use Neos\Flow\Package\Exception\UnknownPackageException;
use Neos\Flow\Package\FlowPackageInterface;
use Neos\Flow\Package\PackageManager;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Form\Core\Model\AbstractFormElement;
use Neos\Form\Exception as FormException;
use Neos\Form\Exception\TypeDefinitionNotFoundException;
use Neos\Form\Exception\TypeDefinitionNotValidException;
use Neos\Form\FormElements\Section;
use Neos\Utility\Arrays;
use Neos\Utility\Files;
use Neos\Form\Core\Model\FormDefinition;
use Neos\Imagine\ImagineFactory;
use Neos\Setup\Step\AbstractStep;

/**
 * @Flow\Scope("singleton")
 */
class NeosSpecificRequirementsStep extends AbstractStep
{
    /**
     * @Flow\Inject
     * @var YamlSource
     */
    protected $configurationSource;

    /**
     * @Flow\Inject
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @Flow\Inject
     * @var ImagineFactory
     */
    protected $imagineFactory;

    /**
     * @Flow\Inject
     * @var PackageManager
     */
    protected $packageManager;

    /**
     * @Flow\Inject
     * @var PersistenceManagerInterface
     */
    protected $persistenceManager;

    /**
     * @param FormDefinition $formDefinition
     * @throws \ReflectionException | FormException | TypeDefinitionNotFoundException | TypeDefinitionNotValidException | UnknownPackageException
     */
    protected function buildForm(FormDefinition $formDefinition): void
    {
        $page1 = $formDefinition->createPage('page1');
        $page1->setRenderingOption('header', 'Neos requirements check');

        /** @var Section $imageSection */
        $imageSection = $page1->createElement('connectionSection', 'Neos.Form:Section');
        $imageSection->setLabel('Image Manipulation');

        /** @var AbstractFormElement $formElement */
        $formElement = $imageSection->createElement('imageLibrariesInfo', 'Neos.Form:StaticText');
        $formElement->setProperty('text', 'We checked for supported image manipulation libraries on your server.
		Only one is needed and we select the best one available for you.
		Using GD in production environment is not recommended as it has some issues and can easily lead to blank pages due to memory exhaustion.');
        $formElement->setProperty('elementClassAttribute', 'alert alert-primary');

        $foundImageHandler = false;
        foreach (['gd', 'gmagick', 'imagick'] as $extensionName) {
            /** @var AbstractFormElement $formElement */
            $formElement = $imageSection->createElement($extensionName, 'Neos.Form:StaticText');

            if (\extension_loaded($extensionName)) {
                $unsupportedFormats = $this->findUnsupportedImageFormats($extensionName);
                if (\count($unsupportedFormats) === 0) {
                    $formElement->setProperty('text', 'PHP extension "' . $extensionName .'" is installed');
                    $formElement->setProperty('elementClassAttribute', 'alert alert-info');
                    $foundImageHandler = $extensionName;
                } else {
                    $formElement->setProperty('text', 'PHP extension "' . $extensionName . '" is installed but lacks support for ' . implode(', ', $unsupportedFormats));
                    $formElement->setProperty('elementClassAttribute', 'alert alert-default');
                }
            } else {
                $formElement->setProperty('text', 'PHP extension "' . $extensionName . '" is not installed');
                $formElement->setProperty('elementClassAttribute', 'alert alert-default');
            }
        }

        if ($foundImageHandler === false) {
            /** @var AbstractFormElement $formElement */
            $formElement = $imageSection->createElement('noImageLibrary', 'Neos.Form:StaticText');
            $formElement->setProperty('text', 'No suitable PHP extension for image manipulation was found. Please install one of the required PHP extensions and restart the php process. Then proceed with the setup.');
            $formElement->setProperty('elementClassAttribute', 'alert alert-error');
            return;
        }
        /** @var AbstractFormElement $formElement */
        $formElement = $imageSection->createElement('configuredImageLibrary', 'Neos.Form:StaticText');
        $formElement->setProperty('text', 'Neos will be configured to use extension "' . $foundImageHandler . '"');
        $formElement->setProperty('elementClassAttribute', 'alert alert-success');
        $hiddenField = $imageSection->createElement('imagineDriver', 'Neos.Form:HiddenField');
        $hiddenField->setDefaultValue(ucfirst($foundImageHandler));
    }

    /**
     * @param string $driver
     * @return array Not supported image format
     * @throws \ReflectionException | UnknownPackageException
     */
    protected function findUnsupportedImageFormats($driver): array
    {
        $this->imagineFactory->injectSettings(['driver' => ucfirst($driver)]);
        $imagine = $this->imagineFactory->create();
        $unsupportedFormats = [];

        foreach (['jpg', 'gif', 'png'] as $imageFormat) {
            /** @var FlowPackageInterface $neosPackage */
            $neosPackage = $this->packageManager->getPackage('Neos.Neos');
            $imagePath = Files::concatenatePaths([$neosPackage->getResourcesPath(), 'Private/Installer/TestImages/Test.' . $imageFormat]);

            try {
                $imagine->open($imagePath);
            } /** @noinspection BadExceptionsProcessingInspection */ catch (\Exception $exception) {
                $unsupportedFormats[] = sprintf('"%s"', $imageFormat);
            }
        }

        return $unsupportedFormats;
    }

    /**
     * @param array $formValues
     */
    public function postProcessFormValues(array $formValues): void
    {
        $this->distributionSettings = Arrays::setValueByPath($this->distributionSettings, 'Neos.Imagine.driver', $formValues['imagineDriver']);
        $this->configurationSource->save(FLOW_PATH_CONFIGURATION . ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, $this->distributionSettings);

        $this->configurationManager->refreshConfiguration();
    }
}
