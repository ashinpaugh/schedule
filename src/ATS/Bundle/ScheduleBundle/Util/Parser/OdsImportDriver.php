<?php

namespace ATS\Bundle\ScheduleBundle\Util\Parser;

use ATS\Bundle\ScheduleBundle\Entity\Building;
use ATS\Bundle\ScheduleBundle\Entity\Campus;
use ATS\Bundle\ScheduleBundle\Entity\Course;
use ATS\Bundle\ScheduleBundle\Entity\Instructor;
use ATS\Bundle\ScheduleBundle\Entity\Room;
use ATS\Bundle\ScheduleBundle\Entity\Section;
use ATS\Bundle\ScheduleBundle\Entity\Subject;
use ATS\Bundle\ScheduleBundle\Entity\Term;
use ATS\Bundle\ScheduleBundle\Entity\TermBlock;
use Doctrine\DBAL\Connection;

/**
 * The driver pattern for ODS imports.
 * 
 * @author Austin Shinpaugh
 */
class OdsImportDriver extends AbstractImportDriver
{
    /**
     * {@inheritdoc}
     */
    public function init($mixed = null)
    {
        $this->loadRawData($this->helper->getAcademicPeriod());
    }
    
    /**
     * {@inheritdoc}
     */
    public function getCount()
    {
        if ($this->num_rows) {
            return $this->num_rows;
        }
        
        /* @var Connection $connection */
        $connection = $this->getDoctrine()->getConnection('ods');
        $statement  = $connection->prepare("
            SELECT COUNT(*)
            FROM `course_section` AS cs
            WHERE cs.status_code = :status_code
              AND cs.sub_academic_period IS NOT NULL
              AND cs.section_number REGEXP '[0-9]+'
        ");
        
        $statement->bindValue('status_code', 'A', \PDO::PARAM_STR);
        $statement->execute();
        
        return $this->num_rows = $statement->fetchColumn(0);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function loadRawData($mixed = null)
    {
        $ac_start = null;
        $ac_end   = null;
        
        $this->helper
            ->assignAcademicPoints($ac_start, $ac_end)
        ;
        
        /* @var Connection $connection */
        $connection = $this->getDoctrine()->getConnection('ods');
        $statement  = $connection->prepare("
            SELECT
              cs.academic_period, cs.sub_academic_period,
              
              cs.subject_code, cs.course_number, cs.section_number,
              cs.section_title, cs.course_reference_number, cs.section_id,
              cs.start_date, cs.end_date, mt.start_time, mt.end_time,
              mt.meeting_days, cs.status_code, mt.meeting_type_code,
              
              cs.maximum_enrollment, cs.actual_enrollment, cs.seats_available,
              cs.waitlist_count, cs.waitlist_seats_available,
              
              cs.campus_code, mt.building_desc, mt.room,
              
              cs.instructor1_id, cs.instructor1_email,
              CONCAT(cs.instructor1_first_name, ' ', cs.instructor1_last_name) AS instructor_name
            FROM `course_section` AS cs
            JOIN `meeting_time` AS  mt
              ON cs.academic_period = mt.academic_period
                AND cs.course_reference_number = mt.course_reference_number
            WHERE cs.status_code = :status_code
              AND cs.sub_academic_period IS NOT NULL
              AND cs.section_number REGEXP '[0-9]+'
              AND cs.academic_period BETWEEN :ap_start AND :ap_end
              AND (mt.start_time IS NOT NULL AND mt.end_time IS NOT NULL)
            ORDER BY cs.academic_period, cs.sub_academic_period
        ");
        
        $statement->bindValue('status_code', 'A', \PDO::PARAM_STR);
        $statement->bindValue('ap_start', $ac_start, \PDO::PARAM_INT);
        $statement->bindValue('ap_end', $ac_end, \PDO::PARAM_INT);
        $statement->execute();
        
        return $this->setEntries($statement->fetchAll());
    }
    
    /**
     * {@inheritdoc}
     */
    public function createCampus()
    {
        return new Campus($this->getEntry('campus_code'));
    }
    
    /**
     * {@inheritdoc}
     */
    public function createBuilding(Campus $campus = null)
    {
        $campus   = $campus ?: $this->createCampus();
        $building = new Building(
            $campus,
            $this->getLocation('building')
        );
        
        return $building;
    }
    
    /**
     * {@inheritdoc}
     */
    public function createRoom(Building $building = null)
    {
        $building = $building ?: $this->createBuilding();
        $number   = $this->getLocation('room') ?: '0000';
        $room     = new Room(
            $building,
            $number
        );
        
        return $room;
    }
    
    /**
     * {@inheritdoc}
     */
    public function createInstructor()
    {
        $data       = $this->getEntry();
        $instructor = new Instructor(
            (int) $data['instructor1_id'],
            $data['instructor1_id'] ? $data['instructor_name'] : 'N/A'
        );
        
        return $instructor->setEmail($data['instructor1_email']);
    }
    
    /**
     * {@inheritdoc}
     */
    public function createTerm()
    {
        $entry = $this->getEntry();
        $dict  = $this->parseTerm($entry);
        $name  = $dict['semester'] . ' ' . $dict['year'];
        $term  = new Term($name, $dict['year'], $dict['semester']);
        $block = new TermBlock($term, $dict['block']);
        
        $term->addBlock($block);
        
        return $block;
    }
    
    /**
     * {@inheritdoc}
     */
    public function createSubject()
    {
        return new Subject($this->getEntry('subject_code'));
    }
    
    /**
     * {@inheritdoc}
     */
    public function createCourse(Subject $subject = null)
    {
        $entry   = $this->getEntry();
        $subject = $subject ?: $this->createSubject();
        $course  = new Course($subject, $entry['course_number']);
        
        $course
            ->setName($entry['section_title'])
            // TODO: fix when added to course_section.
            ->setLevel(array_key_exists('level', $entry) ? $entry['level'] : '')
        ;
        
        return $course;
    }
    
    /**
     * {@inheritdoc}
     */
    public function createSection(Subject $subject = null)
    {
        $entry   = $this->getEntry();
        $section = new Section();
        
        $section
            ->setCrn($entry['course_reference_number'])
            ->setDays($entry['meeting_days'])
            ->setStartDate($this->getDate($entry['start_date']))
            ->setEndDate($this->getDate($entry['end_date']))
            ->setStartTime($entry['start_time'])
            ->setEndTime($entry['end_time'])
            ->setStatus($entry['status_code'])
            ->setNumber($entry['section_number'])
            ->setNumEnrolled($entry['actual_enrollment'])
            ->setMaximumEnrollment($entry['maximum_enrollment'])
            ->setMeetingType($entry['meeting_type_code'])
        ;
        
        return $section;
    }
    
    /**
     * {@inheritdoc}
     */
    protected function parseBuilding()
    {
        $data = $this->getEntry();
        
        return [
            'building' => $data['building_desc'] ?: 'N/A',
            'room'     => $data['room'] ?: '0000',
        ];
    }
    
    /**
     * Parse the term data.
     * 
     * Semesters that end in 20 are Spring of the following year.
     * IE: 201720 = Spring 2018
     * 
     * @param array $data
     *
     * @return array
     */
    protected function parseTerm(array $data)
    {
        $term  = $data['academic_period'];
        $year  = substr($term, 0, 4);
        $code  = substr($term, 4);
        $block = $data['sub_academic_period'];
        
        if ('EXAM' === $data['meeting_type_code']) {
            $block = $data['meeting_type_code'];
        }
        
        return [
            'year'     => $code < 20 ? $year : $year + 1,
            'semester' => $this->parseSemester($code),
            'block'    => $block,
        ];
    }
    
    /**
     * Get the text based version of a semester.
     * 
     * @param integer $code
     *
     * @return string
     */
    private function parseSemester($code)
    {
        switch ($code) {
            case 10:
                return 'Fall';
            case 20:
                return 'Spring';
            case 30:
                return 'Summer';
            default:
                return 'Unknown';
        }
    }
}
