<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Service\PaymentService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{

    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private PaymentService $paymentService
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            [
                'email' => 'casualuser@msil.ru',
                'roles' => [''],
                'password' => '2486200',
            ],
            [
                'email' => 'admin@mail.ru',
                'roles' => ['ROLE_SUPER_ADMIN'],
                'password' => '2486200',
            ],
        ];
        $manager->wrapInTransaction(function () use ($users, $manager) {
            foreach ($users as $user) {
                $userEntity = new User();
                $userEntity->setEmail($user['email']);
                $userEntity->setRoles($user['roles']);

                $hashedPassword = $this->passwordHasher->hashPassword($userEntity, $user['password']);
                $userEntity->setPassword($hashedPassword);

                $manager->persist($userEntity);
                $this->paymentService->deposit($userEntity, $this->paymentService->getInitialBalance());
            }
            $manager->flush();
        });
    }
}
