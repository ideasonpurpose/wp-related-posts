<?php

namespace IdeasOnPurpose\WP;

/**
 *
 * For the REST API, weights can be passed by prefixing any valid slug with weight_
 * eg. weight_topic=5&weight_color=3
 *
 * would be translated merged onto default or pre-defined weights as:
 *   [topic => 5, color=>3]
 *
 * The following alises are also reccognized, if both are specified, the last
 * one in the query will be used.
 *
 * weight_type = post_type
 * weight_post_type = post_type
 * weight_tag = tag
 * weight_post_tag = tag
 *
 *
 *
 * Create a new RelatedPosts object for each ID
 *
 * $related = new RelatedPosts(334);
 *
 * Get four posts, ranked by relatedness
 *
 * $related->get(4);
 *
 * transients should be handled transparently
 * Posts do not need to be collected in the constructor
 *
 * weights can be modified after instantiation
 *
 * But everything is derived from the initial Post, so that
 * should be unchangeable.
 *
 * BUT
 *
 * We can't init for the rest route, since there's no post in that
 * context
 *
 *
 *
 *
 *
 *
 * Two ways of initializing the Rest endpoint:
 * 1. Call the  rest setup action statically. Kind of ugly, hard to test and
 * different than how we usually do this.
 *
 * 2. Create a new object with no arguments. If there are any arguments passed to
 * the constructor, sip the REST initialization
 *
 *
 *
 *
 *
 *
 * TODO:
 *   - Maybe Use a list of types for post_type, invert this for omit_types?
 *    the post_type list? (might not be enough?)
 *
 * @package IdeasOnPurpose
 */
class RelatedPosts extends \WP_REST_Controller
{
    /**
     * Placeholders for mocking
     */
    public $ABSPATH;
    public $WP_DEBUG = false;

    /**
     * default weights, can be overridden in the constructor
     * Unknown keys default to 1
     */
    public $weights = [
        'post_tag' => 4,
        'category' => 3,
        'post_type' => 2,
    ];

    /**
     * Default args, these are the mimimum required to generate a set of
     * related posts, all arguments will me shallow-merged on top of these.
     * Arrays like weights and post_types will be replaced by merged args.
     */

    public $defaults = [];

    /**
     * Default number of related posts to return
     */
    public $posts_per_page = 3;

    /**
     * Omit post_types from fetched list of related posts.
     * Maps to the omit_types REST arg
     * Post_types to include in the set of related content
     *
     * Post_types to include in the set of relatedPosts
     */
    public $types = [];

    /**
     * REST API endpoint components, exposed for testing
     */
    // public $namespace;
    public $rest_base;
    public $rest_base_MINE;

    // protected $debug;

    /**
     * Used to abstract the global post vs. Posts passed in via $args.
     */
    public $post = null;

    //
    // public $posts;
    // public $debugExecutionTime;

    // public $debugAll;
    // public $debugSelected;

    public $randomizer;

    /**
     *
     * @param object $args [weights[], types[] ]
     * @return void
     */
    public function __construct($args = [])
    {
        $this->ABSPATH = defined('ABSPATH') ? ABSPATH : getcwd();
        $this->WP_DEBUG = defined('WP_DEBUG') && WP_DEBUG;

        /**
         * Used internally for shuffling arrays
         */
        $this->randomizer = new \Random\Randomizer();

        /**
         * Setup the REST route, namespace, route and endpoint base
         * are defined in the constructor so they can be mocked.
         */
        $this->namespace = 'ideasonpurpose/v1';
        $this->rest_base = 'related_posts';
        $this->rest_base_MINE = "{$this->namespace}/{$this->rest_base}";
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        $this->setDefaults();
        $this->normalizeArgs($args);
    }

    /**
     * Set default args. These are the minimum required to query for related posts
     * and all user input args will be merged on top of the baseline in $this->defaults
     * @return void
     */
    public function setDefaults()
    {
        global $post;
        $post_types = get_post_type() ? [get_post_type()] : [];
        $this->defaults = [
            'post' => $post,
            'post_types' => $post_types,
            'weights' => [
                'post_tag' => 4,
                'category' => 3,
                'post_type' => 2,
            ],
            'posts_per_page' => 3,
            'offset' => 0,
            'has_post_thumbnail' => false,
        ];
    }

    /*
     * Set default types from the global $post or $args['post'] if set
     *   $this->post will be a WP_Post object or null.
     *
     * @param array $args
     * @return void
     */
    public function initPost($args = [])
    {
        global $post;
        $this->post = array_key_exists('post', $args) ? $args['post'] : $post;
        $this->post = get_post($this->post); // returns a WP_Post or null
    }

    /**
     * $this->post should always be either a WP_Post object, or null
     *
     * If $args contains an array (or a string wrapped) return that unmodified
     */
    public function initTypes($args = []): void
    {
        if (array_key_exists('post_types', $args)) {
            $this->types = is_array($args['post_types'])
                ? $args['post_types']
                : [$args['post_types']];
        }

        if (!array_key_exists('post_types', $args) && $this->post) {
            $this->types = [$this->post->post_type];
        }
    }

    /**
     * Register REST routes to return Related Posts
     */
    public function registerRestRoutes()
    {
        register_rest_route($this->namespace, "/{$this->rest_base}/(?P<id>[0-9]+)", [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'restResponse'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function restResponse(\WP_REST_Request $req)
    {
        $id = $req->get_param('id');
        $count = $req->get_param('count') ?? $this->count;

        $weights = [];
        foreach ($req->get_params() as $param => $val) {
            $weight = preg_split('/_/', strtolower($param), 2, PREG_SPLIT_NO_EMPTY);

            if ($weight[0] === 'weight' && is_numeric($val)) {
                $key = preg_replace('/^post_/', '', $weight[1]);
                // clamp weights between 0 and 7
                $min = 0;
                $max = 7;
                $weights[$key] = max($min, min($max, (int) $val));
            }

            $this->weights = array_merge($this->weights, $weights);
        }

        global $post;

        $posts = $this->get($count, 0, $id);

        $ids = wp_list_pluck($posts, 'ID');

        $post_types = array_unique(wp_list_pluck($posts, 'post_type'));

        $controllers = [];
        // why not use $this->types?
        foreach ($post_types as $post_type) {
            $controllers[$post_type] = new \WP_REST_Posts_Controller($post_type);
        }

        if (empty($posts)) {
            /**
             * TODO: Does this ever happen?
             * $posts will always be an array
             * TODO: Needs to return an error
             */
            return rest_ensure_response($posts);
        }

        $data = [];

        // TODO: This translation to REST should happen separately, (if at all?)
        //       For regular usage, we can save some ticks and just work with native
        //       WP_Post objects
        foreach ($posts as $post) {
            $response = $controllers[$post->post_type]->prepare_item_for_response(
                $post,
                new \WP_REST_Request()
                // $controllers[$post->post_type]->get_collection_params()
            );
            // $post_controller = new \WP_REST_Posts_Controller($post->post_type);

            // $response = $this->prepare_item_for_response( $post, $request );
            // $response = rest_ensure_response($post);

            // $response = (new \WP_REST_Response($post))->get_data();
            // $response = $post_controller->prepare_item_for_response($post, []);
            // $data[] = $post_controller->prepare_response_for_collection(rest_ensure_response($data));
            $data[] = $this->prepare_response_for_collection($response);
        }

        $res = [
            'id' => $id,
            'posts' => $posts,
            'params' => $req->get_params(),
            'weights' => $this->weights,
        ];

        return rest_ensure_response($posts);

        $err = new \WP_Error(404, 'bad ID');
        return rest_ensure_response($err);
    }

    public function prepare_item_for_response($post, $request)
    {
        $post_data = [];

        $schema = $this->get_item_schema($request);

        // We are also renaming the fields to more understandable names.
        if (isset($schema['properties']['id'])) {
            $post_data['id'] = (int) $post->ID;
        }

        if (isset($schema['properties']['content'])) {
            $post_data['content'] = apply_filters('the_content', $post->post_content, $post);
        }

        return rest_ensure_response($post_data);
    }

    /// :TODO: Need to merge input Args on top of the minimul default args
    //         Weights and post_types should be replaced, not deep-merged

    /**
     * Transforms an $args array to ensure valid properties.
     * @return array
     */
    public function normalizeArgs($rawArgs)
    {
        global $post;

        $args = is_array($rawArgs) ? $rawArgs : $this->defaults;
        $cleanArgs = [];
        /**
         * Ensure a $post is set and is a valid WP_POST object
         */
        $cleanArgs['post'] = array_key_exists('post', $args) ? $args['post'] : $post;
        $cleanArgs['post'] = get_post($cleanArgs['post']); // returns a WP_Post or null

        if (!$cleanArgs['post']) {
            $latest_post = get_posts(['post_type' => 'any', 'posts_per_page' => 1]);
            $cleanArgs['post'] = array_pop($latest_post);
        }

        if (
            get_post_type($cleanArgs['post']) === 'revision' &&
            $cleanArgs['post']->post_parent !== 0
        ) {
            $cleanArgs['post'] = get_post($cleanArgs['post']->post_parent);
        }

        if (!$cleanArgs['post']) {
            return false;
        }

        /**
         * Ensure weights is an array of integers
         */
        if (array_key_exists('weights', $args) && is_array($args['weights'])) {
            foreach ($args['weights'] as $slug => $val) {
                if (is_numeric($val)) {
                    $cleanArgs['weights'][$slug] = (int) $val;
                }
            }
        }

        /**
         * Ensure post_types is a unique array of strings
         *
         * TODO: This can not be empty, should not include 'attachment'
         */
        if (array_key_exists('post_types', $args) && is_array($args['post_types'])) {
            $cleanArgs['post_types'] = [];
            foreach ($args['post_types'] as $post_type) {
                if (is_string($post_type) && $post_type !== 'attachment') {
                    $cleanArgs['post_types'][] = $post_type;
                }
            }
            $cleanArgs['post_types'] = array_unique($cleanArgs['post_types']);
            if (empty($cleanArgs['post_types'])) {
                $cleanArgs['post_types'] = [get_post_type($post)];
            }
        }

        if (array_key_exists('has_post_thumbnail', $args)) {
            $cleanArgs['has_post_thumbnail'] = (bool) $args['has_post_thumbnail'];
        }

        if (array_key_exists('offset', $args)) {
            if (is_numeric($args['offset'])) {
                $cleanArgs['offset'] = (int) $args['offset'];
            }
        }

        if (array_key_exists('posts_per_page', $args)) {
            if (is_numeric($args['posts_per_page'])) {
                $min = 1;
                $max = 20;
                $val = (int) $args['posts_per_page'];
                $cleanArgs['posts_per_page'] = max($min, min($val, $max));
            }
        }

        $cleanArgs = array_merge($this->defaults, $cleanArgs);
        return $cleanArgs;
    }

    /**
     * Validate and merge arguments onto defaults
     *
     * non-integer weights will be ignored
     *
     */
    // public function validateAndMergeArgs($args)
    // {
    //     if (!is_array($args)) {
    //         return;
    //     }

    //     /**
    //      * Ensure weights and types are arrays?
    //      */
    //     if (array_key_exists('weights', $args) && is_array($args['weights'])) {
    //         $integerWeights = [];
    //         foreach ($args['weights'] as $slug => $val) {
    //             if (is_numeric($val)) {
    //                 $integerWeights[$slug] = (int) $val;
    //             }
    //         }
    //         $this->weights = array_merge($this->weights, $integerWeights);
    //     }

    //     // if (array_key_exists('omit-types', $args) && is_array($args['omit-types'])) {
    //     //     $this->omitTypes = array_merge($this->omitTypes, $args['omit-types']);
    //     // }

    //     if (array_key_exists('types', $args) && is_array($args['types'])) {
    //         $this->types = array_unique(array_merge($this->types, $args['types']));
    //     }

    //     /**
    //      * Ensure weights and types are arrays
    //      */

    //     // try {
    //     //     //code...
    //     //     $this->weights = array_merge($this->weights, $args['weights'] ?? []);
    //     // } catch (\Throwable $th) {
    //     //     //throw $th;
    //     // }

    //     // try {
    //     //     $this->types = array_merge($this->types, $args['types'] ?? []);
    //     // } catch (\Throwable $th) {

    //     // }

    //     // sort weights so the resulting hash is idempotent (will be hashed with $post->guid for the transient ID)
    //     ksort($this->weights);
    //     $this->weights = $this->clampWeights($this->weights);

    //     // d($this);
    // }

    /**
     * Returns a copy of the input $weights array with all values clamped between $min and $max
     * @param array $weights
     * @param int $min
     * @param int $max
     * @return array
     */
    public function clampWeights($weights, $min = 0, $max = 7)
    {
        $clamped = [];
        foreach ($weights as $key => $value) {
            $clamped[$key] = max($min, min((int) $value, $max));
        }
        return $clamped;
    }

    public function getTransientName($cleanArgs)
    {
        $basis = [
            'id' => $cleanArgs['post']->ID,
            'weights' => $cleanArgs['weights'],
            'post_types' => $cleanArgs['post_types'],
        ];

        return 'related_posts_' . md5(json_encode($basis));
    }

    /**
     * Gather and rank related posts for a given WP_POST based on the post
     * used to initialize RelatedPosts
     *
     * Store the result in a transient derived from the Post ID and weightings
     * @return array Array of related WP_Post objects
     */
    public function fetchPosts($cleanArgs): array
    {
        /*
         * store posts in an 8-hour transient to conserve queries and to prevent
         * spiders from thinking the page is changing too often.
         */
        $transientName = $this->getTransientName($cleanArgs);
        $posts = $this->WP_DEBUG ? false : get_transient($transientName);
        if ($posts === false) {
            $posts = $this->collectPosts($cleanArgs);
            // TODO: Make transient duration configurable?
            set_transient($transientName, $posts, 8 * HOUR_IN_SECONDS);
        }
        return $posts;
    }

    /**
     * Run a bunch of queries to collect related posts, then rank posts by frequency
     *
     * Queries:
     *     Same type
     *     matching tags
     *     last 12 of same type
     *     last 12 of all types
     *
     * TODO: Make that '12' number configurable
     *
     * @param array $args options container, may include a post (WP_POST) and weights
     */
    public function collectPosts(array $cleanArgs): array
    {
        /**
         * This will hold an array of post IDs which will be added multiple times
         * after being merged in with arrayMergeWeighted().
         */
        $postBucket = [];

        /**
         * Get all public post_types, then filter out pages and attachments
         */
        // $post_types = array_filter($cleanArgs['post_types'], fn($t) => $t !== 'attachment');
        $post_types = $cleanArgs['post_types'];

        $thumb_query = ['key' => '_thumbnail_id', 'compare' => 'EXISTS'];
        $meta_query = $cleanArgs['has_post_thumbnail'] ? [$thumb_query] : [];

        $post = $cleanArgs['post'];
        $taxonomies = get_object_taxonomies($post->post_type, 'objects');

        /**
         * Collect posts with the same terms across all of this object's taxonomies
         * TODO: Filter taxonomies that are not weighted?
         */
        foreach ($taxonomies as $slug => $tax) {
            $terms = get_the_terms($post->ID, $slug);
            if ($terms) {
                foreach ($terms as $term) {
                    $posts = get_posts([
                        'post__not_in' => [$post->ID],
                        'posts_per_page' => 12,
                        'orderby' => ['date' => 'DESC'],
                        'post_status' => 'publish',
                        'post_type' => $post_types,
                        'tax_query' => [
                            [
                                'taxonomy' => $slug,
                                'field' => 'slug',
                                'terms' => [$term->slug],
                            ],
                        ],
                        'meta_query' => $meta_query,
                    ]);
                    $ids = wp_list_pluck($posts, 'ID');
                    $postBucket = $this->arrayMergeWeighted($postBucket, $ids, $slug);
                }
            }
        }

        /**
         * Collect the most recent posts of the same post_type
         */
        $posts = get_posts([
            'post_type' => $post->post_type,
            'post__not_in' => [$post->ID],
            'posts_per_page' => 12,
            'orderby' => ['date' => 'DESC'],
            'meta_query' => $meta_query,
        ]);
        $ids = wp_list_pluck($posts, 'ID');
        $postBucket = $this->arrayMergeWeighted($postBucket, $ids, 'post_type');

        /**
         * Get 12 most recent posts in $post_types
         */
        $posts = get_posts([
            'post_type' => $post_types,
            'post__not_in' => [$post->ID],
            'posts_per_page' => 12,
            'post_status' => 'publish',
            'orderby' => ['date' => 'DESC'],
            'meta_query' => $meta_query,
        ]);
        $ids = wp_list_pluck($posts, 'ID');
        $postBucket = $this->arrayMergeWeighted($postBucket, $ids, 'date');

        /**
         * Create a lookup table array with IDs and occurrence counts.
         */
        $counts = array_count_values($postBucket);

        // TODO: Extract total number of unique collected IDs here for debugging

        $rankedPosts = [];
        foreach ($counts as $key => $count) {
            $thePost = get_post($key);
            $rankedPosts[$key] = [
                'count' => $count,
                'post_date' => $thePost->post_date,
                'post' => $thePost,
            ];
        }

        /**
         * Sort rankedPosts by count DESC, then date DESC
         */
        uasort($rankedPosts, function ($a, $b) {
            $cmp = $b['count'] - $a['count'];
            if ($cmp === 0) {
                $cmp = strcmp($b['post_date'], $a['post_date']);
            }
            return $cmp;
        });

        return array_map(function ($n) {
            return $n['post'];
        }, $rankedPosts);
    }

    /**
     * fetches a weight integer from $this->weights
     * unknown keys default to 1
     * tags is translated to post_tag
     * @param  string $slug the taxonomy or type of query to weight
     * @return integer       an integer weight
     */
    public function getWeight($slug)
    {
        // remap 'post_tag' to 'tag'
        // if ($slug === 'post_tag') {
        //     $slug = 'tag';
        // }
        // remap 'tag' to 'post_tag'
        if ($slug === 'tag') {
            $slug = 'post_tag';
        }

        return $this->weights[$slug] ?? 1;

        // $weight = 1;
        // if (array_key_exists($slug, $this->weights)) {
        //     $weight = $this->weights[$slug];
        // }
        // return $weight;
    }

    /**
     * wrapper for array_merge which uses weights from $this->getWeight
     * @param  array $base      array to merge into
     * @param  array $merge     array to merge
     * @param  string $slug     taxonomy or type of query to weight
     * @return array            the merged array
     */
    public function arrayMergeWeighted($base, $merge, $slug)
    {
        $output = $base;
        $weight = $this->getWeight($slug);
        for ($i = 0; $i < $weight; $i++) {
            $output = array_merge($output, $merge);
        }
        return $output;
    }

    /**
     * Get related posts
     * @param  integer $id  a post ID
     * @param  array $args  number of related posts to return
     * @return array       Returns an array of posts, or an empty array
     */
    public function get($args = [])
    {
        $cleanArgs = $this->normalizeArgs($args);

        if (!$cleanArgs) {
            /**
             * If args can't be parsed, normalizeArgs will return false
             */
            return [];
        }
        /**
         * Handle legacy first-arg is a number, apply back to default $cleanArgs
         */
        if (is_numeric($args)) {
            $cleanArgs['posts_per_page'] = $args;
        }

        $offset = $cleanArgs['offset'] ?? 0;
        $per_page = $cleanArgs['posts_per_page'] ?? $this->posts_per_page;
        $posts = array_slice($this->fetchPosts($cleanArgs), $offset, $per_page);

        return $posts;
    }
}

/**
 * Reference links
 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 */
