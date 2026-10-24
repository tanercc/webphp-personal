<?php
//=======================================================
// Tum scriptler calismadan once BASEPATH kontrolu yapar
//=======================================================
defined('BASEPATH') or exit('No direct script access allowed');

isset($loginResult) ?: $loginResult = null;

?>

<!DOCTYPE html>
<html>

<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title><?= panelHtml($panel_name) ?> | Giriş</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="/panel/plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <!-- icheck bootstrap -->
  <link rel="stylesheet" href="/panel/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="/panel/css/adminlte.min.css">
  <!-- Google Font: Source Sans Pro -->
  <link href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700" rel="stylesheet">
  <!-- Recapcha -->
</head>

<body class="hold-transition login-page">
  <div class="login-box">
    <div class="login-logo">
      <img class="img-responsive mb-2" src="<?= panelHtml(panelMediaUrl('/panel/img/' . $panel_logo)) ?>" alt>
      <br />
      <b><?= panelHtml($panel_name) ?> </b>Panel
    </div>
    <!-- /.login-logo -->
    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">Personal demo paneline giriş yapın</p>

        <form id="login-form" action="" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(panelCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
          <div class="input-group mb-3">
            <input type="email" name="email" class="form-control" placeholder="Email" value="">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-envelope"></span>
              </div>
            </div>
          </div>
          <div class="input-group mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password" value="">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-lock"></span>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-8">
              <div class="icheck-primary">
                <!-- <input type="checkbox" id="remember">
              <label for="remember">
                Beni Hatırla
              </label> -->
              </div>
            </div>
            <!-- /.col -->
            <div class="col-4">
              <button class="btn btn-primary btn-block" type="submit">Giriş</button>
            </div>
            <!-- /.col -->
          </div>
        </form>

        <?php if ($loginResult === false) : ?>
          <div class="alert alert-danger mt-3">
            Kullanıcı adı veya parolanız hatalı!
          </div>
        <?php endif ?>
        <!-- <p class="mb-1">
        <a href="#">Parolamı unuttum</a>
      </p> -->
      </div>
      <!-- /.login-card-body -->
    </div>
  </div>
  <!-- /.login-box -->

  <!-- jQuery -->
  <script src="/panel/plugins/jquery/jquery.min.js"></script>
  <!-- Bootstrap 4 -->
  <script src="/panel/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- AdminLTE App -->
  <script src="/panel/js/adminlte.min.js"></script>
  <script>
    function onSubmit(token) {
      document.getElementById("login-form").submit();
    }
  </script>
</body>

</html>
