<?php

namespace App\Controller;

use App\DTO\RegistrationDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;

class AuthController extends AbstractController
{

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private JWTTokenManagerInterface $jwtManager;
    private ValidatorInterface $validator;
    private UserRepository $userRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        JWTTokenManagerInterface $jwtManager,
        ValidatorInterface $validator,
        UserRepository $userRepository,
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
        $this->jwtManager = $jwtManager;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
    }

    #[OA\Post(
        operationId: "auth",
        description: "Авторизация пользователя по e-mail и паролю",
        summary: "Авторизация пользователя",
        requestBody: new OA\RequestBody(
            description: "Данные пользователя для авторизации",
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "username", type: "string", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", example: "1234567890"),
                ],
                type: "object"
            )
        ),
        tags: ["User"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Успешная авторизация пользователя",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "token", type: "string",
                            example: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
                        new OA\Property(property: "refresh_token", type: "string",
                            example: "fasdfasdf97asd65f6a7s4dfa7s8d6f4a87d..."),
                        new OA\Property(property: "refresh_token_expires", type: "number",
                            example: "1745481039"),
                        new OA\Property(property: "user", properties: [
                            new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string",
                                example: "ROLE_USER"))
                        ]),
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 400,
                description: "Ошибка при авторизации",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "errors", type: "string",
                            example: 'The key "username" must be provided.'),
                        new OA\Property(property: "code", type: "integer", example: 400),
                    ],
                    type: "object"
                )
            ),
        ],

    )]
    #[Route('/api/v1/auth', name: 'api_auth', methods: ['POST'])]
    public function auth(Request $request, RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager)
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username'], $data['password'])) {
            throw new AuthenticationException('Email and password are required');
        }

        $email = $data['username'];
        $password = $data['password'];

        $user = $this->userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            throw new AuthenticationException('User not found');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new AuthenticationException('Invalid credentials');
        }

        $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, 2592000);
        $refreshTokenManager->save($refreshToken);
        
        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'refresh_token_expires' => $refreshToken->getValid()->getTimestamp(),
            'user' => [
                'roles' => $user->getRoles(),
            ],
        ]);
    }

    #[OA\Post(
        operationId: "register",
        description: "Регистрация пользователя по e-mail и паролю",
        summary: "Регистрация нового пользователя",
        requestBody: new OA\RequestBody(
            description: "Данные пользователя для регистрации",
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: "email", type: "string", example: "user@example.com"),
                    new OA\Property(property: "password", type: "string", example: "1234567890"),
                ],
                type: "object"
            )
        ),
        tags: ["User"],
        responses: [
            new OA\Response(
                response: 201,
                description: "Успешная регистрация пользователя",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "token", type: "string",
                            example: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."),
                        new OA\Property(property: "refresh_token", type: "string",
                            example: "fasdfasdf97asd65f6a7s4dfa7s8d6f4a87d..."),
                        new OA\Property(property: "refresh_token_expires", type: "number",
                            example: "1745481039"),
                        new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string",
                            example: "ROLE_USER")),
                    ],
                    type: "object"
                )
            ),
            new OA\Response(
                response: 400,
                description: "Ошибка при регистрации",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "errors", type: "array", items: new OA\Items(type: "string",
                            example: "Email is required.")),
                    ],
                    type: "object"
                )
            ),
        ],
    )]
    #[Route('/api/v1/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request, RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager, PaymentService $paymentService): JsonResponse
    {
        try {
            $serializer = SerializerBuilder::create()->build();
            $registrationDTO = $serializer->deserialize(
                $request->getContent(),
                RegistrationDTO::class,
                'json'
            );

            $errors = $this->validator->validate($registrationDTO);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return new JsonResponse(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $user = new User();
            $user->setEmail($registrationDTO->email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $registrationDTO->password));
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->wrapInTransaction(function () use ($user, $paymentService) {
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $paymentService->deposit($user, $paymentService->getInitialBalance());
            });

            $token = $this->jwtManager->create($user);
            $refreshToken = $refreshTokenGenerator->createForUserWithTtl($user, 2592000);
            $refreshTokenManager->save($refreshToken);

            return new JsonResponse([
                'token' => $token,
                'refresh_token' => $refreshToken->getRefreshToken(),
                'refresh_token_expires' => $refreshToken->getValid()->getTimestamp(),
                'roles' => $user->getRoles(),
            ], Response::HTTP_CREATED);
        } catch (\Exception $exception) {
            return new JsonResponse(['errors' => [$exception->getMessage()]], Response::HTTP_BAD_REQUEST);
        }
    }


    #[OA\Get(
        operationId: "getBalance",
        description: "Получение e-mail, баланса и ролей авторизованного пользователя",
        summary: "Получение данных авторизованного пользователя",
        tags: ["User"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Successful operation",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "username", type: "string", example: "john_doe"),
                        new OA\Property(property: "roles", type: "array", items: new OA\Items(type: "string",
                            example: "ROLE_USER")),
                        new OA\Property(property: "balance", type: "number", format: "float", example: 100.50),
                    ]
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
    #[Security(name: 'bearerAuth')]
    #[Route('/api/v1/users/current', name: 'api_get_user_balance', methods: ['GET'])]
    public function getBalance(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof UserInterface) {
            return $this->json([
                'error' => 'User not authenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
            'balance' => $user->getBalance(),
        ], Response::HTTP_OK);
    }
}
