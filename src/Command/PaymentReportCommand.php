<?php

namespace App\Command;

use App\Entity\Course;
use App\Entity\Transaction;
use App\Repository\TransactionRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

#[AsCommand(
    name: 'payment:report',
    description: 'Generates report about paid courses for the month'
)]
class PaymentReportCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private string $reportEmail
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startDate = new \DateTime('01.04.2025');
        $endDate = new \DateTime('30.04.2025');

        $transactions = $this->transactionRepository->findPaymentTransactionsForPeriod(
            $startDate,
            $endDate,
            Transaction::OPERATION_TYPE_PAYMENT
        );

        $reportData = [];
        $totalAmount = 0;

        foreach ($transactions as $transaction) {
            $course = $transaction->getCourse();

            if (!$course) {
                continue;
            }

            $courseId = $course->getId();

            if (!isset($reportData[$courseId])) {
                $reportData[$courseId] = [
                    'code' => $course->getCode(),
                    'type' => $this->getCourseTypeName($course->getType()),
                    'count' => 0,
                    'amount' => 0,
                ];
            }

            $reportData[$courseId]['count']++;
            $reportData[$courseId]['amount'] += $transaction->getAmount();
            $totalAmount += $transaction->getAmount();
        }

        $html = $this->twig->render('email/payment_report.html.twig', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reportData' => $reportData,
            'totalAmount' => $totalAmount,
        ]);

        $email = (new Email())
            ->from('noreply@study-on.local')
            ->to($this->reportEmail)
            ->subject(sprintf(
                'Отчет об оплаченных курсах за период %s - %s',
                $startDate->format('d.m.Y'),
                $endDate->format('d.m.Y')
            ))
            ->html($html);

        $this->mailer->send($email);

        $output->writeln('Отчет успешно отправлен на email: ' . $this->reportEmail);

        return Command::SUCCESS;
    }

    private function getCourseTypeName(int $type): string
    {
        return match ($type) {
            Course::TYPE_FULL => 'Покупка',
            Course::TYPE_RENT => 'Аренда',
            Course::TYPE_FREE => 'Бесплатный',
            default => 'Неизвестный',
        };
    }
}
