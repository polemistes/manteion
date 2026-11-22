<?php

/**
 * Tailwind makes a utility of every colour in the theme for every family
 * that takes one: `--color-inset` gave not only `bg-inset` but also a
 * colour utility called `ring-inset`, which overrode the ring colour of
 * every field drawn with `ring-1 ring-inset ring-control` (their outlines
 * vanished into the page). So a colour may not carry the name of a keyword
 * those families already use, nor of one of the text sizes.
 */
test('no colour token shadows another utility', function () {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    preg_match_all('/--color-([a-z0-9-]+)\s*:/', $css, $colours);
    preg_match_all('/--text-([a-z0-9-]+)\s*:/', $css, $sizes);

    $reserved = [
        // ring-, shadow-, outline-, border-, divide-
        'inset', 'offset', 'none', 'hidden', 'solid', 'dashed', 'dotted', 'double', 'collapse', 'separate',
        // bg-
        'fixed', 'local', 'scroll', 'cover', 'contain', 'auto', 'repeat', 'center', 'top', 'bottom', 'left', 'right',
        // text-
        'justify', 'start', 'end', 'wrap', 'nowrap', 'balance', 'pretty', 'ellipsis', 'clip',
        ...array_unique($sizes[1]),
    ];

    expect($colours[1])->not->toBeEmpty()
        ->and(array_values(array_intersect(array_unique($colours[1]), $reserved)))->toBe([]);
});
