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
use Neos\Flow\Security\AccountRepository;
use Neos\Flow\Validation\Exception\InvalidValidationOptionsException;
use Neos\Flow\Validation\Validator\NotEmptyValidator;
use Neos\Flow\Validation\Validator\StringLengthValidator;
use Neos\Form\Core\Model\AbstractFormElement;
use Neos\Form\Core\Model\FormDefinition;
use Neos\Form\Exception as FormException;
use Neos\Form\Exception\TypeDefinitionNotFoundException;
use Neos\Form\Exception\TypeDefinitionNotValidException;
use Neos\Form\FormElements\Section;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Validation\Validator\UserDoesNotExistValidator;
use Neos\Party\Domain\Repository\PartyRepository;
use Neos\Setup\Step\AbstractStep;

/**
 * @Flow\Scope("singleton")
 */
class AdministratorStep extends AbstractStep
{

    /**
     * @Flow\Inject
     * @var AccountRepository
     */
    protected $accountRepository;

    /**
     * @Flow\Inject
     * @var PartyRepository
     */
    protected $partyRepository;

    /**
     * @Flow\Inject
     * @var UserService
     */
    protected $userService;

    public function __construct()
    {
        $this->optional = true;
    }

    /**
     * Returns the form definitions for the step
     *
     * @param FormDefinition $formDefinition
     * @return void
     * @throws InvalidValidationOptionsException | FormException | TypeDefinitionNotFoundException | TypeDefinitionNotValidException
     */
    protected function buildForm(FormDefinition $formDefinition): void
    {
        $page1 = $formDefinition->createPage('page1');
        $page1->setRenderingOption('header', 'Create administrator account');

        $introduction = $page1->createElement('introduction', 'Neos.Form:StaticText');
        $introduction->setProperty('text', 'Enter the personal data and credentials for your backend account:');

        /** @var Section $personalSection */
        $personalSection = $page1->createElement('personalSection', 'Neos.Form:Section');
        $personalSection->setLabel('Personal Data');

        /** @var AbstractFormElement $firstName */
        $firstName = $personalSection->createElement('firstName', 'Neos.Form:SingleLineText');
        $firstName->setLabel('First name');
        $firstName->addValidator(new NotEmptyValidator());
        $firstName->addValidator(new StringLengthValidator(['minimum' => 1, 'maximum' => 255]));

        /** @var AbstractFormElement $lastName */
        $lastName = $personalSection->createElement('lastName', 'Neos.Form:SingleLineText');
        $lastName->setLabel('Last name');
        $lastName->addValidator(new NotEmptyValidator());
        $lastName->addValidator(new StringLengthValidator(['minimum' => 1, 'maximum' => 255]));

        /** @var Section $credentialsSection */
        $credentialsSection = $page1->createElement('credentialsSection', 'Neos.Form:Section');
        $credentialsSection->setLabel('Credentials');

        /** @var AbstractFormElement $username */
        $username = $credentialsSection->createElement('username', 'Neos.Form:SingleLineText');
        $username->setLabel('Username');
        $username->addValidator(new NotEmptyValidator());
        $username->addValidator(new UserDoesNotExistValidator());

        /** @var AbstractFormElement $password */
        $password = $credentialsSection->createElement('password', 'Neos.Form:PasswordWithConfirmation');
        $password->addValidator(new NotEmptyValidator());
        $password->addValidator(new StringLengthValidator(['minimum' => 6, 'maximum' => 255]));
        $password->setLabel('Password');
        $password->setProperty('passwordDescription', 'At least 6 characters');

        $formDefinition->setRenderingOption('skipStepNotice', 'If you skip this step make sure that you have an existing user or create one with the user:create command');
    }

    /**
     * This method is called when the form of this step has been submitted
     *
     * @param array $formValues
     * @return void
     */
    public function postProcessFormValues(array $formValues): void
    {
        $this->userService->createUser($formValues['username'], $formValues['password'], $formValues['firstName'], $formValues['lastName'], ['Neos.Neos:Administrator']);
    }
}
