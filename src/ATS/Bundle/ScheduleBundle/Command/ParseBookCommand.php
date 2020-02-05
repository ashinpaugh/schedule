<?php

namespace ATS\Bundle\ScheduleBundle\Command;

use ATS\Bundle\ScheduleBundle\Util\Parser\BookParser;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parses The Book csv dump and imports that data into the database.
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 * @deprecated Use the import command.
 */
class ParseBookCommand extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        
        $this
            ->setName('schedule:parse-book')
            ->setDescription("Parses the CSV book file and loads its contents into the databse. Deprecated: use the <info>schedule:import</info> command.")
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the CSV file.', BookParser::CSV_PATH)
            ->addOption('include_online', 'io', InputOption::VALUE_OPTIONAL, 'Flag to include online courses.', false)
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        ini_set('memory_limit', '4096M');

        $this->getContainer()->get('ats_schedule.book_parser')
            ->setPath($input->getArgument('path'))
            ->setIncludeOnline($input->getOption('include_online'))
            ->doParse($output)
        ;
    }
}
