<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Entity\User;

class UserFixtures extends Fixture
{

    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
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

        foreach ($users as $user) {
            $userEntity = new User();
            $userEntity->setEmail($user['email']);
            $userEntity->setRoles($user['roles']);

            $hashedPassword = $this->passwordHasher->hashPassword($userEntity, $user['password']);
            $userEntity->setPassword($hashedPassword);

            $manager->persist($userEntity);
            $manager->flush();
        }
    }
}
