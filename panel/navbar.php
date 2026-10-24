<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

?>

<!-- Navbar -->
<nav class="main-header navbar navbar-expand navbar-white navbar-light">
  <!-- Left navbar links -->
  <ul class="navbar-nav">
    <li class="nav-item">
      <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="/" target="_blank" class="nav-link">Site</a>
    </li>
    <li class="nav-item d-none d-sm-inline-block">
      <a href="<?= panelHtml(url('/')) ?>" class="nav-link">İletişim</a>
    </li>
  </ul>

  <!-- Right navbar links -->
  <ul class="navbar-nav ml-auto">
    <!-- Logout -->
    <li class="nav-item dropdown">
      <form action="<?= panelHtml(url('logout')) ?>" method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(panelCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <button class="nav-link btn btn-link" type="submit" aria-label="Çıkış yap">
          <i class="fas fa-power-off"></i>
        </button>
      </form>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-widget="control-sidebar" data-slide="true" href="#" role="button"><i class="fas fa-th-large"></i></a>
    </li>
  </ul>
</nav>
<!-- /.navbar -->
