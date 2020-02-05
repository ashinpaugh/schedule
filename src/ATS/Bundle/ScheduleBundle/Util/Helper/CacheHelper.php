<?php

namespace ATS\Bundle\ScheduleBundle\Util\Helper;

use Buzz\Browser;
use Buzz\Client\Curl;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Contains methods for cache building / warming.
 * 
 * @author Austin Shinpaugh <ashinpaugh@ou.edu>
 */
class CacheHelper
{
    private $helper;
    private $dev_domain;
    private $prod_domain;
    private $environment;
    
    /**
     * CacheHelper constructor.
     *
     * @param CommandHelper $helper
     * @param String        $dev_domain
     * @param String        $prod_domain
     * @param String        $env
     */
    public function __construct(CommandHelper $helper, $dev_domain, $prod_domain, $env)
    {
        $this->helper      = $helper;
        $this->dev_domain  = $dev_domain;
        $this->prod_domain = $prod_domain;
        $this->environment = $env;
    }
    
    /**
     * Warm the cache and optionally build the assets.
     * 
     * @param null|string $environment
     * @param bool        $build_assets
     *
     * @return CacheHelper
     */
    public function warmHttpCache($environment = null, $build_assets = false)
    {
        if ($build_assets) {
            $this->dumpAssets($environment);
        }
        
        return $this->makeRequest();
    }
    
    /**
     * Dumps the assets before generating the HttpCache.
     * This is required since some assets are loaded inline.
     * 
     * @param string $environment
     * 
     * @return $this
     * @throws \ErrorException
     */
    public function dumpAssets($environment)
    {
        if (null === $environment) {
            $environment = $this->environment;
        }
        
        if (!$this->prepareAssets($environment)) {
            throw new \ErrorException("Unable to generate {$environment}'s assets.");
        }
        
        return $this;
    }
    
    /**
     * Makes an Http request to build the cache.
     * 
     * @return $this
     */
    protected function makeRequest()
    {
        $browser = new Browser(new Curl());
        $urls    = [$this->prod_domain, $this->dev_domain];
        
        if ('dev' === $this->environment) {
            $urls = [$this->dev_domain, $this->prod_domain];
        }
        
        $browser->getClient()->setTimeout(30);
        
        foreach ($urls as $url) {
            if (!$url) {
                continue;
            }
            
            $browser->get($url);
        }
        
        return $this;
    }
    
    /**
     * Call assetic:dump.
     * 
     * @param string $env
     *
     * @return bool
     */
    protected function prepareAssets($env)
    {
        $process = new Process($this->helper->getConsolePath() . " assetic:dump --env={$env} --no-debug --force");
        $process->run();
        
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        
        $finder = Finder::create()
            ->files()
            ->in($this->helper->getAppRoot() . '/web/assets/compiled')
            ->name('controllers.js')
            ->name('libraries.js')
            ->name('utils.js')
            
            
            ->name('app.css')
            ->name('app_print.css')
            ->name('inline-libraries.css')
            ->name('libraries.css')
        ;
        
        return 7 === $finder->count();
    }
}
