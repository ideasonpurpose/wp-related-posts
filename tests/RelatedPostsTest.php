<?php

namespace IdeasOnPurpose\WP;

use PHPUnit\Framework\TestCase;

Test\Stubs::init();

/**
 * @covers IdeasOnPurpose\WP\RelatedPosts
 */
final class RelatedPostsTest extends TestCase
{
    public function setUp(): void
    {
        global $actions, $filters, $transients;
        $actions = [];
        $filters = [];
        $transients = [];
    }

    public function testConstructor()
    {
        $actual = new RelatedPosts();

        $this->assertIsString($actual->namespace);
        $this->assertIsString($actual->rest_base);
        $this->assertCount(3, $actual->weights);
        // $this->assertEmpty($actual->types);

        $this->assertCount(1, all_added_actions());
        $this->assertCount(0, all_added_filters());
        $this->assertContains(['rest_api_init', 'registerRestRoutes'], all_added_actions());

        $this->assertInstanceOf('\Random\Randomizer', $actual->randomizer);
    }

    public function testValidateAndMergeArgs()
    {
        $a = 5;
        $b = 10;
        $rp = new RelatedPosts(['weights' => ['a' => 3]]);
        $rp->validateAndMergeArgs(['weights' => ['a' => $a, 'b' => ($b = $b)]]);

        // d($rp->weights);
        $this->assertEquals($a, $rp->weights['a']);
        $this->assertLessThan($b, $rp->weights['b']);
        $this->assertCount(5, $rp->weights);
    }

    // public function testValidateAndMergeArgs_types()
    // {
    //     $type = 'card';

    //     $rp = new RelatedPosts(['types' => ['post']]);
    //     $rp->validateAndMergeArgs(['types' => [$type]]);

    //     $this->assertContains($type, $rp->types);
    //     $this->assertCount(2, $rp->types);
    // }

    public function testValidateAndMergeArgs_notArrays()
    {
        $a = 5;
        $b = 10;
        $rp = new RelatedPosts(['weights' => ['a' => $a]]);
        $rp->validateAndMergeArgs(['weights' => 5, 'types' => 'frogs']);

        $this->assertEquals($a, $rp->weights['a']);
        $this->assertCount(4, $rp->weights);
        // $this->assertCount(0, $rp->types);
    }

    public function testValidateAndMergeArgs_nonNumericWeights()
    {
        $a = 1;
        $b = 2;
        $c = 3;
        $rp = new RelatedPosts(['weights' => ['a' => $a, 'b' => $b, 'c' => 0]]);
        $rp->validateAndMergeArgs(['weights' => ['a' => 'dog', 'b' => 'cat', 'c' => $c]]);

        $this->assertEquals($a, $rp->weights['a']);
        $this->assertEquals($b, $rp->weights['b']);
        $this->assertEquals($c, $rp->weights['c']);
        $this->assertIsNotString($rp->weights['a']);
        $this->assertIsNotString($rp->weights['b']);
    }

    public function testValidateAndMergeArgs_argsNotArray()
    {
        // $expected = ['weights' => ['a' => 1], 'types' => ['dog']];
        $rp = new RelatedPosts();
        $expected = $rp->weights;

        // null
        $rp->validateAndMergeArgs(null);
        $this->assertSame($rp->weights, $expected);

        // string
        $rp->validateAndMergeArgs('string');
        $this->assertSame($rp->weights, $expected);

        // number
        $rp->validateAndMergeArgs(1337);
        $this->assertSame($rp->weights, $expected);
    }

    public function testClampWeights()
    {
        $min = 1;
        $max = 6;
        $src = ['a' => 12, 'b' => -5];
        $expected = ['a' => $max, 'b' => $min];

        $rp = new RelatedPosts();
        $actual = $rp->clampWeights($src, $min, $max);
        $this->assertNotEquals($src, $actual);
        $this->assertEquals($actual, $expected);
        $this->assertEquals($expected['a'], $max);
        $this->assertEquals($expected['b'], $min);

        // make sure numbers-as-strings work too
        $src = ['a' => '3'];
        $actual = $rp->clampWeights($src);
        $this->assertIsInt($actual['a']);
        $this->assertNotSame($src['a'], $actual['a']);
    }

    public function testFetchPosts_noTransient()
    {
        global $transients, $get_transient, $set_transient;

        $expected = ['array of posts'];

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectPosts'])
            ->getMock();

        $rp->method('collectPosts')->willReturn($expected);

        $post = (object) [
            'guid' => 'post_guid',
        ];

        $transientName = md5($post->guid . json_encode($rp->weights));
        $get_transient[$transientName] = false;

        $rp->WP_DEBUG = false;
        $actual = $rp->fetchPosts($post);

        // d($transients);

        $this->assertContains('get', $transients[0]);
        $this->assertContains('set', $transients[1]);
        $this->assertSame($actual, $expected);

        // d($transients, $get_transient, $set_transient);
    }

    public function testFetchPosts_debug()
    {
        global $transients, $get_transient;

        $expected = ['posts from transient'];

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectPosts'])
            ->getMock();

        $rp->method('collectPosts')->willReturn($expected);
        $rp->WP_DEBUG = false;

        $post = (object) [
            'guid' => 'post_guid',
        ];

        $rp->WP_DEBUG = true;
        $actual = $rp->fetchPosts($post);

        $this->assertNotContains('get', $transients[0]);
        $this->assertContains('set', $transients[0]);

        $this->assertSame($actual, $expected);
    }

    public function testFetchPosts_noPost()
    {
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectPosts'])
            ->getMock();

        $rp->method('collectPosts')->willReturn(['array of posts']);

        $actual = $rp->fetchPosts(null);

        $this->assertSame($actual, []);
    }

    public function testArrayMergeWeighted()
    {
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getWeight'])
            ->getMock();

        $rp->method('getWeight')->willReturn(3);

        $src = [1, 2];
        $merge = [33, 44];
        $expected = [1, 2, 33, 33, 33, 44, 44, 44];
        $expected = [1, 2, 33, 44, 33, 44, 33, 44];
        $actual = $rp->arrayMergeWeighted($src, $merge, 'weight_slug');

        $this->assertSame($actual, $expected);
        $this->assertEqualsCanonicalizing($actual, $expected);
    }

    public function testCollectPosts()
    {
        global $object_taxonomies, $post_types, $the_terms, $get_posts, $posts;

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['arrayMergeWeighted'])
            ->getMock();

        $one = (object) ['ID' => 11, 'post_date' => '2024-05-31'];
        $two = (object) ['ID' => 22, 'post_date' => '2024-05-15'];
        $three = (object) ['ID' => 33, 'post_date' => '2024-05-01'];

        $rp->expects($this->exactly(2))
            ->method('arrayMergeWeighted')
            ->willReturn([$one, $two, $two, $three, $three, $three]);

        $post = (object) [
            'ID' => '25',
            'post_type' => 'post',
        ];

        /**
         * Set up globals for mocking
         * TODO: 'article' post_type will be filtered out, any way to test that?
         */
        $post_types = ['attachment', 'page', 'article'];
        $object_taxonomies = ['topic' => 'topic', 'color' => 'color'];
        $the_terms = [(object) ['slug' => 'purple']];
        $get_posts = null;
        $posts = [11 => $one, 22 => $two, 33 => $three];
        // d($get_posts);
        $actual = $rp->collectPosts($post);

        // d($actual);
        // d($get_posts);
        $this->assertEquals(5, 5);

        /**
         * Check args passed to the get_posts loop include all taxonomies
         */
        // $this->assertContains('color', $get_posts[1]['tax_query'][0]);
        $this->assertContains('topic', ...$get_posts[0]['tax_query']);
        $this->assertContains('color', ...$get_posts[1]['tax_query']);
    }

    public function testCollectPosts_noPost()
    {
        $rp = new RelatedPosts();
        $actual = $rp->collectPosts(null);
        $this->assertEmpty($actual);
    }

    public function testGetWeight()
    {
        $expected_cat = 1;
        $expected_tag = 4;
        $rp = new RelatedPosts([
            'weights' => ['cat' => $expected_cat, 'post_tag' => $expected_tag],
        ]);
        $this->assertEquals($rp->getWeight('tag'), $expected_tag);
        $this->assertEquals($rp->getWeight('post_tag'), $expected_tag);
        $this->assertEquals($rp->getWeight('cat'), $expected_cat);
    }
}
