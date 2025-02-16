<?php namespace x\archive;

function route__archive($content, $path, $query, $hash) {
    if (null !== $content) {
        return $content;
    }
    \extract(\lot(), \EXTR_SKIP);
    $name = \State::get('[x].query.archive') ?? "";
    $path = \trim($path ?? "", '/');
    $route = \trim($state->x->archive->route ?? 'archive', '/');
    if ($part = \x\page\n($path)) {
        $path = \substr($path, 0, -\strlen('/' . $part));
    }
    $part = ((int) ($part ?? 0)) - 1;
    $folder = \LOT . \D . 'page' . \D . $path;
    if ($file = \exist([
        $folder . '.archive',
        $folder . '.page'
    ], 1)) {
        $page = new \Page($file);
    }
    $chunk = $page->chunk ?? 5;
    $deep = $page->deep ?? 0;
    $sort = [-1, 'time']; // Force page sort by the `time` data
    if ($pages = $page->children('page', $deep)) {
        $pages = $pages->sort($sort);
    } else {
        $pages = new \Pages;
    }
    \State::set([
        'chunk' => $chunk,
        'count' => $count = $pages->count, // Total number of page(s) before chunk
        'deep' => $deep,
        'part' => $part + 1,
        'sort' => $sort
    ]);
    if ($count > 0) {
        $pages = $pages->is(function ($v) use ($name) {
            $time = $v->time . "";
            return 0 === \strpos(\strtr($time, [
                ':' => '-',
                ' ' => '-'
            ]) . '-', $name . '-');
        });
        $pager = \Pager::from($pages);
        $pager->path = $path . '/' . $route . '/' . $name;
        $pager = $pager->chunk($chunk, $part);
        $pages = $pages->chunk($chunk, $part);
        if (0 === ($count = $pages->count)) { // Total number of page(s) after chunk
            // Greater than the maximum part or less than `1`, abort!
            \State::set([
                'has' => [
                    'next' => false,
                    'parent' => false,
                    'prev' => false
                ],
                'is' => [
                    'error' => 404,
                    'page' => true,
                    'pages' => false
                ]
            ]);
            \lot('t')[] = \i('Error');
            return ['page/archive/' . $name, [], 404];
        }
        \State::set([
            'is' => [
                'archive' => false, // Never be `true`
                'archives' => true,
                'error' => false,
                'page' => false,
                'pages' => true
            ],
            'has' => [
                'page' => true,
                'pages' => $count > 0,
                'parent' => true
            ]
        ]);
        \lot('t')[] = \i('Archive');
        $a = \explode('-', $name);
        if (!isset($a[1])) {
            \lot('t')[] = $a[0];
        } else {
            \lot('t')[] = (new \Time($a[0] . '-' . $a[1] . '-01 00:00:00'))('%B %Y');
        }
        \lot('page', $page);
        \lot('pager', $pager);
        \lot('pages', $pages);
        \State::set('has', [
            'next' => !!$pager->next,
            'parent' => !!$pager->parent,
            'part' => $part >= 0,
            'prev' => !!$pager->prev
        ]);
        return ['pages/archive/' . $name, [], 200];
    }
}

function route__page($content, $path, $query, $hash) {
    if (null !== $content) {
        return $content;
    }
    \extract(\lot(), \EXTR_SKIP);
    if (!$part = \x\page\n($path = \trim($path ?? "", '/'))) {
        return $content;
    }
    $path = \substr($path, 0, -\strlen('/' . $part));
    $route = \trim($state->x->archive->route ?? 'archive', '/');
    // Return the route value to the native page route and move the archive route parameter to state
    if ($path) {
        $a = \explode('/', $path);
        $name = \array_pop($a);
        $r = \array_pop($a);
        if ($r !== $route) {
            return $content;
        }
        \State::set('[x].query.archive', $name);
        return \Hook::fire('route.archive', [$content, \implode('/', $a) . '/' . $part, $query, $hash]);
    }
    return $content;
}

$chops = \explode('/', $url->path ?? "");
$part = \array_pop($chops);
$archive = \array_pop($chops);
$route = \array_pop($chops);

// Initialize response variable(s)
\lot('archive', new \Time);

if ($archive && $route === \trim($state->x->archive->route ?? 'archive', '/')) {
    $a = \explode('-', $archive);
    // Year
    if (\count($a) < 7 && ($a[0] = (int) $a[0]) > 1969) {
        // Month
        if (!isset($a[1]) || ($a[1] = (int) $a[1]) > 0 && $a[1] < 13) {
            // Day
            if (!isset($a[2]) || ($a[2] = (int) $a[2]) > 0 && $a[2] < 32) {
                // Hour
                if (!isset($a[3]) || ($a[3] = (int) $a[3]) > 0 && $a[3] < 25) {
                    // Minute
                    if (!isset($a[4]) || ($a[4] = (int) $a[4]) > 0 && $a[4] < 61) {
                        // Second
                        if (!isset($a[5]) || ($a[5] = (int) $a[5]) > 0 && $a[5] < 61) {
                            $archive = \substr_replace('1970-01-01-00-00-00', $archive, 0, \strlen($archive));
                            \lot('archive', new \Time($archive));
                            \Hook::set('route.archive', __NAMESPACE__ . "\\route__archive", 100);
                            \Hook::set('route.page', __NAMESPACE__ . "\\route__page", 90);
                        }
                    }
                }
            }
        }
    }
}