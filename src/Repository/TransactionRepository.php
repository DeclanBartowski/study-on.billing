<?php

namespace App\Repository;

use App\Entity\Transaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function getUserBalance(User $user): float
    {
        $deposits = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', Transaction::OPERATION_TYPE_DEPOSIT)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $payments = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)')
            ->where('t.user = :user')
            ->andWhere('t.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', Transaction::OPERATION_TYPE_PAYMENT)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return $deposits - $payments;
    }

    public function findByUserWithFilters(User $user, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if ($filters['type']) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if ($filters['course_code']) {
            $qb->join('t.course', 'c')
                ->andWhere('c.code = :course_code')
                ->setParameter('course_code', $filters['course_code']);
        }

        if ($filters['skip_expired']) {
            $qb->andWhere('t.expiresAt IS NULL OR t.expiresAt > :now')
                ->setParameter('now', new \DateTime());
        }

        return $qb->getQuery()->getResult();
    }

    public function findRentalTransactionsEndingSoonForUser(User $user): array
    {
        $now = new \DateTime();
        $tomorrow = (new \DateTime())->modify('+1 day');

        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->andWhere('t.type = :type')
            ->andWhere('t.expiresAt BETWEEN :now AND :tomorrow')
            ->andWhere('t.course IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('type', Transaction::OPERATION_TYPE_PAYMENT)
            ->setParameter('now', $now)
            ->setParameter('tomorrow', $tomorrow)
            ->getQuery()
            ->getResult();
    }

    public function findPaymentTransactionsForPeriod(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $operationType
    ): array {
        return $this->createQueryBuilder('t')
            ->andWhere('t.createdAt BETWEEN :start AND :end')
            ->andWhere('t.type = :type')
            ->andWhere('t.course IS NOT NULL')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->setParameter('type', $operationType)
            ->join('t.course', 'c')
            ->addSelect('c')
            ->getQuery()
            ->getResult();
    }
}
