<?php

namespace ATS\Bundle\ScheduleBundle\Command;

use Doctrine\Bundle\FixturesBundle\Command\LoadDataFixturesDoctrineCommand;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extends doctrine's fixtures command for integration into the
 * import driver system.
 * 
 * @author Austin Shinpaugh
 */
class ImportCommand extends LoadDataFixturesDoctrineCommand
{
    protected $importer;

    /**
     * @param string|null $name The name of the command; passing null means it must be set in configure()
     *
     * @throws LogicException When the command name is empty
     */
    /*public function __construct(ImportDriverHelper $driver) {
        parent::__construct('schedule:import');

        $this->importer = $driver;
    }*/

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('schedule:import')
            ->setDescription('Populate the database.')
            ->addOption(
                'source',
                's',
                InputOption::VALUE_OPTIONAL,
                "The data source used to update the data. Either 'ods' or 'book'.",
                'ods'
            )->addOption(
                'year',
                'y',
                InputOption::VALUE_OPTIONAL,
                'The starting year to import. IE: 2015',
                'all'
            )->setHelp('Import data from varying sources into the database.')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '4096M');

        $source = $input->getOption('source');
        $period = $input->getOption('year');
        
        $this->getContainer()->get('schedule.import_helper')
            ->setServiceId($source)
            ->setAcademicPeriod($period)
            ->toggleFKChecks(false)
        ;
        
        parent::execute($input, $output);
    }
}
