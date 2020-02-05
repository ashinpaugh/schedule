<?php

namespace ATS\Bundle\ScheduleBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use JMS\Serializer\Annotation as Serializer;

/**
 * Tracks the status of imports.
 * 
 * @ORM\Entity()
 * @ORM\HasLifecycleCallbacks()
 */
class UpdateLog extends AbstractEntity
{
    const STARTED   = 0;
    const COMPLETED = 1;
    
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     * 
     * @var integer
     */
    protected $id;
    
    /**
     * @ORM\Column(type="datetime")
     * @var \DateTime
     */
    protected $start;
    
    /**
     * @ORM\Column(type="datetime", nullable=true)
     * @var \DateTime
     */
    protected $end;
    
    /**
     * @ORM\Column(type="smallint")
     * @var integer
     */
    protected $status;
    
    /**
     * @ORM\Column(type="string", length=4)
     * @var string
     */
    protected $source;
    
    /**
     * Tracks the Peak Memory usage in bytes.
     * 
     * @Serializer\Exclude()
     * 
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    protected $peak_memory;
    
    /**
     * UpdateLog constructor.
     *
     * @param string $source Either book or ods.
     */
    public function __construct($source)
    {
        $this->start  = new \DateTime();
        $this->status = static::STARTED;
        $this->source = $source;
    }
    
    /**
     * @ORM\PrePersist()
     */
    public function prePersist()
    {
        $this->id = null;
    }
    
    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
    
    /**
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }
    
    /**
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }
    
    /**
     * @param \DateTime $end
     *
     * @return UpdateLog
     */
    public function setEnd($end)
    {
        $this->end = $end;
        
        return $this;
    }
    
    /**
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }
    
    /**
     * @param int $status
     *
     * @return UpdateLog
     */
    public function setStatus($status)
    {
        $this->status = $status;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }
    
    /**
     * @param string $source
     *
     * @return UpdateLog
     */
    public function setSource($source)
    {
        $this->source = $source;
        
        return $this;
    }
    
    /**
     * @return string
     */
    public function getPeakMemory()
    {
        return $this->peak_memory;
    }
    
    /**
     * @param string $peak_memory
     *
     * @return UpdateLog
     */
    public function setPeakMemory($peak_memory)
    {
        $this->peak_memory = $peak_memory;
        
        return $this;
    }
    
    /**
     * Returns an array of identifiable information.
     *
     * @return array
     */
    public function getKeyArr()
    {
        return [];
    }
}
