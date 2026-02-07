<?php

namespace {
    // Disable this extension if `page` extension is disabled or removed ;)
    if (!isset($state->x->page)) {
        return;
    }
    // Initialize layout variable(s)
    \lot('archive', new \Time);
}

namespace x\archive {
    function route__archive($content, $path, $query, $hash) {
        if (null !== $content) {
            return $content;
        }
        \extract(\lot(), \EXTR_SKIP);
        $route = \trim($state->x->archive->route ?? 'archive', '/');
        if ($part = \x\page\part($path = \trim($path ?? "", '/'))) {
            $path = \substr($path, 0, -\strlen('/' . $part));
        }
        $part = ($part ?? 0) - 1;
        // For `/…/archive/:name/:part`
        if ($part >= 0 && $path) {
            if ($file = \exist(\LOT . \D . 'page' . \D . $path . '.{' . ($x = \x\page\x()) . '}', 1)) {
                if ($name = $state->q('archive.name')) {
                    \lot('page', $page = new \Page($file));
                    $chunk = $state->x->archive->lot->chunk ?? $page->chunk ?? 5;
                    $sort = \array_replace([-1, 'time'], (array) ($page->sort ?? []), (array) ($state->x->archive->lot->sort ?? []));
                    if ($pages = $page->children($x, 0)) {
                        $pages = $pages->is(function ($v) use ($name) {
                            return 0 === \strpos(\strtr($v->time . '-', [
                                ' ' => '-',
                                ':' => '-'
                            ]), $name . '-');
                        })->sort($sort);
                    } else {
                        $pages = new \Pages;
                    }
                    \lot('t')[] = $page->title;
                    \lot('t')[] = \i('Archive');
                    $a = \explode('-', $name);
                    if (isset($a[1])) {
                        \lot('t')[] = (new \Time($a[0] . '-' . $a[1] . '-01 00:00:00'))('%B %Y');
                    } else {
                        \lot('t')[] = $a[0];
                    }
                    $pager = \Pager::from($pages);
                    $pager->path = $path . '/' . $route . '/' . $name;
                    \lot('pager', $pager = $pager->chunk($chunk, $part));
                    \lot('pages', $pages = $pages->chunk($chunk, $part));
                    if (0 === ($count = \q($pages))) {
                        \lot('t')[] = \i('Error');
                    }
                    \State::set([
                        'has' => [
                            'next' => !!$pager->next,
                            'parent' => !!$page->parent,
                            'prev' => !!$pager->prev
                        ],
                        'is' => ['error' => 0 === $count ? 404 : false],
                        'with' => ['pages' => $count > 0]
                    ]);
                    return [
                        'lot' => [],
                        'status' => 0 === $count ? 404 : 200,
                        'y' => 'pages/archive/' . $name
                    ];
                }
            }
        }
    }
    function route__page($content, $path, $query, $hash) {
        if (null !== $content) {
            return $content;
        }
        \extract(\lot(), \EXTR_SKIP);
        $route = \trim($state->x->archive->route ?? 'archive', '/');
        if ($part = \x\page\part($path = \trim($path ?? "", '/'))) {
            $path = \substr($path, 0, -\strlen('/' . $part));
            $path = \dirname($path); // Remove the name (time) part
            if ($route !== \basename($path)) {
                return $content;
            }
            return \Hook::fire('route.archive', [$content, \dirname($path) . '/' . $part, $query, $hash]);
        }
        return $content;
    }
    if ($part = \x\page\part($path = \trim($url->path ?? "", '/'))) {
        $path = \substr($path, 0, -\strlen('/' . $part));
    }
    $part = ($part ?? 0) - 1;
    $route = \trim($state->x->archive->route ?? 'archive', '/');
    // For `/…/archive/:name/:part`
    if ($part >= 0 && $route === \basename(\dirname($path))) {
        $a = \explode('-', $name = \basename($path));
        // Year
        if (\count($a) < 7 && ($n = (int) $a[0]) > 1969) {
            // Month
            if (!isset($a[1]) || ($n = (int) $a[1]) > 0 && $n < 13) {
                // Day
                if (!isset($a[2]) || ($n = (int) $a[2]) > 0 && $n < 32) {
                    // Hour
                    if (!isset($a[3]) || ($n = (int) $a[3]) > 0 && $n < 25) {
                        // Minute
                        if (!isset($a[4]) || ($n = (int) $a[4]) > 0 && $n < 61) {
                            // Second
                            if (!isset($a[5]) || ($n = (int) $a[5]) > 0 && $n < 61) {
                                \lot('archive', new \Time(\substr_replace('1970-01-01-00-00-00', $name, 0, \strlen($name))));
                                \Hook::set('route.archive', __NAMESPACE__ . "\\route__archive", 100);
                                \Hook::set('route.page', __NAMESPACE__ . "\\route__page", 90);
                                \State::set([
                                    'is' => ['archives' => true],
                                    'q' => [
                                        'archive' => [
                                            'name' => $name,
                                            'part' => $part + 1
                                        ]
                                    ]
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }
}