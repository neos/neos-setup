<?php

namespace Neos\Neos\Setup\Infrastructure\Healthcheck;

use Neos\Neos\Domain\Repository\UserRepository;
use Neos\Setup\Domain\Health;
use Neos\Setup\Domain\HealthcheckInterface;
use Neos\Setup\Domain\Status;

class UserHealthcheck implements HealthcheckInterface
{
    public function __construct(
        private readonly UserRepository $userRepository
    ) {
    }

    public function getTitle(): string
    {
        return 'Neos user';
    }

    public function execute(): Health
    {
        $users = $this->userRepository->findAll();
        if (!$users->count()) {
            return new Health(<<<'MSG'
            No Neos user created yet. To create an user run <code>./flow user:create --roles Administrator admin admin Jon Doe</code>
            MSG, Status::ERROR);
        }

        return new Health('At least one Neos user exists', Status::OK);
    }
}
