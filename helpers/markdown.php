<?php
/**
 * Markdown Helper
 */

if (!defined('QUOTABASE_SYSTEM')) {
    define('QUOTABASE_SYSTEM', true);
}

require_once __DIR__ . '/../vendor/erusev/parsedown/Parsedown.php';

function render_markdown_to_html(string $text): string {
    static $parser = null;

    if ($parser === null) {
        $parser = new Parsedown();
        if (method_exists($parser, 'setSafeMode')) {
            $parser->setSafeMode(true);
        }
        if (method_exists($parser, 'setBreaksEnabled')) {
            $parser->setBreaksEnabled(true);
        }
    }

    return $parser->text($text);
}
