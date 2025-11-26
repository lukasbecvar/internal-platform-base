<?php

namespace App\Tests\Annotation;

use App\Annotation\CsrfProtection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class CsrfProtectionTest
 *
 * Test cases for CsrfProtection attribute
 *
 * @package App\Tests\Annotation
 */
#[CoversClass(CsrfProtection::class)]
class CsrfProtectionTest extends TestCase
{
    /**
     * Test default enabled value
     *
     * @return void
     */
    public function testDefaultEnabled(): void
    {
        // instantiate attribute without parameters
        $attribute = new CsrfProtection();

        // call tested method
        $result = $attribute->isEnabled();

        // assert default is true
        $this->assertTrue($result);
    }

    /**
     * Test explicit enabled = true
     *
     * @return void
     */
    public function testEnabledTrue(): void
    {
        $attribute = new CsrfProtection(true);

        // call tested method
        $result = $attribute->isEnabled();

        // assert true
        $this->assertTrue($result);
    }

    /**
     * Test explicit enabled = false
     *
     * @return void
     */
    public function testEnabledFalse(): void
    {
        $attribute = new CsrfProtection(false);

        // call tested method
        $result = $attribute->isEnabled();

        // assert false
        $this->assertFalse($result);
    }
}
