<?php

$chops = explode('/', $url->path);
$archive = array_pop($chops);
$prefix = array_pop($chops);

$GLOBALS['archive'] = null;

if (
    $archive &&
    '/' . $prefix === ($state->x->archive->path ?? '/archive') &&
    is_numeric(strtr($archive, ['-' => ""])) &&
    preg_match('/^
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
    $archive = substr_replace('1970-01-01-00-00-00', $archive, 0, strlen($archive));
    $GLOBALS['archive'] = new Time($archive);
    require __DIR__ . DS . 'engine' . DS . 'r' . DS . 'route.php';
}