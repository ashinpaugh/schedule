<?php

namespace ATS\Bundle\ScheduleBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Command that should be run after setting the environmental vars
 * in parameters.yml.
 * 
 * @author Austin Shinpaugh
 */
class SetupCommand extends AbstractCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('schedule:setup')
            ->setDescription('Initialize the app settings.')
            ->addOption('import', 'i', InputOption::VALUE_NONE, 'Runs the <info>schedule:import</info> command with the default settings.')
        ;
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this
            ->setupDatabase($output)
            ->createSessionsTable($output)
            ->createTableSchema($output)
            ->prepareAssets($output)
            ->generateOptimizedAutoloader($output)
        ;
        
        $output->writeln("\nSetup complete.");
        
        if (!$input->getOption('import')) {
            $output->writeln("Next run <info>php bin/console doctrine:fixtures:load</info> command to populate the database.");
            return;
        }
        
        $this->doImport($output);
    }
    
    /**
     * Passing the env option to the sub-command is ignored. The output says prod, but
     * builds the assets in the same environment that the :setup command was
     * run in. For this reason we use the process component.
     * 
     * @param OutputInterface $output
     *
     * @return $this
     */
    private function prepareAssets(OutputInterface $output)
    {
        $output->writeln('Preparing assets...');
        
        $process = new Process('php bin/console assetic:dump --env=prod --no-debug --force');
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('Failed!');
            throw new ProcessFailedException($process);
        }
        
        $root   = $this->getContainer()->getParameter('kernel.root_dir');
        $finder = Finder::create()
            ->files()
            ->in($root . '/../web/assets/compiled')
            ->name('controllers.js')
            ->name('utils.js')
            ->name('libraries.js')
            ->name('libraries.css')
            ->name('app.css')
        ;
        
        if (5 === $finder->count()) {
            $output->writeln("Assets created successfully.\n");
            return $this;
        }
        
        $output->writeln("Production files could not be created.");
        $output->writeln("Try running 'php bin/console assetic:dump --env=prod' for further information.");
        die();
    }
    
    /**
     * Create the project's database.
     * 
     * @param OutputInterface $output
     *
     * @return $this
     */
    private function setupDatabase(OutputInterface $output)
    {
        $output->writeln('Creating the database...');
        
        $command = $this->getApplication()->find('doctrine:database:create');
        $args    = new ArrayInput([
            'command'         => 'doctrine:database:create',
            '--quiet'         => true,
            '--no-debug'      => true,
            '--if-not-exists' => true,
        ]);
        
        $command->run($args, new NullOutput());
        
        return $this;
    }
    
    /**
     * Create the sessions table.
     *
     * @param OutputInterface $output
     *
     * @return $this
     */
    private function createSessionsTable(OutputInterface $output)
    {
        /* @var Connection $connection */
        $doctrine   = $this->getContainer()->get('doctrine');
        $connection = $doctrine->getConnection();
        $statement  = $connection->prepare('
            CREATE TABLE `sessions` (
                `sess_id` VARCHAR(128) NOT NULL PRIMARY KEY,
                `sess_data` MEDIUMBLOB NOT NULL,
                `sess_time` INTEGER UNSIGNED NOT NULL,
                `sess_lifetime` MEDIUMINT NOT NULL
            ) COLLATE utf8_bin, ENGINE = InnoDB;
        ');
        
        $statement->execute();
        
        $output->writeln('Sessions table created.');
        
        return $this;
    }
    
    /**
     * Create the table schema.
     * 
     * @param OutputInterface $output
     *
     * @return $this
     */
    private function createTableSchema(OutputInterface $output)
    {
        $command = $this->getApplication()->find('doctrine:schema:create');
        $args    = new ArrayInput([
            'command'    => 'doctrine:schema:create',
            '--quiet'    => true,
            '--no-debug' => true,
        ]);
        
        $command->run($args, new NullOutput());
        
        $output->writeln("Entity table schema created.\n");
        
        return $this;
    }
    
    /**
     * Optimize the composer auto loader.
     * 
     * @param OutputInterface $output
     *
     * @return $this
     */
    private function generateOptimizedAutoloader(OutputInterface $output)
    {
        $output->writeln('Generating optimized autoloader...');
        
        $process = new Process('composer dump-autoload -o');
        $process->run();
        
        if (!$process->isSuccessful()) {
            $output->writeln('Failed!');
            throw new ProcessFailedException($process);
        }
        
        $output->writeln('Autoloader optimized.');
        
        return $this;
    }
    
    /**
     * Runs the schedule:import command.
     * The command will timeout after three hours.
     * 
     * @param OutputInterface $output
     * 
     * @return $this
     */
    private function doImport(OutputInterface $output)
    {
        $output->writeln("\nRunning import...");
        
        $options = ['--no-debug', '--purge-with-truncate', '--no-interaction'];
        $process = new Process(
            'php bin/console schedule:import ' . implode(' ', $options),
            null,
            null,
            null,
            (3600 * 3)
        );
        
        $reset = false;
        $process->run(function ($type, $buffer) use ($output, &$reset) {
            if (1 === strlen($buffer)) {
                return;
            }
            
            if (false === strpos($buffer, '%')) {
                $output->write($buffer);
                $reset = true;
            } else {
                $this->printStreamResponse($buffer, $reset ? 0 : null);
                $reset = false;
            }
        });
        
        return $this;
    }
    
    /**
     * Replace the cli's last message with a new one.
     * 
     * @param string $message
     * @param null   $force_clear_lines
     * 
     * @url https://stackoverflow.com/questions/4320081/clear-php-cli-output
     */
    private function printStreamResponse($message, $force_clear_lines = null)
    {
        static $last_lines = 0;
    
        if (!is_null($force_clear_lines)) {
            $last_lines = $force_clear_lines;
        }
        
        $term_width = exec('tput cols', $toss, $status);
        if ($status) {
            $term_width = 64; // Arbitrary fall-back term width.
        }
        
        $line_count = 0;
        foreach (explode("\n", $message) as $line) {
            $line_count += count(str_split($line, $term_width));
        }
        
        // Erasure MAGIC: Clear as many lines as the last output had.
        for ($i = 0; $i < $last_lines; $i++) {
            // Can be consolodated into
            echo "\r\033[K\033[1A\r\033[K\r";
        }
        
        $last_lines = $line_count;
        
        echo $message."\n";
    }
}