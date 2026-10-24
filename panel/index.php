<?php

//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
define('BASEPATH', '/panel');

require_once "include/config.php";
require_once "include/functions.php";
require_once "include/db_functions.php";
require_once "include/permissions.php";
require_once "include/init.php";

if (!$_SESSION['userAuthorized']) {

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once "include/login.php";
    $loginResult = checkLogin();
  }

  if (!$_SESSION['userAuthorized']) {
    require_once "page-login.php";
    return;
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="csrf-token" content="<?= htmlspecialchars(panelCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="x-ua-compatible" content="ie=edge">

  <title><?= panelHtml($panel_name) ?></title>

  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="/panel/plugins/fontawesome-free/css/all.min.css">
  <!-- jsGrid -->
  <link rel="stylesheet" href="/panel/plugins/jsgrid/jsgrid.min.css">
  <link rel="stylesheet" href="/panel/plugins/jsgrid/jsgrid-theme.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="/panel/css/adminlte.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="/panel/plugins/daterangepicker/daterangepicker.css">
  <!-- Tempusdominus Bbootstrap 4 -->
  <link rel="stylesheet" href="/panel/plugins/tempusdominus/css/tempusdominus-bootstrap-4.min.css">
  <!-- Select2 -->
  <link rel="stylesheet" href="/panel/plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="/panel/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <!-- fancybox -->
  <link rel="stylesheet" href="/panel/plugins/fancybox/jquery.fancybox.min.css">
  <!-- Custom style -->
  <link rel="stylesheet" href="/panel/css/custom.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">

  <!-- REQUIRED SCRIPTS -->

  <!-- jQuery -->
  <script src="/panel/plugins/jquery/jquery.min.js?v=3.7.1"></script>
  <script src="/panel/js/csrf.js"></script>
  <!-- jQuery UI -->
  <script src="/panel/plugins/jquery-ui/jquery-ui.min.js?v=1.14.2"></script>
  <!-- Bootstrap 4 -->
  <script src="/panel/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Bootstrap Waitingfor -->
  <script src="/panel/plugins/waitingfor/waitingfor.js"></script>
  <!-- jsGrid -->
  <script src="/panel/plugins/jsgrid/jsgrid.min.js"></script>
  <script src="/panel/js/output-security.js"></script>
  <!-- daterangepicker -->
  <script src="/panel/plugins/moment/moment-with-locales.min.js?v=2.31.0"></script>
  <script src="/panel/plugins/daterangepicker/daterangepicker.js"></script>
  <!-- Tempusdominus Bootstrap 4 -->
  <script src="/panel/plugins/tempusdominus/js/tempusdominus-bootstrap-4.min.js"></script>
  <!-- Select2 -->
  <script src="/panel/plugins/select2/js/select2.full.min.js"></script>
  <!-- CKEditor 4 -->
  <script src="/panel/plugins/ckeditor/ckeditor.js?v=4.22.1"></script>
  <!-- fancybox -->
  <script src="/panel/plugins/fancybox/jquery.fancybox.min.js"></script>
  <!-- Sortable -->
  <script src="/panel/plugins/sortable/sortable.min.js?v=1.15.7"></script>
  <!-- AdminLTE App -->
  <script src="/panel/js/adminlte.min.js"></script>
  <!-- Custom Script -->
  <script src="/panel/js/urlify.js"></script>
</head>

<body class="hold-transition sidebar-mini">
  <div class="wrapper">

    <?php include "navbar.php" ?>

    <?php include "sidebar.php" ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">

      <?php include getSiteContent(__DIR__) ?>

    </div>
    <!-- /.content-wrapper -->

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
      <!-- Control sidebar content goes here -->
      <div class="p-3">
        <h5>Title</h5>
        <p>Sidebar content</p>
      </div>
    </aside>
    <!-- /.control-sidebar -->

    <!-- Main Footer -->
    <footer class="main-footer">
      <!-- To the right -->
      <div class="float-right d-none d-sm-inline">
        <?= panelHtml(trim($panel_name)) ?> · SQLite
      </div>
      <!-- Default to the left -->
      <strong><?= panelHtml(trim($panel_name)) ?></strong> All rights reserved.
    </footer>
  </div>
  <!-- ./wrapper -->

  <script>
    $(function() {
      //Initialize FancyBox
      $('.fancybox').fancybox();

      // Responsive File Manager
      $('[data-filemanager]').fancybox({
        toolbar: false,
        smallBtn: true,
        iframe: {
          preload: false
        }
      })

      var unsaved = false;

      $("form#edit-form :input").change(function() { //triggers change in all input fields including text type
        unsaved = true;
      });

      $('form#edit-form #submit').click(function() {
        unsaved = false;
      });

      window.addEventListener('beforeunload', function(event) {
        if (unsaved) {
          return event.returnValue = 'Unsaved?';
        }
      });
    });
  </script>

</body>

</html>
