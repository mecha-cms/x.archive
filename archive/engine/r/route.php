<?php namespace x\archive;

function route($any, $name) {
    extract($GLOBALS, \EXTR_SKIP);
    $name = (string) $name;
    $i = ($url['i'] ?? 1) - 1;
    $path = $state->x->archive->path ?? '/archive';
    $r = \LOT . \DS . 'page' . \DS . $any;
    if ($file = \File::exist([
        $r . '.archive',
        $r . '.page'
    ])) {
        $page = new \Page($file);
    }
    \State::set([
        'chunk' => $chunk = $page['chunk'] ?? 5,
        'deep' => $deep = $page['deep'] ?? 0,
        'sort' => $sort = [-1, 'time'] // Force page sort by the `time` data
    ]);
    $pages = \Pages::from($r, 'page', $deep)->sort($sort);
    if ($pages->count() > 0) {
        $pages->lot($pages->is(function($v) use($name) {
            $page = new \Page($v);
            $t = $page->time;
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
        'link' => $url . '/' . $any . $path . '/' . $name
    ]);
    // Set proper parent link
    if (0 === $i) {
        $pager->parent = $page;
    }
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
            'is' => ['error' => 404]
        ]);
        $GLOBALS['t'][] = \i('Error');
        $this->layout('404/' . $any . $path . '/' . $name . '/' . ($i + 1));
    }
    \State::set('has', [
        'next' => !!$pager->next,
        'parent' => !!$pager->parent,
        'prev' => !!$pager->prev
    ]);
    $this->layout('pages/' . $any . $path . '/' . $name . '/' . ($i + 1));
}

\Route::set('*' . ($state->x->archive->path ?? '/archive') . '/:archive', 200, __NAMESPACE__ . "\\route", 10);
