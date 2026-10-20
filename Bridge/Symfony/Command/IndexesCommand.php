<?php

namespace Omnisearch\Bridge\Symfony\Command;

use Omnisearch\Mapping\FieldMetadata;
use Omnisearch\ORM\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'omnisearch:indexes', description: 'The indexes the classes declare: their class, connection, engine and fields')]
final class IndexesCommand extends Command
{
    public function __construct(private readonly Manager $manager)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];
        foreach ($this->manager->metadata()->all() as $metadata) {
            $connection = $metadata->connection ?? $this->manager->registry()->defaultName();
            $rows[] = [$metadata->index, $metadata->class, $connection, $this->manager->registry()->driverName($connection), implode(', ', array_map(fn (FieldMetadata $f) => $f->name.($f->facet ? '*' : ''), $metadata->fields))];
        }
        (new SymfonyStyle($input, $output))->table(['Index', 'Class', 'Connection', 'Driver', 'Fields (* facet)'], $rows);

        return Command::SUCCESS;
    }
}
