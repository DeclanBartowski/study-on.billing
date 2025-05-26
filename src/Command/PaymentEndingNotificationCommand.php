<?php

namespace App\Command;

use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'payment:ending:notification',
    description: 'Sends notifications about courses that are ending soon',
)]
class PaymentEndingNotificationCommand extends Command
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private UserRepository $userRepository,
        private MailerInterface $mailer
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->userRepository->findUsersWithRentalsEndingSoon();

        foreach ($users as $user) {
            $transactions = $this->transactionRepository->findRentalTransactionsEndingSoonForUser($user);

            if (count($transactions) > 0) {
                $this->sendNotificationEmail($user, $transactions);
                $output->writeln(sprintf('Sent notification to %s', $user->getEmail()));
            }
        }

        $output->writeln('All notifications sent successfully!');
        return Command::SUCCESS;
    }

    private function sendNotificationEmail($user, array $transactions): void
    {
        $emailContent = "Уважаемый клиент! У вас есть курсы, срок аренды которых подходит к концу:\n\n";

        foreach ($transactions as $transaction) {
            $course = $transaction->getCourse();
            $emailContent .= sprintf(
                "%s действует до %s\n",
                $course->getTitle(),
                $transaction->getExpiresAt()->format('d.m.Y H:i')
            );
        }

        $email = (new Email())
            ->from('noreply@yourdomain.com')
            ->to($user->getEmail())
            ->subject('Уведомление об окончании срока аренды курсов')
            ->text($emailContent);

        $this->mailer->send($email);
    }
}
