<?php

namespace Omnisearch\Bridge\Symfony\Command;

use Omnisearch\ORM\Manager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'omnisearch:schema', description: 'The schema each index is created with, in its engine\'s terms (JSON)')]
final class SchemaCommand extends Command
{
    public function __construct(private readonly Manager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('index', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Indexes (all when none)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schemas = [];
        foreach ($input->getArgument('index') ?: array_keys($this->manager->metadata()->all()) as $index) {
            $metadata = $this->manager->classMetadata($index);
            $schemas[$index] = $this->manager->connection($metadata)->schema($metadata);
        }
        $output->writeln((string) json_encode($schemas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return Command::SUCCESS;
    }
}
