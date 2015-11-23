<?php

namespace Fatmouse\Agent\Tasks;

/**
 * OS level tasks
 */
class Sys
{
    /**
     * Constructor
     * 
     * @param \Fatmouse\Agent\Agent $agent
     */
    public function __construct($agent)
    {
        $this->agent = $agent;
    }
    
    /**
     * Get OS facts
     * 
     * @param string|array $keys
     * @return \stdClass a facts subtree
     * @see https://github.com/Scalr/fatmouse#facts
     */
    public function getFacts($keys)
    {
        if (is_string($keys)) {
            $keys = [$keys];
        }
        return $this->agent->call("sys.get_facts", ['keys' => $keys]);
    }
    
    /**
     * Set OS hostname
     * TODO: document $hostname limitations
     * 
     * @param string $hostname Hostname
     */
    public function setHostname($hostname)
    {
        return $this->agent->call("sys.set_hostname", ['hostname' => $hostname]);
    }
    
    /**
     * Synchronize OS time
     * 
     * @param array $ntpServers List of NTP servers hostnames
     */
    public function syncTime($ntpServers = null)
    {
        return $this->agent->call("sys.sync_time", ['ntp_servers' => $ntpServers]);
    }
}
