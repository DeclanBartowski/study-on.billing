<?php

namespace App\Controller;

use App\Entity\Course;
use App\Entity\User;
use App\Exception\NotEnoughFundsException;
use App\Repository\CourseRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

#[Route('/api/v1/courses')]
class CourseController extends AbstractController
{
    #[OA\Get(
        path: "/api/v1/courses",
        operationId: "getCourses",
        description: "Получение списка всех курсов",
        summary: "Получение списка доступных курсов",
        tags: ["Course"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Список курсов",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "code", type: "string", example: "react-developer"),
                            new OA\Property(property: "type", type: "number", example: "1"),
                            new OA\Property(property: "price", type: "string", example: "10000.00", nullable: true)
                        ],
                        type: "object"
                    )
                )
            )
        ]
    )]
    #[Route('', name: 'api_v1_courses')]
    public function index(CourseRepository $courseRepository): Response
    {
        $courses = $courseRepository->findAll();
        $responseData = [];

        foreach ($courses as $course) {
            $courseData = [
                'code' => $course->getCode(),
                'type' => $course->getType(),
            ];

            if ($course->getType() !== Course::TYPE_FREE && $course->getPrice()) {
                $courseData['price'] = number_format($course->getPrice(), 2, '.', '');
            }
            $responseData[] = $courseData;
        }

        return $this->json($responseData);
    }

    #[OA\Get(
        path: "/api/v1/courses/{code}",
        operationId: "getCourse",
        description: "Получение информации о курсе",
        summary: "Получение детальной информации о курсе",
        tags: ["Course"],
        parameters: [
            new OA\Parameter(
                name: "code",
                description: "Код курса",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", example: "react-developer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Информация о курсе",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "code", type: "string", example: "react-developer"),
                        new OA\Property(property: "type", type: "number", example: 1),
                        new OA\Property(property: "price", type: "string", example: "10000.00", nullable: true)
                    ],
                    type: "object"
                )
            )
        ]
    )]
    #[Route('/{code}', name: 'api_v1_course_show', methods: ['GET'])]
    public function course(Course $course): Response
    {
        $responseData = [
            'code' => $course->getCode(),
            'type' => $course->getType(),
        ];

        if ($course->getType() !== Course::TYPE_FREE && $course->getPrice() !== null) {
            $responseData['price'] = number_format($course->getPrice(), 2, '.', '');
        }

        return $this->json($responseData);
    }

    #[OA\Post(
        path: "/api/v1/courses/{code}/pay",
        operationId: "payForCourse",
        description: "Оплата курса",
        summary: "Оплата выбранного курса",
        security: [["bearerAuth" => []]],
        tags: ["Course"],
        parameters: [
            new OA\Parameter(
                name: "code",
                description: "Код курса",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string", example: "react-developer")
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Успешная оплата курса",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "course_type", type: "number", example: 2),
                        new OA\Property(property: "expires_at", type: "string", example: '2025-04-10T13:55:17+00:00')
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 406,
                description: "Недостаточно средств",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "code", type: "integer", example: 406),
                        new OA\Property(property: "message", type: "string", example: "Недостаточно средств на счету")
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 401,
                description: "Unauthorized",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "code", type: "number", example: 401),
                        new OA\Property(property: "message", type: "string", example: "JWT Token not found"),
                    ]
                )
            ),
        ]
    )]
    #[Route('/{code}/pay', name: 'api_v1_course_pay', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function pay(
        Course $course,
        PaymentService $paymentService,
        #[CurrentUser] User $user
    ): Response {
        try {
            $result = $paymentService->payForCourse($user, $course);
            return $this->json($result);
        } catch (NotEnoughFundsException $e) {
            return $this->json([
                'code' => Response::HTTP_NOT_ACCEPTABLE,
                'message' => $e->getMessage()
            ], Response::HTTP_NOT_ACCEPTABLE);
        }
    }

    #[Route('/{code}/edit', name: 'app_course_edit', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function edit(Request $request, #[MapEntity(mapping: ['code' => 'code'])] Course $course, EntityManagerInterface $entityManager)
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (isset($data['name'])) {
                $course->setTitle($data['name']);
            }
            if (isset($data['price'])) {
                $course->setPrice($data['price']);
            }
            if (isset($data['type'])) {
                $course->setType($data['type']);
            }

            $entityManager->persist($course);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Курс обновлён',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Произошла ошибка при обновлении курса',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/new', name: 'app_course_new', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager)
    {
        try {
            $data = json_decode($request->getContent(), true);
            $course = new Course();
            if (isset($data['name'])) {
                $course->setTitle($data['name']);
            }
            if (isset($data['code'])) {
                $course->setCode($data['code']);
            }
            if (isset($data['price'])) {
                $course->setPrice($data['price']);
            }
            if (isset($data['type'])) {
                $course->setType($data['type']);
            }
            $entityManager->persist($course);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Курс создан',
            ]);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], JsonResponse::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Произошла ошибка при создании курса',
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
