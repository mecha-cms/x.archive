<?php namespace Pager;

class Archive extends \Pager {

    public function pages(...$lot) {
        $pages = \Pages::from(...$lot);
        $name = \basename($this->lot['route']);
        return $pages->is(static function ($v) use ($name) {
            $page = new \Page($v);
            $t = $page->time . "";
            return 0 === \strpos(\strtr($t, [
                ':' => '-',
                ' ' => '-'
            ]) . '-', $name . '-');
        });
    }

}