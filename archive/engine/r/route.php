<?php namespace x\archive;

function route($any, $date) {
    extract($GLOBALS, \EXTR_SKIP);
    $date = (string) $date;
    $i = ($url['i'] ?? 1) - 1;
    $path = \State::get('x.archive.path') ?? '/archive';
    if (\is_numeric(\strtr($date, ['-' => ""])) && \preg_match('/^
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
    $/x', $date)) {
        $t = \array_slice(\array_replace([
            '0000', // Year
            '00', // Month
            '00', // Day
            '00', // Hour
            '00', // Minute
            '00' // Second
        ], \explode('-', $date)), 0, 6);
        $GLOBALS['archive'] = new \Time(\implode('-', $t));
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
            $pages->lot($pages->is(function($v) use($date) {
                $page = new \Page($v);
                $t = $page->time;
                return 0 === \strpos(\strtr($t, [
                    ':' => '-',
                    ' ' => '-'
                ]) . '-', $date . '-');
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
        $t = \explode('-', $date);
        if (!isset($t[1])) {
            $GLOBALS['t'][] = $t[0];
        } else {
            $GLOBALS['t'][] = (new \Time($t[0] . '-' . $t[1] . '-01 00:00:00'))('%B %Y');
        }
        $pager = new \Pager\Pages($pages->get(), [$chunk, $i], (object) [
            'link' => $url . '/' . $any . $path . '/' . $date
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
            $this->layout('404/' . $any . $path . '/' . $date . '/' . ($i + 1));
        }
        \State::set('has', [
            'next' => !!$pager->next,
            'parent' => !!$pager->parent,
            'prev' => !!$pager->prev
        ]);
        $this->layout('pages/' . $any . $path . '/' . $date . '/' . ($i + 1));
    }
    \State::set([
        'has' => [
            'next' => false,
            'parent' => false,
            'prev' => false
        ],
        'is' => ['error' => 404]
    ]);
    $GLOBALS['t'][] = \i('Error');
    $this->layout('404/' . $any . $path . '/' . $date . '/' . ($i + 1));
}

\Route::set('*' . (\State::get('x.archive.path') ?? '/archive') . '/:date', 200, __NAMESPACE__ . "\\route", 10);
