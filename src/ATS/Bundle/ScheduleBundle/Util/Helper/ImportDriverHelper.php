<?php

namespace ATS\Bundle\ScheduleBundle\Util\Helper;

use Doctrine\Bundle\DoctrineBundle\Registry;

class ImportDriverHelper
{
    /**
     * @var Registry
     */
    protected $doctrine;
    
    /**
     * @var String
     */
    protected $service_id;
    
    /**
     * @var String
     */
    protected $academic_period;
    
    /**
     * @var Integer
     */
    protected $num_years;
    
    /**
     * ImportDriverHelper constructor.
     *
     * @param Registry $doctrine
     * @param Integer  $num_years
     */
    public function __construct(Registry $doctrine, $num_years)
    {
        $this->doctrine  = $doctrine;
        $this->num_years = $num_years;
    }
    
    /**
     * Get the service id of the driver being used.
     * 
     * @return String
     */
    public function getServiceId()
    {
        return $this->service_id;
    }
    
    /**
     * Sets the service id.
     * 
     * @param string $id
     *
     * @return $this
     * @throws \ErrorException
     */
    public function setServiceId($id)
    {
        if (!static::isValidImportId($id)) {
            throw new \ErrorException("Invalid input provided for source option. Must be either 'book' or 'ods'.");
        }
        
        $this->service_id = sprintf("schedule.%s_import", $id);
        
        return $this;
    }
    
    /**
     * @return String
     */
    public function getAcademicPeriod()
    {
        return $this->academic_period;
    }
    
    /**
     * @param string $period The year to start from.
     *
     * @return $this
     */
    public function setAcademicPeriod($period)
    {
        if ($period) {
            $this->academic_period = (int) ($period . '00');
        } else {
            $this->academic_period = null;
        }
        
        return $this;
    }
    
    /**
     * Accepts two points to byref assign values based on the
     * input taken from the command line.
     * 
     * @param Integer $start
     * @param Integer $stop
     *
     * @return $this
     */
    public function assignAcademicPoints(&$start, &$stop)
    {
        $year  = $this->academic_period ?: date('Y');
        $start = (int) (($year - $this->num_years) . '00');
        $stop  = 300000;
        //$stop  = (int) (date('Y') . '99');
        
        return $this;
    }
    
    /**
     * Validate the service ID.
     * 
     * @param string $id
     *
     * @return bool
     */
    public static function isValidImportId($id)
    {
        return in_array($id, ['book', 'ods']);
    }
    
    /**
     * FK Checks need to be disabled when using TRUNCATE instead of DELETE
     * during the :fixtures:load command.
     * 
     * @param boolean $enabled
     *
     * @return int
     */
    public function toggleFKChecks($enabled)
    {
        $connection = $this->doctrine->getConnection();
        
        return $connection->executeUpdate(sprintf(
            "SET foreign_key_checks = %b;",
            (int) $enabled
        ));
    }
}