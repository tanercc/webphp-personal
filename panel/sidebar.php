<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

?>

<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-dark-primary elevation-4">
  <!-- Brand Logo -->
  <a href="<?= panelHtml(url('/')) ?>" class="brand-link">
    <img src="<?= panelHtml(panelMediaUrl('/panel/img/' . $panel_logo)) ?>" alt="Panel Logo" class="brand-image img-circle" style="opacity: .8">
    <span class="brand-text font-weight-light"><b><?= panelHtml($panel_name) ?></b>Panel</span>
  </a>

  <!-- Sidebar -->
  <div class="sidebar">
    <!-- Sidebar user panel (optional) -->
    <div class="user-panel mt-3 pb-3 mb-3 d-flex">
      <div class="image">
        <img src="<?= panelHtml(panelMediaUrl($_SESSION['userImage'] ?? '')) ?>" class="img-circle" alt="User Image">
      </div>
      <div class="info">
        <a href="<?= panelHtml(url('self')) ?>" class="d-block"><?= panelHtml($_SESSION['userName'] ?? '') ?></a>
      </div>
    </div>

    <!-- Sidebar Menu -->
    <nav class="mt-2">
      <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
        <!-- Add icons to the links using the .nav-icon class
              with font-awesome or any other icon font library -->

        <?php ?>
        <li class="nav-item">
          <a href="<?= panelHtml(url('/')) ?>" class="nav-link <?= $siteRequest == '' ? 'active' : '' ?>">
            <i class="nav-icon fas fa-tachometer-alt"></i>
            <p>Dashboard</p>
          </a>
        </li>

        <?php if (isUserAllowed(['pages', 'pagetypes'])) : ?>
          <li class="nav-item has-treeview <?= in_array($siteRequest, ['pages', 'pagetypes']) ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-book"></i>
              <p>
                Sayfalar
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">

              <?php if (isUserAllowed('pages')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('pages')) ?>" class="nav-link <?= $siteRequest == 'pages' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Sayfa Listesi</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('pagetypes')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('pagetypes')) ?>" class="nav-link <?= $siteRequest == 'pagetypes' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Sayfa Tipleri</p>
                  </a>
                </li>
              <?php endif ?>

            </ul>
          </li>
        <?php endif ?>

        <?php if (isUserAllowed(['sliders', 'slidertypes'])) : ?>
          <li class="nav-item has-treeview <?= in_array($siteRequest, ['sliders', 'slidertypes']) ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-desktop"></i>
              <p>
                Sliderlar
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">

              <?php if (isUserAllowed('sliders')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('sliders')) ?>" class="nav-link <?= $siteRequest == 'sliders' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Slider Listesi</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('slidertypes')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('slidertypes')) ?>" class="nav-link <?= $siteRequest == 'slidertypes' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Slider Tipleri</p>
                  </a>
                </li>
              <?php endif ?>
            </ul>
          </li>
        <?php endif ?>

        <?php if (isUserAllowed(['menus', 'menutypes', 'menusort'])) : ?>
          <li class="nav-item has-treeview <?= in_array($siteRequest, ['menus', 'menutypes', 'menusort']) ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-stream"></i>
              <p>
                Menüler
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">

              <?php if (isUserAllowed('menus')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('menus')) ?>" class="nav-link <?= $siteRequest == 'menus' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Menü Listesi</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('menutypes')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('menutypes')) ?>" class="nav-link <?= $siteRequest == 'menutypes' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Menü Tipleri</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('menusort')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('menusort')) ?>" class="nav-link <?= $siteRequest == 'menusort' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Menü Sıralama</p>
                  </a>
                </li>
              <?php endif ?>

            </ul>
          </li>
        <?php endif ?>

        <?php if (isUserAllowed(['datas', 'datatypes', 'tags', 'cats', 'templates'])) : ?>
          <li class="nav-item has-treeview <?= in_array($siteRequest, ['datas', 'datatypes', 'tags', 'cats', 'templates']) ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-table"></i>
              <p>
                Veriler
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">

              <?php if (isUserAllowed('datas')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('datas')) ?>" class="nav-link <?= $siteRequest == 'datas' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Veri Listesi</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('datatypes')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('datatypes')) ?>" class="nav-link <?= $siteRequest == 'datatypes' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Veri Tipleri</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('templates')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('templates')) ?>" class="nav-link <?= $siteRequest == 'templates' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i><p>İçerik Şablonları</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('cats')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('cats')) ?>" class="nav-link <?= $siteRequest == 'cats' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Kategoriler</p>
                  </a>
                </li>
              <?php endif ?>

              <?php if (isUserAllowed('tags')) : ?>
                <li class="nav-item">
                  <a href="<?= panelHtml(url('tags')) ?>" class="nav-link <?= $siteRequest == 'tags' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Etiketler</p>
                  </a>
                </li>
              <?php endif ?>

            </ul>
          </li>
        <?php endif ?>

        <?php if ($_SESSION['userType'] == 'admin') : ?>
          <li class="nav-item has-treeview <?= in_array($siteRequest, ['users', 'variables']) ? 'menu-open' : '' ?>">
            <a href="#" class="nav-link">
              <i class="nav-icon fas fa-cog"></i>
              <p>
                Ayarlar
                <i class="fas fa-angle-left right"></i>
              </p>
            </a>
            <ul class="nav nav-treeview">

                <li class="nav-item">
                  <a href="<?= panelHtml(url('users')) ?>" class="nav-link <?= $siteRequest == 'users' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Kullanıcılar</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="<?= panelHtml(url('variables')) ?>" class="nav-link <?= $siteRequest == 'variables' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Değişkenler</p>
                  </a>
                </li>

                <li class="nav-item">
                  <a href="<?= panelHtml(url('captions')) ?>" class="nav-link <?= $siteRequest == 'captions' ? 'active' : '' ?>">
                    <i class="far fa-circle nav-icon"></i>
                    <p>Başlıklar</p>
                  </a>
                </li>

            </ul>
          </li>
        <?php endif ?>

      </ul>
    </nav>
    <!-- /.sidebar-menu -->
  </div>
  <!-- /.sidebar -->
</aside>
