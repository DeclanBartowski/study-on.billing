<?php

namespace App\Tests\Controller;

use App\DataFixtures\CourseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Course;
use App\Entity\User;
use App\Service\PaymentService;
use App\Tests\AbstractTest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CourseControllerTest extends AbstractTest
{

    public function testEditCourseWithSuperAdmin(): void
    {
        $client = self::getClient();
        $entityManager = self::getEntityManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'admin@mail.ru']);

        $courseRepository = $entityManager->getRepository(Course::class);
        $course = $courseRepository->findOneBy(['code' => 'react-developer']);

        $client->loginUser($user);

        $data = [
            'name' => 'Updated Course Name',
            'price' => 200,
            'type' => Course::TYPE_RENT
        ];

        $client->request(
            'POST',
            '/api/v1/courses/react-developer/edit',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $updatedCourse = $entityManager->getRepository(Course::class)->find($course->getId());
        $this->assertEquals('Updated Course Name', $updatedCourse->getTitle());
        $this->assertEquals(200, $updatedCourse->getPrice());
        $this->assertEquals(Course::TYPE_RENT, $updatedCourse->getType());
    }

    public function testEditCourseWithoutPermission(): void
    {
        $client = self::getClient();
        $entityManager = self::getEntityManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'casualuser@msil.ru']);

        $course = $entityManager->getRepository(Course::class)->findOneBy([]);

        $client->loginUser($user);

        $client->request('POST', '/api/v1/courses/' . $course->getCode() . '/edit');
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCreateNewCourse(): void
    {
        $client = self::getClient();
        $entityManager = self::getEntityManager();
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => 'admin@mail.ru']);
        $client->loginUser($user);

        $data = [
            'name' => 'New Course',
            'code' => 'new-course',
            'price' => 150,
            'type' => Course::TYPE_FULL
        ];

        $client->request(
            'POST',
            '/api/v1/courses/new',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data)
        );

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);

        $course = $entityManager->getRepository(Course::class)->findOneBy(['code' => 'new-course']);
        $this->assertNotNull($course);
        $this->assertEquals('New Course', $course->getTitle());
    }

    protected function getFixtures(): array
    {
        $container = self::getContainer();
        return [
            new UserFixtures(
                $container->get(UserPasswordHasherInterface::class),
                $container->get(PaymentService::class)),
            new CourseFixtures()
        ];
    }
}
