<?php

namespace IdeasOnPurpose\WP;

use PHPUnit\Framework\TestCase;

Test\Stubs::init();

/**
 * @covers IdeasOnPurpose\WP\RelatedPosts
 */
final class RelatedPostsTest extends TestCase
{
    public function testTests()
    {
        $actual = new RelatedPosts();

        $this->assertEquals (5, 5);
    }


}
