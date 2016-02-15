<?php

namespace RonRademaker\ReleaseBuilder\Modifier;

use PHPUnit_Framework_TestCase;

/**
 * Unit test for the ConstantModifierTest
 *
 * @author Ron Rademaker
 */
class ConstantModifierTest extends PHPUnit_Framework_TestCase
{
    /**
     * Test modify version
     */
    public function testModify()
    {
        $code = file_get_contents(__DIR__.'/../../src/Command/ReleaseCommand.php');
        $modifier = new ConstantModifier($code);

        $newCode = $modifier->modify('VERSION', '1.0.0');

        $this->assertRegExp('/const VERSION = \'1.0.0\';/', $newCode);
    }
}
