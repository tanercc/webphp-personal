<?php
defined('BASEPATH') or exit('No direct script access allowed');
require_once __DIR__ . '/output.php';

/** Safe HTML for rendered content only; never rewrites the stored/editor value. */
function panelRichHtml($value): string
{
    if ($value === null || $value === '') { return ''; }
    if (!is_scalar($value)) { return ''; }
    $html = (string) $value;
    $autoload = __DIR__ . '/../plugins/htmlpurifier/library/HTMLPurifier.auto.php';
    if (!class_exists('HTMLPurifier') && is_file($autoload)) { require_once $autoload; }
    if (!class_exists('HTMLPurifier')) {
        // Missing deployment dependencies must not allow raw HTML execution.
        return panelHtml($html);
    }
    static $purifier;
    if ($purifier === null) {
        $config = HTMLPurifier_Config::createDefault();
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('Cache.DefinitionImpl', null);
        $config->set('HTML.Allowed', implode(',', [
            '*[class|style|lang|dir]', 'p', 'div', 'span', 'br', 'hr',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
            'sub', 'sup', 'blockquote', 'pre', 'code', 'ul', 'ol', 'li', 'dl', 'dt', 'dd',
            'a[href|title]', 'img[src|alt|title|width|height]',
            'table[border|cellpadding|cellspacing|width|summary]', 'caption', 'thead', 'tbody', 'tfoot',
            'tr', 'th[colspan|rowspan|scope|width|height]', 'td[colspan|rowspan|width|height]',
        ]));
        $config->set('CSS.AllowedProperties', [
            'text-align', 'vertical-align', 'color', 'background-color', 'font-size', 'font-family',
            'font-weight', 'font-style', 'text-decoration', 'line-height', 'width', 'height',
            'border', 'border-width', 'border-style', 'border-color', 'border-collapse',
            'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
            'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'float',
        ]);
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true, 'tel' => true]);
        $config->set('HTML.Trusted', false);
        // Formatting classes are preserved; editor HTML cannot enable HTML/AJAX widgets.
        $config->set('Attr.ForbiddenClasses', ['fancybox', 'fancybox_div']);
        $config->set('HTML.SafeIframe', false);
        $config->set('HTML.SafeObject', false);
        $purifier = new HTMLPurifier($config);
    }
    return $purifier->purify($html);
}
