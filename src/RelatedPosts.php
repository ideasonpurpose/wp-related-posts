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
        // 'tag' => 4,
        // 'category' => 3,
        // 'post_type' => 4,
    ];

    /**
     * Default number of related posts to return
     */
    public $count = 3;

    /**
     * Omit post_types from fetched list of related posts.
     * Maps to the omit_types REST arg
     * Post_types to include in the set of related content
     */
    public $types = [];

    /**
     * REST API endpoint components, exposed for testing
     */
    // public $namespace;
    public $rest_base;
    public $rest_base_MINE;

    // protected $debug;

    // public $post;

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

        $this->mergeArgs($args);

        /**
         * Merge any args onto the defaults
         */

        // return;
        // global $post;

        // $this->post = array_key_exists('post', $args) ? $args['post'] : $post;
        // $this->post = is_integer($this->post) ? get_post($this->post) : $this->post;

        // if (array_key_exists('weights', $args) && is_array($args['weights'])) {
        //     $this->weights = array_merge($this->weights, $args['weights']);
        // }

        // if (array_key_exists('omit-types', $args) && is_array($args['omit-types'])) {
        //     $this->omitTypes = array_merge($this->omitTypes, $args['omit-types']);
        // }

        // /*
        //  * store posts in an 8-hour transient to conserve queries and to prevent
        //  * spiders from thinking the page is changing too often.
        //  *
        //  * // TODO pre-sort weights so the resulting hash is idempotent
        //  */
        // $guid = md5($this->post->guid . json_encode($this->weights));
        // $this->posts = WP_DEBUG ? false : get_transient($guid);
        // if ($this->posts === false) {
        //     // $startTime = microtime(true);
        //     $this->_init();
        //     // $this->debugExecutionTime = microtime(true) - $startTime;
        //     set_transient($guid, $this->posts, 8 * HOUR_IN_SECONDS);
        // }
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

    /**
     * Validate and merge arguments onto defaults
     */
    public function mergeArgs($args)
    {
        /**
         * Ensure weights and types are arrays?
         */
        if (array_key_exists('weights', $args) && is_array($args['weights'])) {
            $this->weights = array_merge($this->weights, $args['weights']);
        }

        // if (array_key_exists('omit-types', $args) && is_array($args['omit-types'])) {
        //     $this->omitTypes = array_merge($this->omitTypes, $args['omit-types']);
        // }

        if (array_key_exists('types', $args) && is_array($args['types'])) {
            $this->types = array_merge($this->types, $args['types']);
        }

        $this->weights = array_merge($this->weights, $args['weights'] ?? []);
        $this->types = array_merge($this->types, $args['types'] ?? []);

        // sort weights so the resulting hash is idempotent (hased for the transient ID)
        ksort($this->weights);

        // d($this);
    }

    /**
     * @return array Returns an array
     */

    /**
     *   Gather and rank related posts for a given ID
     * Store the result in a transient
     * @param WP_Post $post
     * @return array Array of related WP_Post objects
     */
    public function fetchPosts($post)
    {
        /*
         * store posts in an 8-hour transient to conserve queries and to prevent
         * spiders from thinking the page is changing too often.
         */
        if (!$post) {
            return [];
        }
        $transientName = md5($post->guid . json_encode($this->weights));
        $posts = $this->WP_DEBUG ? false : get_transient($transientName);
        if ($posts === false) {
            $posts = $this->collectPosts($post);
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
     * @param array $args options container, may include a post (WP_POST) and weights
     */
    public function collectPosts($post)
    {
        $postBucket = [];
        if (!$post) {
            return [];
        }
        $post_types = array_filter(
            get_post_types(['public' => true, 'exclude_from_search' => false]),
            fn($k) => !in_array($k, ['page', 'attachment']),
            ARRAY_FILTER_USE_KEY
        );

        $taxonomies = get_object_taxonomies($post->post_type, 'objects');
        d($post_types, $taxonomies, $this->weights);
        foreach ($taxonomies as $slug => $tax) {
            $terms = get_the_terms($post->ID, $slug);
            d($slug, $tax, $terms, $post_types);
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
                    ]);
                    d($posts);
                    $postBucket = $this->_arrayMergeWeighted($postBucket, $posts, $slug);
                }
            }
        }
        // $types = wp_list_pluck($postBucket, 'post_type');
        // d($types);
        // Get 12 most recent posts of the same type
        // UNNECESSARY WHEN WE'RE ALREADY QUERYING POST_TYPES
        // $posts = get_posts([
        //     'post_type' => $post->post_type,
        //     'post__not_in' => [$post->ID],
        //     'posts_per_page' => 12,
        //     'orderby' => ['date' => 'DESC'],
        // ]);
        // $postBucket = $this->_arrayMergeWeighted($postBucket, $posts, 'type');

        // Get 12 most recent posts of all types
        // $posts = get_posts([
        //     'post_type' => 'any',
        //     'post__not_in' => [$post->ID],
        //     'posts_per_page' => 12,
        //     'post_status' => 'publish',
        //     'orderby' => ['date' => 'DESC'],
        // ]);
        // $postBucket = $this->_arrayMergeWeighted($postBucket, $posts, 'date');

        //     d($postBucket, $ids
        // );

        $rankedPosts = [];
        $ids = array_map(fn($n) => $n->ID, $postBucket);
        $counts = array_count_values($ids);

        d($ids, $counts);
        foreach ($counts as $key => $count) {
            $thePost = get_post($key);
            $rankedPosts[$key] = [
                'count' => $count,
                'date' => $thePost->post_date,
                'post' => $thePost,
            ];
        }

        // sort by count DESC, then by date DESC
        uasort($rankedPosts, function ($a, $b) {
            $cmp = $b['count'] - $a['count'];
            if ($cmp === 0) {
                $cmp = strcmp($b['date'], $a['date']);
            }
            return $cmp;
        });

        $rankedPosts = array_filter($rankedPosts, function ($p) {
            return !in_array($p['post']->post_type, $this->types);
        });

        d($rankedPosts);
        return array_map(function ($n) {
            return $n['post'];
        }, $rankedPosts);

        // TODO: Do these
        // $this->_filterPostsWithoutImages();
        // $this->_filterPostTypes();
    }

    /**
     * fetches a weight integer from $this->weights
     * unknown keys default to 1
     * @param  string $slug the taxonomy or type of query to weight
     * @return integer       an integer weight
     */
    private function _getWeight($slug)
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
     * wrapper for array_merge which uses weights from $this->_getWeight
     * @param  array $base      array to merge into
     * @param  array $merge     array to merge
     * @param  string $slug     taxonomy or type of query to weight
     * @return array            the merged array
     */
    private function _arrayMergeWeighted($base, $merge, $slug)
    {
        $output = $base;
        $weight = $this->_getWeight($slug);
        for ($i = 0; $i < $weight; $i++) {
            $output = array_merge($output, $merge);
        }
        return $output;
    }

    // TODO: Waste of space, inline these
    private function _filterPostsWithoutImages()
    {
        $this->posts = array_filter($this->posts, 'has_post_thumbnail');
    }

    private function _filterPostTypes()
    {
        $this->posts = array_filter($this->posts, function ($p) {
            return !in_array($p->post_type, $this->types);
        });
    }

    /**
     * Get related posts
     * @param  integer $id  a post ID
     * @param  integer $count  number of related posts to return
     * @param  integer $offset offset
     * @return [type]          [description]
     */
    public function get($count = 3, $offset = 0, $id = 0)
    {
        global $post;

        $the_post = get_post($id) ?? ($post ?? []);

        // d($count, $offset, $id, $post, $the_post);

        if (!$the_post) {
            return new \WP_Error('rest_post_invalid_id', 'Invalid post ID.', ['status' => 404]);
        }

        $posts = array_slice($this->fetchPosts($the_post), $offset, $count);

        // d($posts);
        $ids = wp_list_pluck($posts, 'ID');
        $post_types = array_unique(wp_list_pluck($posts, 'post_type'));
        // THIS MIGHT NOT BE NECESSARY? DOES IT WORK FROM JUST THE post POST_TYPE?

        $controllers = [];
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

        // d($data);
        return $data;

        // return $relQuery->posts;

        // return array_slice($this->fetchPosts($the_post), $offset, $count);
        // }
        // return [];
    }

    /**
     * Get related posts matching the given type
     * @param  string  $type  Slug of the type to get
     * @param  integer $count number of related posts to return
     * @return array         an array of posts
     */
    public function getType($type, $count = 3, $offset = 0)
    {
        $posts = array_filter($this->posts, function ($n) use ($type) {
            return $n->post_type === $type;
        });

        /**
         * If the list of post_type-filtered posts is less than $count,
         * append a shuffled array of all selected posts from the 5th index
         */
        if (count($posts) < $count) {
            $extra_posts = $this->randomizer->shuffleArray(array_slice($this->posts, 5));
            $posts = array_merge($posts, $extra_posts);
        }
        return array_slice($posts, $offset, $count);
    }

    /**
     * Get related posts excluding the given type
     * @param  string  $type  Slug of the type NOT to get
     * @param  integer $count number of related posts to return
     * @return array         an array of posts
     */
    public function getNotType($type, $count = 3, $offset = 0)
    {
        $posts = array_filter($this->posts, function ($n) use ($type) {
            return $n->post_type !== $type;
        });
        /**
         * If the filtered list of posts is less than $count, append
         * a shuffled array of the last 20 selected posts
         */
        if (count($posts) < $count) {
            $extra_posts = $this->randomizer->shuffleArray(array_slice($this->posts, -20));
            $posts = array_merge($posts, $extra_posts);
        }
        return array_slice($posts, $offset, $count);
    }
}

/**
 * Reference links
 * @link https://developer.wordpress.org/rest-api/extending-the-rest-api/controller-classes/
 */
