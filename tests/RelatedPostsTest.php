<?php

namespace IdeasOnPurpose\WP;

use PHPUnit\Framework\TestCase;

Test\Stubs::init();

if (!function_exists(__NAMESPACE__ . '\error_log')) {
    function error_log($err)
    {
        global $error_log;
        $error_log = $err;
    }
}

/**
 * @covers IdeasOnPurpose\WP\RelatedPosts
 */
final class RelatedPostsTest extends TestCase
{
    public function setUp(): void
    {
        global $posts, $actions, $get_post, $filters, $transients;
        unset($GLOBALS['post']);
        $posts = [];
        $actions = [];
        $filters = [];
        $get_post = [];
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

    // public function testInitPost_globalPost()
    // {
    //     global $post;
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $expected = 44;
    //     $post = (object) [
    //         'ID' => $expected,
    //         'post_type' => 'post',
    //     ];

    //     $rp->initPost();

    //     $this->assertEquals($expected, $rp->post->ID);
    // }

    // public function testInitPost_argsInteger()
    // {
    //     global $post, $get_post;
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $post = (object) [
    //         'ID' => 55,
    //         'post_type' => 'post',
    //     ];

    //     $expected = 124;
    //     // $posts[$expected] = (object) [
    //     //     'ID' => $expected,
    //     //     'post_type' => 'book',
    //     // ];
    //     $get_post[] = (object) [
    //         'ID' => $expected,
    //         'post_type' => 'book',
    //     ];

    //     $rp->initPost(['post' => $expected]);

    //     $this->assertNotEquals($post->ID, $rp->post->ID);
    //     $this->assertEquals($expected, $rp->post->ID);
    // }

    // public function testInitTypes()
    // {
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $expected = 'book';
    //     $rp->post = (object) [
    //         'ID' => 88,
    //         'post_type' => $expected,
    //     ];
    //     $rp->initTypes();

    //     $this->assertIsArray($rp->types);
    //     $this->assertContains($expected, $rp->types);
    // }

    // public function testInitTypes_noPost()
    // {
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $rp->initTypes();

    //     $this->assertIsArray($rp->types);
    //     $this->assertEmpty($rp->types);

    //     $expected = ['dog'];
    //     $rp->types = $expected;

    //     $rp->initTypes();

    //     $this->assertSame($expected, $rp->types);
    // }

    // public function testInitTypes_typesArray()
    // {
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $expected = ['animals'];

    //     $rp->initTypes(['post_types' => $expected]);

    //     $this->assertIsArray($rp->types);
    //     $this->assertSame($expected, $rp->types);

    //     $rp->types = $expected;
    // }

    // public function testInitTypes_typesString()
    // {
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $expected = 'film';

    //     $rp->initTypes(['post_types' => $expected]);

    //     $this->assertIsArray($rp->types);
    //     $this->assertContains($expected, $rp->types);

    //     $rp->types = [$expected];
    // }

    public function testNormalizeArgs()
    {
        global $get_post, $post_type;
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $post = (object) [
            'ID' => 55,
            'post_type' => 'post',
        ];

        $get_post = [$post];

        $src = ['weights' => ['dog' => '125']];
        $expected = ['post' => $post, 'weights' => ['dog' => 125]];
        $actual = $rp->normalizeArgs($src);

        $this->assertSame($expected, $actual);

        // post_types should not contain duplicates or 'attachment'
        $get_post = [$post];
        $src = ['post_types' => ['dog', 'dog', 'attachment']];
        $expected = ['post' => $post, 'post_types' => ['dog']];
        $actual = $rp->normalizeArgs($src);
        $this->assertNotContains('attachment', $actual['post_types']);
        $this->assertSame($expected, $actual);

        // post_types can not be empty
        $get_post = [$post];
        $post_type = $post->post_type;
        $src = ['post_types' => []];
        $expected = ['post' => $post, 'post_types' => [$post->post_type]];
        $actual = $rp->normalizeArgs($src);
        $this->assertNotContains('attachment', $actual['post_types']);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['offset' => '4'];
        $expected = ['post' => $post, 'offset' => 4];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['offset' => 'not a number'];
        $expected = ['post' => $post];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['has_post_thumbnail' => true];
        $expected = ['post' => $post, 'has_post_thumbnail' => true];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['has_post_thumbnail' => false];
        $expected = ['post' => $post, 'has_post_thumbnail' => false];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['has_post_thumbnail' => 'yes'];
        $expected = ['post' => $post, 'has_post_thumbnail' => true];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['has_post_thumbnail' => 'no'];
        $expected = ['post' => $post, 'has_post_thumbnail' => false];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['has_post_thumbnail' => 'true'];
        $expected = ['post' => $post, 'has_post_thumbnail' => true];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['has_post_thumbnail' => 'false'];
        $expected = ['post' => $post, 'has_post_thumbnail' => false];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['posts_per_page' => 'not a number'];
        $expected = ['post' => $post];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        $get_post = [$post];
        $src = ['posts_per_page' => '4'];
        $expected = ['post' => $post, 'posts_per_page' => 4];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        // too big
        $get_post = [$post];
        $src = ['posts_per_page' => 125];
        $expected = ['post' => $post, 'posts_per_page' => 20];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);

        // too small
        $get_post = [$post];
        $src = ['posts_per_page' => -1];
        $expected = ['post' => $post, 'posts_per_page' => 1];
        $actual = $rp->normalizeArgs($src);
        $this->assertSame($expected, $actual);
    }

    public function testNormalizeArgs_noPost()
    {
        global $post, $posts, $get_posts;
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $posts = [
            (object) [
                'ID' => 222,
            ],
        ];
        $src = [];
        $actual = $rp->normalizeArgs($src);
        $this->assertNull($post);
        $this->assertNotNull($actual['post']);
    }

    public function testNormalizeArgs_revision()
    {
        global $get_post;
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $expected = 6677;
        $post1 = (object) [
            'ID' => 4455,
            'post_type' => 'revision',
            'post_parent' => $expected,
        ];

        $post2 = (object) [
            'ID' => $expected,
            'post_type' => 'post',
            'post_parent' => 0,
        ];

        $get_post = [$post1, $post2];

        $src = ['post' => $post1];
        $actual = $rp->normalizeArgs($src);
        $this->assertNotEquals('revision', $actual['post']->post_type);
        $this->assertEquals($expected, $actual['post']->ID);
    }

    // public function testValidateAndMergeArgs()
    // {
    //     $a = 5;
    //     $b = 10;
    //     $rp = new RelatedPosts(['weights' => ['a' => 3]]);
    //     $rp->validateAndMergeArgs(['weights' => ['a' => $a, 'b' => ($b = $b)]]);

    //     // d($rp->weights);
    //     $this->assertEquals($a, $rp->weights['a']);
    //     $this->assertLessThan($b, $rp->weights['b']);
    //     $this->assertCount(5, $rp->weights);
    // }

    // public function testValidateAndMergeArgs_types()
    // {
    //     $type = 'card';

    //     $rp = new RelatedPosts(['types' => ['post']]);
    //     $this->assertCount(1, $rp->types);

    //     $rp->validateAndMergeArgs(['types' => [$type]]);
    //     $this->assertContains('post', $rp->types);
    //     $this->assertContains($type, $rp->types);
    //     $this->assertCount(2, $rp->types);

    //     /**
    //      * Check there are no duplicates
    //      */
    //     $rp->validateAndMergeArgs(['types' => [$type, 'post']]);
    //     $this->assertCount(2, $rp->types);
    // }

    // public function testValidateAndMergeArgs_postAndTypes()
    // {
    //     $type = 'card';

    //     $rp = new RelatedPosts(['types' => ['post']]);

    //     $rp->validateAndMergeArgs(['types' => [$type]]);

    //     $this->assertContains($type, $rp->types);

    //     // d($rp->types);
    //     $this->assertCount(2, $rp->types);
    // }

    // // public function testValidateAndMergeArgs_notArrays()
    // // {
    // //     $a = 5;
    // //     $b = 10;
    // //     $rp = new RelatedPosts(['weights' => ['a' => $a]]);
    // //     // $rp->validateAndMergeArgs(['weights' => 5, 'types' => 'frogs']);

    // //     $this->assertEquals($a, $rp->weights['a']);
    // //     $this->assertCount(4, $rp->weights);
    // //     // $this->assertCount(0, $rp->types);
    // // }

    // public function testValidateAndMergeArgs_nonNumericWeights()
    // {
    //     $a = 1;
    //     $b = 2;
    //     $c = 3;
    //     $rp = new RelatedPosts(['weights' => ['a' => $a, 'b' => $b, 'c' => 0]]);
    //     $rp->validateAndMergeArgs(['weights' => ['a' => 'dog', 'b' => 'cat', 'c' => $c]]);

    //     $this->assertEquals($a, $rp->weights['a']);
    //     $this->assertEquals($b, $rp->weights['b']);
    //     $this->assertEquals($c, $rp->weights['c']);
    //     $this->assertIsNotString($rp->weights['a']);
    //     $this->assertIsNotString($rp->weights['b']);
    // }

    // public function testValidateAndMergeArgs_argsNotArray()
    // {
    //     // $expected = ['weights' => ['a' => 1], 'types' => ['dog']];
    //     $rp = new RelatedPosts();
    //     $expected = $rp->weights;

    //     // null
    //     $rp->validateAndMergeArgs(null);
    //     $this->assertSame($rp->weights, $expected);

    //     // string
    //     $rp->validateAndMergeArgs('string');
    //     $this->assertSame($rp->weights, $expected);

    //     // number
    //     $rp->validateAndMergeArgs(1337);
    //     $this->assertSame($rp->weights, $expected);
    // }

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

    public function testGetTransientName()
    {
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $src = ['weights' => 1, 'post_types' => 2, 'post' => (object) ['ID' => 3]];

        $actual = $rp->getTransientName($src);
        $this->assertEquals(strlen('related_posts_') + 32, strlen($actual));
        $this->assertStringStartsWith('related_posts_', $actual);
    }

    public function testFetchPosts_noTransient()
    {
        global $transients, $get_transient, $set_transient;

        $expected = ['array of posts'];

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectPosts', 'getTransientName'])
            ->getMock();

        $transientName = 'transient_name';
        $rp->method('collectPosts')->willReturn($expected);
        $rp->method('getTransientName')->willReturn($transientName);

        $get_transient[$transientName] = false;

        $rp->WP_DEBUG = false;
        $actual = $rp->fetchPosts(['post' => 'required']);

        // d($transients, $get_transient);

        $this->assertContains('get', $transients[0]);
        $this->assertContains('set', $transients[1]);
        $this->assertSame($actual, $expected);

        // d($transients, $get_transient, $set_transient);
    }

    // public function testFetchPosts_noPost()
    // {
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods([])
    //         ->getMock();

    //     $actual = $rp->fetchPosts([]);
    //     $this->assertEmpty($actual);
    // }

    public function testFetchPosts_debug()
    {
        global $transients;

        $expected = ['posts from transient'];

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectPosts', 'getTransientName'])
            ->getMock();

        $transientName = 'transient_name';
        $rp->method('collectPosts')->willReturn($expected);
        $rp->method('getTransientName')->willReturn($transientName);

        $rp->WP_DEBUG = false;

        $post = (object) [
            'ID' => 123,
        ];

        $rp->WP_DEBUG = true;
        // $actual = $rp->fetchPosts();
        $actual = $rp->fetchPosts(['post' => $post]);

        $this->assertNotContains('get', $transients[0]);
        $this->assertContains('set', $transients[0]);

        $this->assertSame($actual, $expected);
    }

    // public function testFetchPosts_noPost()
    // {
    //     $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
    //         ->disableOriginalConstructor()
    //         ->onlyMethods(['collectPosts'])
    //         ->getMock();

    //     $rp->method('collectPosts')->willReturn(['array of posts']);

    //     $actual = $rp->fetchPosts();

    //     $this->assertSame($actual, []);
    // }

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
        global $get_posts, $get_post, $object_taxonomies, $posts, $the_terms, $wp_list_pluck;

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['arrayMergeWeighted'])
            ->getMock();

        $rp->expects($this->exactly(3))
            ->method('arrayMergeWeighted')
            ->willReturn([11, 22, 22, 33, 33]);

        $post = (object) [
            'ID' => '25',
            'post_type' => 'post',
        ];

        /**
         * Set up globals for mocking
         */
        $one = (object) ['ID' => 11, 'post_date' => '2024-05-31'];
        $two = (object) ['ID' => 22, 'post_date' => '2024-05-15'];
        $three = (object) ['ID' => 33, 'post_date' => '2024-05-01'];
        $get_post = [$one, $two, $three];
        $post_types = ['page', 'article'];
        $object_taxonomies = ['topic' => 'topic', 'color' => 'color'];
        $the_terms = [(object) ['slug' => 'purple']];
        $get_posts = null;
        $wp_list_pluck = [1, 2, 3];

        $actual = $rp->collectPosts([
            'post' => $post,
            'post_types' => $post_types,
            'has_post_thumbnail' => false,
        ]);

        $get_post_types = [];
        foreach ($get_posts as $query) {
            if (is_array($query['post_type'])) {
                $get_post_types = array_merge($get_post_types, $query['post_type']);
            } else {
                $get_post_types[] = $query['post_type'];
            }
        }

        /**
         * Check args passed to the get_posts loop include all taxonomies
         */

        /// The attachment post_type should have been filtered out
        $this->assertNotContains('attachment', $get_post_types);

        // check terms were passed to the taxonomy query
        $this->assertContains('topic', $get_posts[0]['tax_query'][0]);
        $this->assertContains('color', $get_posts[1]['tax_query'][0]);
        // $this->assertContains('topic', ...$get_posts[0]['tax_query']);
        // $this->assertContains('color', ...$get_posts[1]['tax_query']);

        $this->assertSame([22, 33, 11], array_keys($actual));
    }

    public function testCollectPosts_postTypeMatch()
    {
        global $get_posts, $get_post, $object_taxonomies, $posts, $the_terms, $wp_list_pluck;

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['arrayMergeWeighted'])
            ->getMock();

        $rp->expects($this->exactly(2))
            ->method('arrayMergeWeighted')
            ->willReturn([111, 222, 333]);

        $post = (object) [
            'ID' => '27',
            'post_type' => 'news',
        ];

        /**
         * Set up globals for mocking
         */
        $one = (object) ['ID' => 111, 'post_date' => '2024-05-31'];
        $two = (object) ['ID' => 222, 'post_date' => '2024-05-15'];
        $three = (object) ['ID' => 333, 'post_date' => '2024-05-01'];
        $get_post = [$one, $two, $three];
        $post_types = ['news', 'article'];
        $object_taxonomies = [];
        $get_posts = null;
        $posts = [111 => $one, 222 => $two, 333 => $three];

        $rp->collectPosts([
            'post' => $post,
            'post_types' => $post_types,
            'has_post_thumbnail' => false,
        ]);
    }

    public function testCollectPosts_debugLog()
    {
        global $error_log, $get_post, $object_taxonomies, $posts;
        $error_log = '';

        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['arrayMergeWeighted'])
            ->getMock();

        $rp->method('arrayMergeWeighted')->willReturn([111, 222, 333]);

        $rp->WP_DEBUG = true;

        $post = (object) [
            'ID' => '27',
            'post_type' => 'news',
        ];

        /**
         * Set up globals for mocking
         */
        $one = (object) ['ID' => 111, 'post_date' => '2024-05-31'];
        $two = (object) ['ID' => 222, 'post_date' => '2024-05-15'];
        $three = (object) ['ID' => 333, 'post_date' => '2024-05-01'];
        $get_post = [$one, $two, $three];
        $post_types = ['news', 'article'];
        $object_taxonomies = [];
        // $get_posts = null;
        $posts = [111 => $one, 222 => $two, 333 => $three];

        // $this->expectOutputRegex('/^RelatedContent/');
        $rp->collectPosts([
            'post' => $post,
            'post_types' => $post_types,
            'has_post_thumbnail' => false,
        ]);

        $this->assertStringContainsString('RelatedContent collected', $error_log);
        $this->assertStringContainsString('occurrences', $error_log);
        $this->assertStringContainsString(count($posts), $error_log);
    }

    // public function testCollectPosts_noPost()
    // {
    //     $rp = new RelatedPosts();
    //     $actual = $rp->collectPosts(null);
    //     $this->assertEmpty($actual);
    // }

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

    public function testGet()
    {
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['normalizeArgs', 'fetchPosts'])
            ->getMock();

        $mockArgs = ['mock' => 'args'];
        $rp->expects($this->exactly(3))
            ->method('normalizeArgs')
            ->willReturn($mockArgs, $mockArgs, false);

        $rp->expects($this->exactly(2))
            ->method('fetchPosts')
            ->with($this->arrayHasKey('mock'))
            ->willReturn([1, 2, 3, 4, 5, 6, 7, 8, 9]);

        $expected = 2;
        $actual = $rp->get($expected);
        $this->assertCount($expected, $actual);

        $expected = 4;
        $actual = $rp->get($expected);
        $this->assertCount($expected, $actual);

        $actual = $rp->get(25);
        $this->assertEmpty($actual);
    }

    public function testGet_integerArgs()
    {
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['normalizeArgs', 'fetchPosts'])
            ->getMock();

        $expected = 2;

        $rp->expects($this->exactly(1))
            ->method('normalizeArgs')
            ->willReturn(['mock' => 'args']);

        $rp->expects($this->exactly(1))
            ->method('fetchPosts')
            ->with($this->arrayHasKey('posts_per_page'))
            // ->with($this->arrayHasKey('posts_per_page', 2))
            // ->with($this->callback(fn($arg) => $this->assertArrayHasKey('posts_per_page', $arg)))
            ->willReturn([1, 2, 3, 4, 5, 6, 7, 8, 9]);

        $actual = $rp->get($expected);

        $this->assertCount($expected, $actual);
    }

    /**
     * REST function tests
     */

    public function testRegisterRestRoutes()
    {
        global $register_rest_route;
        $rp = $this->getMockBuilder(\IdeasOnPurpose\WP\RelatedPosts::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['normalizeArgs', 'fetchPosts'])
            ->getMock();

        $rp->registerRestRoutes();
        // d($register_rest_route[0]);
        $this->assertArrayHasKey('methods', $register_rest_route[0][2]);
        $this->assertArrayHasKey('callback', $register_rest_route[0][2]);
        $this->assertArrayHasKey('permission_callback', $register_rest_route[0][2]);
    }
}
