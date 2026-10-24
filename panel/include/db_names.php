<?php

function dbName($key) {
  global $dbNames;
  return isset($dbNames[$key]) ? $dbNames[$key] : $key;
}

$dbNames = [
  'id' => 'ID',
  'name' => 'Ad',
  'slug' => 'Url',
  'info' => 'Bilgi',
  'title' => 'Başlık',
  'content' => 'Yazı',
  'icon' => 'Simge',
  'image' => 'Resim',
  'video' => 'Vidyo',
  'file' => 'Dosya',
  'link' => 'Bağlantı',
  'writer' => 'Yazar',
  'write_at' => 'Tarih',
  'coordinate' => 'Koordinat',
  'include' => 'Eklenti',
  'template' => 'Template',
  'email' => 'Eposta',
  'last_login' => 'Son Giriş',
  'password' => 'Parola',
  'is_active' => 'Aktif',
  'deleted' => 'Silinmiş',
  'sort_order' => 'Sıra',
  'type_id' => 'Tipi',
  'parent_id' => 'Üst Bağlantı',
  'page_id' => 'Bağlı Sayfa',
  'data_id' => 'Bağlı Veri',
  'datatype_id' => 'Bağlı Veri Tipi',
  'cat_id' => 'Bağlı Kategori',
  'tag_id' => 'Bağlı Etiket',
  'parent_id' => 'Üst ID',
  'filename' => 'Dosya Adı'
];
