<?php

namespace ATS\Bundle\ScheduleBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Parses The Book csv dump and imports that data into the database.
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
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
            ->setName('scheduler:parse-book')
            ->setDescription('Parses the CSV book file and loads its contents into the databse.')
            ->addArgument('path', InputArgument::OPTIONAL, 'Path to the CSV file.', 'ClassesF17.csv')
        ;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->getContainer()->get('ats_schedule.book_parser')
            ->setPath($input->getArgument('path'))
            ->doParse($output)
        ;
    }
}