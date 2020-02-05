<?php

namespace ATS\Bundle\ScheduleBundle\Util\Parser;

use ATS\Bundle\ScheduleBundle\Entity\AbstractEntity;
use ATS\Bundle\ScheduleBundle\Entity\Building;
use ATS\Bundle\ScheduleBundle\Entity\Campus;
use ATS\Bundle\ScheduleBundle\Entity\Course;
use ATS\Bundle\ScheduleBundle\Entity\Section;
use ATS\Bundle\ScheduleBundle\Entity\Instructor;
use ATS\Bundle\ScheduleBundle\Entity\Room;
use ATS\Bundle\ScheduleBundle\Entity\Subject;
use ATS\Bundle\ScheduleBundle\Entity\Term;
use ATS\Bundle\ScheduleBundle\Entity\TermBlock;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\Common\Persistence\ObjectRepository;
use ForceUTF8\Encoding;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Parses the Book CSV file.
 * 
 * @deprecated The driver import system replaced this.
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class BookParser
{
    const CSV_PATH = 'datastores/Classes.csv';
    
    /**
     * The project's root directory.
     * 
     * @var string
     */
    protected $root_dir;
    
    /**
     * @var Registry 
     */
    protected $doctrine;
    
    /**
     * @var string
     */
    protected $path;
    
    /**
     * @var bool
     */
    protected $include_online;
    
    /**
     * @var OutputInterface
     */
    protected $output;
    
    /**
     * BookParser constructor.
     *
     * @param Registry $doctrine
     */
    public function __construct(Registry $doctrine)
    {
        $this->doctrine = $doctrine;
        
        $this->setIncludeOnline(false);
    }
    
    /**
     * Start the parsing.
     * 
     * @param OutputInterface|null $output
     */
    public function doParse(OutputInterface $output = null)
    {
        $this->output = $output;
        
        $this
            ->setEnvironmentVars()
            ->run()
        ;
    }
    
    /**
     * Set environment variables.
     * 
     * @return $this
     */
    protected function setEnvironmentVars()
    {
        // Make sure that OSX line endings are accounted for when parsing the CSV.
        ini_set('auto_detect_line_endings',true);
        
        return $this;
    }
    
    /**
     * Perform the import.
     */
    protected function run()
    {
        $this->disableDoctrineLogging();
        
        $handle   = $this->openFile();
        $progress = new ProgressBar($this->output, count(file($this->path)));
        $progress->setFormat('debug');
        $progress->start();
        
        $i      = 1;
        $chunks = 100;
        
        while($data = fgetcsv($handle)) {
            $this->parseline($data);
            
            if ($i % $chunks == 0) {
                $this->getManager()->flush();
                
                $progress->advance($chunks);
            }
            
            $i++;
        }
        
        $this->getManager()->flush();
        $progress->finish();
        
        fclose($handle);
        
        // The Progress Bar doesn't clear the line upon completion.
        if ($this->output instanceof OutputInterface) {
            $this->output->writeln('');
        }
    }
    
    /**
     * Determine if we should parse a row.
     * 
     * @param array $data
     *
     * @return bool
     */
    public function isValidEntry(array $data)
    {
        // 0 = semester - invalid entry. 8 = status.
        if ('...' === $data[0] || '...' === $data[8]) {
            return false;
        }
        
        if (!$this->include_online && $this->isOnline($data)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Parse a line from The Book.
     * 
     * @param array $data
     *
     * @return null|Section
     */
    protected function parseLine(array $data)
    {
        if (!$this->isValidEntry($data)) {
            return null;
        }
        
        $subject = $this->getSubject($data);
        if (!$course = $this->getCourse($subject, $data)) {
            return null;
        }
        
        $room       = null;
        $block      = null;
        $term       = $this->getTerm($data, $block);
        $instructor = $this->getInstructor($data);
        
        $location = $this->parseBuilding($data);
        $campus   = $this->getCampus($data);
        
        if ($building = $this->getBuilding($campus, $location)) {
            $room = $this->getRoom($building, $location);
        }
        
        return $this->parseClass($data, $block, $subject, $course, $instructor, $campus, $room);
    }
    
    /**
     * Find the subject.
     * 
     * @param array $data
     *
     * @return AbstractEntity|mixed|null
     */
    protected function getSubject(array $data)
    {
        static $instances;
        
        $subject = $data[1];
        $key     = $this->getKey(['name' => $subject]);
        $repo    = $this->getManager()->getRepository(Subject::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        return $this->persist($instances, $key, new Subject($subject));
    }
    
    /**
     * Get the Campus.
     * 
     * @param array $data
     *
     * @return AbstractEntity|Campus
     */
    protected function getCampus(array $data)
    {
        static $instances;
        
        $value = $data[9];
        $key   = $this->getKey(['name' => $value]);
        $repo  = $this->getManager()->getRepository(Campus::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        return $this->persist($instances, $key, new Campus($value));
    }
    
    /**
     * Get the building.
     * 
     * @param Campus $campus
     * @param array  $location
     *
     * @return AbstractEntity|Building
     */
    protected function getBuilding(Campus $campus, array $location)
    {
        static $instances;
        
        $key  = $this->getKey(['name' => $location['building'], 'campus' => $campus->getName()]);
        $repo = $this->getManager()->getRepository(Building::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        $object = new Building($campus, $location['building']);
        $campus->addBuilding($object);
        
        return $this->persist($instances, $key, $object);
    }
    
    /**
     * Get the room.
     * 
     * @param Building $building
     * @param array    $location
     *
     * @return AbstractEntity|Room
     */
    protected function getRoom(Building $building, array $location)
    {
        static $instances;
        
        $name = $location['room'] ?: '0000';
        $key  = $this->getKey(['number' => $name, 'building' => $building->getName()]);
        $repo = $this->getManager()->getRepository(Room::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        $object = new Room($building, $name);
        $building->addRoom($object);
        
        return $this->persist($instances, $key, $object);
    }
    
    /**
     * Get the instructor.
     * 
     * @param array $data
     *
     * @return AbstractEntity|Instructor|object
     */
    protected function getInstructor(array $data)
    {
        static $instances;
        
        $id   = (int) $data[7];
        $name = $data[7] ? $data[6] : 'N/A';
        $key  = $this->getKey(['id' => $id, 'name' => $name]);
        $repo = $this->getManager()->getRepository(Instructor::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        return $this->persist($instances, $key, new Instructor($id, $name));
    }
    
    /**
     * Get the term.
     * 
     * @param array          $data
     * @param TermBlock|null $block
     *
     * @return AbstractEntity|Term
     */
    protected function getTerm(array $data, TermBlock &$block = null)
    {
        static $instances;
        
        $term = $this->parseTerm($data);
        $key  = $this->getKey(['year' => $term['year'], 'semester' => $term['semester']]);
        $repo = $this->getManager()->getRepository(Term::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            $block = $this->validateTermBlock($object, $term['block']);
            return $object;
        }
        
        $object = new Term($data[0], $term['year'], $term['semester']);
        $block  = $this->validateTermBlock($object, $term['block']);
        
        return $this->persist($instances, $key, $object);
    }
    
    /**
     * Get the course.
     * 
     * @param array $data
     *
     * @return AbstractEntity|Course|null
     */
    protected function getCourse(Subject $subject, array $data)
    {
        static $instances;
        
        //$subject = $data[1];
        $number  = $data[2];
        $key     = $this->getKey(['subject' => $subject->getName(), 'number' => $number]);
        $repo    = $this->getManager()->getRepository(Course::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        $object = new Course($subject, $number);
        $object
            ->setName(Encoding::toUTF8($data[5]))
            ->setLevel($data[36])
        ;
        
        $subject->addCourse($object);
        
        return $this->persist($instances, $key, $object);
    }
    
    /**
     * Import a class.
     *
     * @param array      $data
     * @param TermBlock  $block
     * @param Subject    $subject
     * @param Course     $course
     * @param Instructor $instructor
     * @param Campus     $campus
     * @param Room       $room
     *
     * @return AbstractEntity|Section|object
     */
    protected function parseClass(array $data, TermBlock $block, Subject $subject, Course $course, Instructor $instructor, Campus $campus, Room $room)
    {
        static $instances;
        
        $key   = $this->getKey(['crn' => $data[4], 'semester' => $block->getId()]);
        $repo  = $this->getManager()->getRepository(Section::class);
        $event = $this->getStored($instances, $key, $repo) ?: new Section();
        
        $event
            ->setCrn($data[4])
            ->setDays($data[20])
            ->setStartDate($this->getDate($data[16]))
            ->setEndDate($this->getDate($data[17]))
            ->setStartTime($data[21])
            ->setEndTime($data[22])
            ->setStatus($data[8])
            ->setNumber($data[3])
            ->setCampus($campus)
            ->setCourse($course)
            ->setBlock($block)
            ->setInstructor($instructor)
            ->setRoom($room)
            ->setSubject($subject)
            ->setNumEnrolled($data[12])
            ->setMaximumEnrollment($data[11])
            ->setMeetingType('class')
        ;
        
        return $this->persist($instances, $key, $event);
    }
    
    /**
     * Determine if the class offered is an online class.
     * 
     * @param array $data
     *
     * @return bool
     */
    protected function isOnline(array $data)
    {
        // 20 = Days.
        return null === $data[20]
            && $this->getDate($data[16]) <= new \DateTime()
        ;
    }
    
    /**
     * Parse special cases of the building codes.
     * 
     * @param array $data
     *
     * @return array
     */
    public function parseBuilding(array $data)
    {
        if ('XCH' !== substr($data[18], 0, 3)) {
            return [
                'building' => $data[18],
                'room'     => $data[19],
            ];
        }
        
        return [
            'building' => 'XCH',
            'room'     => substr($data[18], 3),
        ];
        
    }
    
    /**
     * Get the Term Block.
     *
     * @param AbstractEntity|Term $term
     * @param string              $block
     *
     * @return TermBlock|null|object
     */
    protected function validateTermBlock(AbstractEntity $term, $block)
    {
        static $instances;
        
        $key  = $this->getKey(['term' => $term->getName(), 'name' => $block]);
        $repo = $this->getManager()->getRepository(TermBlock::class);
        
        if ($object = $this->getStored($instances, $key, $repo)) {
            return $object;
        }
        
        if (!$term->getId()) {
            return ($instances[$key] = $this->createBlock($term, $block));
        }
        
        $instances[$key] = ($object = $this->createBlock($term, $block));
        
        return $object;
    }
    
    /**
     * Create a term block.
     *
     * Term Block needs an ID for the cache key to work, so block parsing
     * needs to happen before class parsing.
     * 
     * @param Term   $term
     * @param string $block
     *
     * @return TermBlock
     */
    public function createBlock(Term $term, $block)
    {
        $object = new TermBlock($term, $block);
        $term->addBlock($object);
        
        $this->getManager()->persist($object);
        $this->getManager()->flush();
        
        return $object;
    }
    
    /**
     * Break the terms into parts.
     * 
     * @param array $data
     *
     * @return array
     */
    public function parseTerm(array $data)
    {
        $parts = explode(' ', $data[0]);
        return [
            'year'     => end($parts),
            'semester' => $parts[0],
            'block'    => $data[35],
        ];
    }
    
    /**
     * Opens a CSV file for read only access.
     * 
     * @return resource
     */
    public function openFile()
    {
        if (!$handle = fopen($this->path, 'r')) {
            throw new FileNotFoundException();
        }
        
        // Ignore the column headers.
        fgetcsv($handle);
        
        return $handle;
    }
    
    /**
     * Check the local memory cache for an instance for a desired object.
     * 
     * @param array            $instances
     * @param string           $key
     * @param ObjectRepository $repo
     *
     * @return null|AbstractEntity
     */
    protected function getStored(&$instances, $key, $repo)
    {
        if (null !== $instances) {
            if (array_key_exists($key, $instances)) {
                return $instances[$key];
            }
            
            return null;
        }
        
        $instances = [];
        $stored    = $repo->findAll();
        
        /* @var AbstractEntity $item */
        foreach ($stored as $item) {
            $instances[$this->getKey($item->getKeyArr())] = $item;
        }
        
        return $this->getStored($instances, $key, $repo);
    }
    
    /**
     * Persist the newly created object.
     * 
     * @param array          &$instances
     * @param string         $key
     * @param AbstractEntity $object
     *
     * @return AbstractEntity
     */
    protected function persist(array &$instances, $key, $object)
    {
        $this->getManager()->persist($object);
        
        return ($instances[$key] = $object);
    }
    
    /**
     * Generate a standardized key used for in memory storage.
     * 
     * @param array $parts
     *
     * @return string
     */
    protected function getKey(array $parts)
    {
        ksort($parts);
        
        return implode('-', $parts);
    }
    
    /**
     * Used for debugging an issue with CRNs colliding.
     * 
     * @return $this
     */
    protected function printDuplicateCrns()
    {
        $handle = $this->openFile();
        $crns   = [];
        
        while($data = fgetcsv($handle)) {
            if (!$this->isValidEntry($data)) {
                continue;
            }
            
            $crn = $data[4];
            if (!array_key_exists($crn, $crns)) {
                $crns[$crn] = [$data];
            } else {
                $crns[$crn][] = $data;
            }
        }
        
        $output = '';
        foreach ($crns as $crn => $rows) {
            if (1 === count($rows)) {
                continue;
            }
            
            $output .= "$crn:\n";
            $this->output->writeln("$crn:\n");
            
            foreach ($rows as $row) {
                $output .= "\t" . implode(',', $row) . "\n";
                $this->output->writeln("\t" . implode(',', $row));
            }
        }
        
        fclose($handle);
        
        $path   = "{$this->root_dir}/crn-duplicates.txt";
        $handle = fopen($path, 'w');
        
        fwrite($handle, $output);
        fclose($handle);
        
        return $this;
    }
    
    /**
     * Tiny wrapper around doctrine Registry.
     * 
     * @param string $className
     * @param mixed  $id
     *
     * @return AbstractEntity|object|null
     */
    private function find($className, $id)
    {
        return $this->doctrine->getManager()
            ->find($className, $id)
        ;
    }
    
    /**
     * Format the date string.
     * 
     * @param string $date
     *
     * @return \DateTime
     */
    private function getDate($date)
    {
        if ($date instanceof \DateTime) {
            return $date;
        }
        
        return new \DateTime($date);
    }
    
    /**
     * @return \Doctrine\Common\Persistence\ObjectManager|object
     */
    private function getManager()
    {
        return $this->doctrine->getManager();
    }
    
    /**
     * Sets the path to the csv to parse.
     * 
     * @param string $path
     *
     * @return $this
     */
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }
    
    /**
     * Sets the flag for including online sections.
     * 
     * @param boolean $on
     *
     * @return $this
     */
    public function setIncludeOnline($on)
    {
        $this->include_online = $on;
        return $this;
    }
    
    /**
     * Save memory by disabling sql query logging.
     */
    public function disableDoctrineLogging()
    {
        $this->doctrine
            ->getConnection()
            ->getConfiguration()
            ->setSQLLogger(null)
        ;
    }
    
    /**
     * Sets the root directory.
     * 
     * @param string $dir
     *
     * @return $this
     */
    public function setRootDir($dir)
    {
        $this->root_dir = $dir;
        
        return $this;
    }
}
