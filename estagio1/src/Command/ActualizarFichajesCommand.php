<?php
// src/Command/ActualizarFichajesCommand.php
namespace App\Command;

use App\Enum\TimeRegisterStatus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-fichajes',
    description: 'Actualiza el campo status según hour_start y hour_end (SQL directo)'
)]
class ActualizarFichajesCommand extends Command
{
    public function __construct(private readonly Connection $conn)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // 1. OPEN = hour_start = hour_end
        $affectedOpen = $this->conn->executeStatement(
            'UPDATE times_register SET status = :open WHERE hour_start = hour_end',
            ['open' => TimeRegisterStatus::OPEN->value]
        );

        // 2. CLOSED = hour_start <> hour_end
        $affectedClosed = $this->conn->executeStatement(
            'UPDATE times_register SET status = :closed WHERE hour_start <> hour_end',
            ['closed' => TimeRegisterStatus::CLOSED->value]
        );

        $output->writeln(sprintf(
            '<info>%d abiertos, %d cerrados actualizados.</info>',
            $affectedOpen,
            $affectedClosed
        ));

        return Command::SUCCESS;
    }
}
