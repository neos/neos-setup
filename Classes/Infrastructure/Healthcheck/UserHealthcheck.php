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
            There is no Neos user created yet. To create one please run
            <code>./flow user:create --roles Administrator</code>
            and follow the instructions.
            MSG, Status::ERROR);
        }

        return new Health('At least one Neos user exists', Status::OK);
    }
}
