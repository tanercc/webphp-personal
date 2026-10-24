<?php
defined('BASEPATH') or exit('No direct script access allowed');
// Site-owned PHP templates. The CMS stores keys, never include paths.
return [
    'default' => ['label' => 'Standart içerik', 'file' => 'content-data-default.php'],
    'slider' => ['label' => 'Görsel slider', 'file' => 'content-data-slider.php'],
    'masonry' => ['label' => 'Galeri', 'file' => 'content-data-masonry.php'],
    'category' => ['label' => 'Kategorili içerik', 'file' => 'content-data-category.php'],
];
