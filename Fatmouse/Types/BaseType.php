<?php

namespace Fatmouse\Types;

use Fatmouse\Util;

/**
 * A base class for any Fatmouse API data type.
 */
class BaseType
{
    /**
     * Constructor.
     * - translates 'under_score' names into 'camelCase'
     * - converts datetime fields value into \Datetime
     * 
     * @param \stdClass $object Raw object returned by Fatmouse
     */
    public function __construct($object)
    {
        foreach (get_object_vars($object) as $key => $value) {
            if (substr($key, -4) === 'date') {
                $value = new \Datetime($value);
            }
            $key = Util::camelize($key);
            $this->$key = $value;
        }
    }
}