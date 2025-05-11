<?php

namespace App\Service;

use App\Entity\{Course, Transaction, User};
use App\Exception\NotEnoughFundsException;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class PaymentService
{
    public function __construct(
        private EntityManagerInterface $em,
        private TransactionRepository $transactionRepository,
        private float $initialBalance
    ) {
    }

    public function getInitialBalance(): float
    {
        return $this->initialBalance;
    }

    public function deposit(User $user, float $amount): void
    {
        $this->em->wrapInTransaction(function () use ($user, $amount) {
            $transaction = new Transaction();
            $transaction->setUser($user)
                ->setType(Transaction::OPERATION_TYPE_DEPOSIT)
                ->setAmount($amount)
                ->setCreatedAt(new \DateTime());

            $this->em->persist($transaction);

            $user->setBalance($user->getBalance() + $amount);
            $this->em->persist($user);
            $this->em->flush();
        });
    }

    public function payForCourse(User $user, Course $course): array
    {
        if ($course->getType() === Course::TYPE_FREE) {
            return ['success' => true, 'course_type' => 'free'];
        }

        $balance = $this->calculateBalance($user);

        if ($course->getPrice() > $balance) {
            throw new NotEnoughFundsException();
        }
        $transaction = new Transaction();
        $response = $this->em->wrapInTransaction(function () use ($user, $course, $transaction) {
            $transaction->setUser($user)
                ->setCourse($course)
                ->setType(Transaction::OPERATION_TYPE_PAYMENT)
                ->setAmount($course->getPrice())
                ->setCreatedAt(new \DateTime());

            if ($course->getType() === Course::TYPE_RENT) {
                $expiresAt = (new \DateTime())->modify('+' . Course::RENT_TYPE_TIME);
                $transaction->setExpiresAt($expiresAt);
            }

            $this->em->persist($transaction);

            $user->setBalance($user->getBalance() - $course->getPrice());
            $this->em->persist($user);
            $this->em->flush();

            return [
                'success' => true,
                'course_type' => $course->getType(),
                'expires_at' => $course->getType() === Course::TYPE_RENT
                    ? $transaction->getExpiresAt()->format(\DateTimeInterface::ATOM)
                    : null
            ];
        });

        return $response['success'] ? $response : [
            'success' => false,
            'message' => 'Произошла ошибка при покупке курса'
        ];
    }

    private function calculateBalance(User $user): float
    {
        return $this->transactionRepository->getUserBalance($user);
    }
}
