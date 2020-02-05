<?php

namespace ATS\Bundle\ScheduleBundle\Util\Helper;

/**
 * Helper class for commands.
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class CommandHelper
{
    protected $root_dir;
    protected $env;
    
    public function __construct($root_dir, $env)
    {
        $this->root_dir = $root_dir;
        $this->env      = $env;
    }
    
    /**
     * Return the full path to the bin/console component.
     * 
     * @return string
     */
    public function getConsolePath()
    {
        // return 'php ' . $this->getAppRoot() . '/bin/console';
        return '$(which php) ' . $this->getAppRoot() . '/bin/console';
    }
    
    /**
     * Return the app root.
     * 
     * @return string
     */
    public function getAppRoot()
    {
        return realpath($this->root_dir . '/..');
    }
}
