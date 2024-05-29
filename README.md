# WordPress Related Posts

A library for selecting related content across post_types based on a set of specified weightings

## How it works

Initialize a new `RelatedPosts` object with an optional list of weights, types and a post to use as the basis for selecting related content. If no post is specified, the global `$post` will be used. If no types are specified, the `post_type` of the global `$post` will be used. 

`post`, `post_type` and `weights` can all also be overridden when requesting Related Posts. 

Related Posts queries will be stored in a transient based on the post ID and defined types and weights. 


### Examples

```php
$rp = new RelatedPosts();

// get related posts based only on category
$rp->get(4, ['weights' => ['post_tag' => 12]]);

// get related posts from two post_types (no assurance of getting one of each)
$rp->get(3, ['post_types' => ['feature', 'help']]);

// get related posts for a different post
$rp->get(2, ['post'=> 334]);
```

Note: When assigning weights, `tag` is aliased to `post_tag`, both will work interchangeably.

### Defaults

If the library is initialized with no arguments, the following defaults will be used.

```php
// Initializing without arguments
$RelatedPosts = new RelatedPosts();

// equivalent to this:
$RelatedPosts = new RelatedPosts([
    'post' => get_theID(),
    'post_types' => [get_post_type()],
    'weights' => [
        'post_tag' => 4,
        'category' => 3,
        'post_type' => 2,
    ]    
]);
```

## Additional Options



### `has_post_thumbnail`
Related Posts can be limited to only posts with assigned featured images. Set the `has_post_thumbnail` property to `true` to filter out posts without a featured image:

```php
$rp = new RelatedPosts(['has_post_thumbnail' => true]);
```

### `posts_per_page
Set the default for how many posts to retrieve with RelatedPosts::get().


### `offset`

use `offset` to return a subset of posts somewhere after the first `$count` posts. 