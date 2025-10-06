<?php

namespace App\Command;

use App\Entity\EmployeeCountReport;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:actualizar-conteo-empleados',
    description: 'Genera un reporte con la cantidad de empleados activos por empresa y lo guarda en la base de datos.'
)]
class ActualizarConteoEmpleadosCommand extends Command
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->entityManager->getConnection();
        $sql = "
            SELECT a.name AS account_name, c.name AS company_name, COUNT(u.id) AS employee_count 
            FROM user u
            JOIN companies c ON c.id = u.company_id
            JOIN accounts a ON a.id = c.accounts_id
            WHERE u.is_active = 1
            GROUP BY a.name, c.name;
        ";

        $result = $conn->fetchAllAssociative($sql);

        if (empty($result)) {
            $output->writeln('<info>No se encontraron registros.</info>');
            return Command::SUCCESS;
        }

        foreach ($result as $row) {
            // Verificar que las claves existen antes de usarlas
            if (!isset($row['account_name'], $row['company_name'], $row['employee_count'])) {
                $output->writeln('<error>Error: Faltan datos en la consulta SQL.</error>');
                continue;
            }
            
            // Crear una nueva instancia de EmployeeCountReport pasando los parámetros necesarios
            $report = new EmployeeCountReport(
                $row['account_name'], 
                $row['company_name'], 
                $row['employee_count']
            );
        
            // Persistir la nueva entidad
            $this->entityManager->persist($report);
        }
        
        $this->entityManager->flush();
        $output->writeln('<info>Reporte actualizado correctamente.</info>');        

        return Command::SUCCESS;
    }
}
