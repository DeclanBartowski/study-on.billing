<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Attributes as OA;

#[Route('/api/v1/transactions')]
class TransactionController extends AbstractController
{
    #[OA\Get(
        path: "/api/v1/transactions",
        operationId: "getTransactions",
        description: "Получение истории транзакций пользователя",
        summary: "Получение отфильтрованной истории транзакций",
        security: [["bearerAuth" => []]],
        tags: ["Transaction"],
        parameters: [
            new OA\Parameter(
                name: "filter[type]",
                description: "Тип транзакции (1|2)",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "1")
            ),
            new OA\Parameter(
                name: "filter[course_code]",
                description: "Код курса",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "string", example: "react-developer")
            ),
            new OA\Parameter(
                name: "filter[skip_expired]",
                description: "Пропускать истекшие аренды",
                in: "query",
                required: false,
                schema: new OA\Schema(type: "boolean", example: true)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Список транзакций",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: "id", type: "integer", example: 1),
                            new OA\Property(property: "created_at", type: "string", format: "date-time", example: '2025-04-10T13:55:17+00:00'),
                            new OA\Property(property: "type", type: "number", example: "2"),
                            new OA\Property(property: "amount", type: "string", example: "10000.00"),
                            new OA\Property(property: "course_code", type: "string", example: "react-developer", nullable: true)
                        ],
                        type: "object"
                    )
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
    #[Route('', name: 'api_v1_transactions')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, TransactionRepository $transactionRepository, #[CurrentUser] User $user): Response
    {
        $filters = [
            'type' => $request->query->get('filter[type]'),
            'course_code' => $request->query->get('filter[course_code]'),
            'skip_expired' => (bool)$request->query->get('filter[skip_expired]', false),
        ];
        $transactions = $transactionRepository->findByUserWithFilters($user, $filters);

        $response = array_map(function ($transaction) {
            $data = [
                'id' => $transaction->getId(),
                'created_at' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'type' => $transaction->getType(),
                'amount' => number_format($transaction->getAmount(), 2, '.', ''),
            ];

            if ($transaction->getCourse() !== null) {
                $data['course_code'] = $transaction->getCourse()->getCode();
            }

            return $data;
        }, $transactions);

        return $this->json($response);
    }
}
