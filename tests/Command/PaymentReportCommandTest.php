<?php

namespace App\Tests\Command;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class PaymentReportCommandTest extends KernelTestCase
{
    private $entityManager;
    private $mailer;
    private $twig;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine')->getManager();
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = self::getContainer()->get(Environment::class);
    }

    public function testExecute(): void
    {
        // Создаем тестовые данные
        $course = new Course();
        $course->setCode('test-course');
        $course->setTitle('Test Course');
        $course->setType(Course::TYPE_FULL);
        $course->setPrice(100);
        $this->entityManager->persist($course);

        $user = new User();
        $user->setEmail('user@example.com');
        $user->setPassword('password');
        $this->entityManager->persist($user);

        $transaction = new Transaction();
        $transaction->setUser($user);
        $transaction->setCourse($course);
        $transaction->setType(Transaction::OPERATION_TYPE_PAYMENT);
        $transaction->setAmount(100);
        $transaction->setCreatedAt(new \DateTime('2025-04-15'));
        $this->entityManager->persist($transaction);

        $this->entityManager->flush();

        // Мокируем отправку email
        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getSubject() === 'Отчет об оплаченных курсах за период 01.04.2025 - 30.04.2025';
            }));

        // Получаем репозиторий
        $transactionRepository = $this->entityManager->getRepository(Transaction::class);

        // Создаем и запускаем команду
        $command = new \App\Command\PaymentReportCommand(
            $transactionRepository,
            $this->mailer,
            $this->twig,
            'report@example.com'
        );

        $result = $command->run(
            $this->createMock(\Symfony\Component\Console\Input\InputInterface::class),
            $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class)
        );

        $this->assertEquals(0, $result);
    }
}
