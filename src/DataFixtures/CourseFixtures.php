<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Entity\Course;

class CourseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $courses = [
            [
                'code' => 'react-developer',
                'type' => Course::TYPE_FULL,
                'price' => 10000,
            ],
            [
                'code' => 'fullstack_developer',
                'type' => Course::TYPE_FREE,
                'price' => 7000,
            ],
            [
                'code' => 'qa-engineer',
                'type' => Course::TYPE_RENT,
                'price' => 6000,
            ],
        ];

        foreach ($courses as $courseItem) {
            $course = new Course();
            $course->setCode($courseItem['code']);
            $course->setPrice($courseItem['price']);
            $course->setType($courseItem['type']);
            $manager->persist($course);
            $manager->flush();
        }

        $manager->flush();
    }
}
