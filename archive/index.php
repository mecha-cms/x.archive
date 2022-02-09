<?php namespace x\archive;

function route($r, $path) {
    if (isset($r['content']) || isset($r['kick'])) {
        return $r;
    }
    \extract($GLOBALS, \EXTR_SKIP);
    $name = $r['name'];
    if ($path && \preg_match('/^(.*?)\/([1-9]\d*)$/', $path, $m)) {
        [$any, $path, $i] = $m;
    }
    $i = ((int) ($i ?? 1)) - 1;
    $path = \trim($path ?? "", '/');
    $route = \trim($state->x->archive->route ?? 'archive', '/');
    $folder = \LOT . \D . 'page' . \D . $path;
    if ($file = \exist([
        $folder . '.archive',
        $folder . '.page'
    ], 1)) {
        $page = new \Page($file);
    }
    \State::set([
        'chunk' => $chunk = $page['chunk'] ?? 5,
        'deep' => $deep = $page['deep'] ?? 0,
        'sort' => $sort = [-1, 'time'] // Force page sort by the `time` data
    ]);
    $pages = \Pages::from($folder, 'page', $deep)->sort($sort);
    if ($pages->count() > 0) {
        $pages->lot($pages->is(function($v) use($name) {
            $page = new \Page($v);
            $t = $page->time . "";
            return 0 === \strpos(\strtr($t, [
                ':' => '-',
                ' ' => '-'
            ]) . '-', $name . '-');
        })->get());
    }
    \State::set([
        'is' => [
            'error' => false,
            'page' => false,
            'pages' => true,
            'archive' => false, // Never be `true`
            'archives' => true
        ],
        'has' => [
            'page' => true,
            'pages' => $pages->count() > 0,
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
    $pager = new \Pager\Pages($pages->get(), [$chunk, $i], (object) [
        'link' => $url . '/' . $path . '/' . $route . '/' . $name
    ]);
    // Set proper parent link
    $pager->parent = $i > 0 ? (object) ['link' => $url . '/' . $path . '/' . $route . '/' . $name . '/1'] : $page;
    $pages = $pages->chunk($chunk, $i);
    $GLOBALS['page'] = $page;
    $GLOBALS['pager'] = $pager;
    $GLOBALS['pages'] = $pages;
    $GLOBALS['parent'] = $page;
    if (0 === $pages->count()) {
        // Greater than the maximum step or less than `1`, abort!
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
        $r['content'] = \Hook::fire('layout', ['error/' . $path . '/' . $route . '/' . $name . '/' . ($i + 1)]);
        $r['status'] = 404;
        return $r;
    }
    \State::set('has', [
        'next' => !!$pager->next,
        'parent' => !!$pager->parent,
        'prev' => !!$pager->prev
    ]);
    $r['content'] = \Hook::fire('layout', ['pages/' . $path . '/' . $route . '/' . $name . '/' . ($i + 1)]);
    $r['status'] = 200;
    return $r;
}

$chops = \explode('/', $url->path ?? "");
$i = \array_pop($chops);
$archive = \array_pop($chops);
$route = \array_pop($chops);

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
    \Hook::set('route.archive', __NAMESPACE__ . "\\route", 100);
    \Hook::set('route.page', function($r, $path, $query, $hash) use($route) {
        if ($path && \preg_match('/^(.*?)\/' . \x($route) . '\/([^\/]+)\/([1-9]\d*)$/', $path, $m)) {
            [$any, $path, $name, $i] = $m;
            $r['name'] = $name;
            return \Hook::fire('route.archive', [$r, $path . '/' . $i, $query, $hash]);
        }
        return $r;
    }, 90);
}