<?php

namespace App\Tests\Command;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PaymentEndingNotificationCommandTest extends KernelTestCase
{
    private $entityManager;
    private $mailer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->mailer = $this->createMock(MailerInterface::class);
    }

    public function testExecute(): void
    {
        $course = new Course();
        $course->setCode('course-test');
        $course->setTitle('Test Course');
        $course->setType(Course::TYPE_RENT);
        $course->setPrice(50);
        $this->entityManager->persist($course);

        $user = new User();
        $user->setEmail('user@mail.com');
        $user->setPassword('password');
        $this->entityManager->persist($user);

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setCourse($course);
        $transaction->setType(Transaction::OPERATION_TYPE_PAYMENT);
        $transaction->setAmount(50);
        $transaction->setCreatedAt(new \DateTime('-6 days'));
        $transaction->setExpiresAt(new \DateTime('+1 day'));
        $this->entityManager->persist($transaction);

        $this->entityManager->flush();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findUsersWithRentalsEndingSoon')
            ->willReturn([$user]);

        $transactionRepository = $this->createMock(TransactionRepository::class);
        $transactionRepository->method('findRentalTransactionsEndingSoonForUser')
            ->willReturn([$transaction]);

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return strpos($email->getSubject(), 'Уведомление об окончании срока аренды курсов') !== false;
            }));

        $command = new \App\Command\PaymentEndingNotificationCommand(
            $transactionRepository,
            $userRepository,
            $this->mailer
        );

        $result = $command->run(
            $this->createMock(\Symfony\Component\Console\Input\InputInterface::class),
            $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class)
        );

        $this->assertEquals(0, $result);
    }
}
