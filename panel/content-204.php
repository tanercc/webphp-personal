<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

?>

<!-- Content Header (Page header) -->
<section class="content-header">
  <div class="container-fluid">
    <div class="row mb-2">
      <div class="col-sm-6">
        <h1>204 Hata Sayfası</h1>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-right">
          <li class="breadcrumb-item"><a href="<?= panelHtml(url('/')) ?>">Anasayfa</a></li>
          <li class=" breadcrumb-item active">204 Hata Sayfası</li>
        </ol>
      </div>
    </div>
  </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
  <div class="error-page">
    <h2 class="headline text-success"> 204</h2>

    <div class="error-content">
      <h3><i class="fas fa-exclamation-triangle text-warning"></i> Veri bulunamadı.</h3>

      <p>
        Bu işlem için veri bulunamadı...
        <a href="<?= panelHtml(url('/')) ?>">Anasayfa</a>'ya geri dönünüz.
      </p>

    </div>
    <!-- /.error-content -->
  </div>
  <!-- /.error-page -->
</section>
<!-- /.content -->
