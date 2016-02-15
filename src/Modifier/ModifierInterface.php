<?php

namespace RonRademaker\ReleaseBuilder\Modifier;

/**
 * Interface defining how something in PHP source can be modified
 *
 * @author Ron Rademaker
 */
interface ModifierInterface
{
    /**
     * Create a modifier to modify $source
     *
     * @param string $source
     */
    public function __construct($source);

    /**
     * Perform modifications and return updated source
     *
     * @param mixed $key
     * @param mixed $value
     * @return string
     */
    public function modify($key, $value);
}
