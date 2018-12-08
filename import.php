<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
ini_set('display_errors', 'On');

header('Content-Type: text/plain');


// 2 function taken from HashOver-Next and modifed to be functions instead of methods
function reduceDashes ($name)
{
	// Remove multiple dashes
	if (mb_strpos ($name, '--') !== false) {
		$name = preg_replace ('/-{2,}/', '-', $name);
	}

	// Remove leading and trailing dashes
	$name = trim ($name, '-');

	return $name;
}

function getSafeThreadName ($name)
{
    $dashFromThreads = array (
		'<', '>', ':', '"', '/', '\\', '|', '?',
		'&', '!', '*', '.', '=', '_', '+', ' '
	);
	// Replace reserved characters with dashes
	$name = str_replace ($dashFromThreads, '-', $name);

	// Remove multiple/leading/trailing dashes
	$name = reduceDashes ($name);

	return $name;
}

function save($fname, $obj) {
    $f = fopen($fname, 'w');
    fwrite($f, json_encode($obj, JSON_PRETTY_PRINT));
    fclose($f);
}

require('disqus-php/disqusapi/disqusapi.php');
$disqus = new DisqusAPI('<API KEY>');

function fetch($options, $fn, $cursor = NULL) {
    if ($cursor != NULL) {
        $payload = array_merge($options, array('cursor' => $cursor));
    } else {
        $payload = $options;
    }
    $res = $fn($payload);
    $posts = $res->response;
    if ($res->cursor->hasNext) {
        $posts = array_merge($posts, fetch($options, $fn, $res->cursor->next));
    }
    return $posts;
}

$opts = array('forum' => 'gjavascript');
// use limit: 100 max option if you have lots of comments

if (!file_exists('posts.json')) {
    save('posts.json', fetch($opts, function($payload) use ($disqus) {
        return $disqus->posts->list($payload);
    }));
}
if (!file_exists('threads.json')) {
    save('threads.json', fetch($opts, function($payload) use ($disqus) {
        return $disqus->threads->list($payload);
    }));
}

function post_name($posts, $post, $name) {
    if (!is_array($name)) {
        $name = array($name);
    }
    if ($post['parent'] == null) {
        return $name;
    }
    $i = 1;
    foreach ($posts as $p) {
        if ($p['id'] == $post['parent']) {
            $name[] = $i;
            return post_name($posts, $p, $name);
        }
    }
    throw new Exception("Post parent not found");
}

$threads = json_decode(file_get_contents('threads.json'), true);
$posts = json_decode(file_get_contents('posts.json'), true);
// -------------------------------------------------------------------------------------------------
function addChild($xml, $node, $name, $value) {
    $element = $xml->createElement($name);
    $element->appendChild($xml->createTextNode($value));
    $node->appendChild($element);
    return $element;
}
// -------------------------------------------------------------------------------------------------
foreach ($threads as $thread) {
    $dir_name = getSafeThreadName(preg_replace("%^https?://[^/]+%", "", $thread['link']));
    $dir = 'threads/' . $dir_name;
    // this could be optimized to first check if there are comments for thread
    // before creating directory
    if (!is_dir($dir)) {
        mkdir($dir);
        chmod($dir, 0775);
    }
    // all echo are just for debug purpose
    echo str_repeat('-', 80) . "\n";
    echo ":: " . $thread['clean_title'] . "\n";
    echo str_repeat('-', 80) . "\n";
    foreach ($posts as $post) {
        $post['children'] = array_filter($posts, function($p) use ($post) {
            return $p['parent'] = $post['id'];
        });
    }
    $root = array_filter($posts, function($post) {
        return $post['parent'] == NULL;
    });
    $i = 1;
    $thread_posts = array_filter($posts, function($post) use ($thread) {
        return $post['thread'] == $thread['id'];
    });
    $refs = array(
        'date' => 'createdAt',
        'name' => 'author.username',
        'avatar' => 'author.avatar.permalink',
        'website' => 'author.url'
    );
    foreach($thread_posts as $post) {
        $xml = new DomDocument('1.0');
        $xml->preserveWhiteSpace = false;
        $xml->formatOutput = true;

        $root = $xml->createElement('comment');
        $root = $xml->appendChild($root);
        $body = preg_replace_callback("%<(a[^>]+)>(.*?)</a>%", function($match) {
            return (preg_match("/data-dsq-mention/", $match[1]) ? "@" : "") . $match[2];
        }, $post['message']);
        addChild($xml, $root, 'body', $body);
        foreach ($refs as $key => $value) {
            $parts = explode('.', $value);
            $ref = $post;
            foreach ($parts as $part) {
                $ref = $ref[$part];
            }
            addChild($xml, $root, $key, $ref);
        }
        $name_arr = post_name($thread_posts, $post, $i);
        $fname = $dir . '/' . implode('-', $name_arr) . ".xml";
        $f = fopen($fname, 'w');
        echo $xml->saveXML() . "\n";
        fwrite($f, $xml->saveXML());
        fclose($f);
        chmod($fname, 0664);
        $n = count($name_arr);
        echo str_repeat(" ", 4*$n) . implode('-', $name_arr) . "\n";
        echo str_repeat(" ", 4*$n) . preg_replace("/\n/", "\n" . str_repeat(" ", 4*$n),
                                                  $post['message']) . "\n";
        if ($post['parent'] == null) {
            $i += 1;
        }
    }
}




?>
