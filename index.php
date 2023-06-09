<?php namespace x\archive;

function route__archive($content, $path, $query, $hash) {
    if (null !== $content) {
        return $content;
    }
    \extract($GLOBALS, \EXTR_SKIP);
    $name = \From::query($query)['name'] ?? "";
    if ($path && \preg_match('/^(.*?)\/([1-9]\d*)$/', $path, $m)) {
        [$any, $path, $part] = $m;
    }
    $part = ((int) ($part ?? 1)) - 1;
    $path = \trim($path ?? "", '/');
    $route = \trim($state->x->archive->route ?? 'archive', '/');
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
    $pages = \Pages::from($folder, 'page', $deep)->sort($sort);
    \State::set([
        'chunk' => $chunk,
        'count' => $count = $pages->count, // Total number of page(s)
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
        $count = $pages->count; // Total number of page(s) after chunk
        if (0 === $count) {
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
            $GLOBALS['t'][] = \i('Error');
            return ['page', [], 404];
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
        $GLOBALS['t'][] = \i('Archive');
        $t = \explode('-', $name);
        if (!isset($t[1])) {
            $GLOBALS['t'][] = $t[0];
        } else {
            $GLOBALS['t'][] = (new \Time($t[0] . '-' . $t[1] . '-01 00:00:00'))('%B %Y');
        }
        $GLOBALS['page'] = $page;
        $GLOBALS['pager'] = $pager;
        $GLOBALS['pages'] = $pages;
        \State::set('has', [
            'next' => !!$pager->next,
            'parent' => !!$pager->parent,
            'part' => !!($part + 1),
            'prev' => !!$pager->prev
        ]);
        return ['pages', [], 200];
    }
}

function route__page($content, $path, $query, $hash) {
    if (null !== $content) {
        return $content;
    }
    \extract($GLOBALS, \EXTR_SKIP);
    $route = \trim($state->x->archive->route ?? 'archive', '/');
    // Return the route value to the native page route and move the archive route parameter to `name`
    if ($path && \preg_match('/^(.*?)\/' . \x($route) . '\/([^\/]+)\/([1-9]\d*)$/', $path, $m)) {
        [$any, $path, $name, $part] = $m;
        $query = \To::query(\array_replace(\From::query($query), ['name' => $name]));
        return \Hook::fire('route.archive', [$content, $path . '/' . $part, $query, $hash]);
    }
    return $content;
}

$chops = \explode('/', $url->path ?? "");
$part = \array_pop($chops);
$archive = \array_pop($chops);
$route = \array_pop($chops);

// Initialize response variable(s)
$GLOBALS['archive'] = null;

if (
    $archive &&
    $route === \trim($state->x->archive->route ?? 'archive', '/') &&
    \is_numeric(\strtr($archive, ['-' => ""])) &&
    \preg_match('/^
        # Year
        [1-9]\d{3,}
        (?:
            # Month
            -(0\d|1[0-2])
            (?:
                # Day
                -(0\d|[1-2]\d|3[0-1])
                (?:
                    # Hour
                    -([0-1]\d|2[0-4])
                    (?:
                        # Minute
                        -([0-5]\d|60)
                        (?:
                            # Second
                            -([0-5]\d|60)
                        )?
                    )?
                )?
            )?
        )?
    $/x', $archive)
) {
    $archive = \substr_replace('1970-01-01-00-00-00', $archive, 0, \strlen($archive));
    $GLOBALS['archive'] = new \Time($archive);
    \Hook::set('route.archive', __NAMESPACE__ . "\\route__archive", 100);
    \Hook::set('route.page', __NAMESPACE__ . "\\route__page", 90);
}