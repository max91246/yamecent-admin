<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>管理控制台</title>
  <!-- plugins:css -->
  <link rel="stylesheet" href="/assets/vendors/iconfonts/mdi/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="/assets/vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" type="text/css" href="/assets/wangEditor/dist/css/wangEditor.min.css">
  <!-- endinject -->
  <!-- inject:css -->
  <link rel="stylesheet" href="/assets/css/style.css">
  <!-- endinject -->
  <link rel="shortcut icon" href="/assets/images/favicon.png" />

  <script src="https://code.jquery.com/jquery-3.0.0.min.js"></script>
  {{--datetimer--}}
  <link rel="stylesheet" id=cal_style type="text/css" href="/assets/datetimer/dist/flatpickr.min.css">
  <script src="/assets/datetimer/src/flatpickr.js"></script>
  <script src="/assets/datetimer/src/flatpickr.l10n.zh.js"></script>
  <style>
    /*定义滚动条高宽及背景 高宽分别对应横竖滚动条的尺寸*/
    ::-webkit-scrollbar
    {
      width: 5px;
      height: 20px;
      background-color: #F5F5F5;
    }

    ::-webkit-scrollbar-track {
      background-color: #0f1428;
    }
    ::-webkit-scrollbar-thumb {
      border-radius: 10px;
      background-color: #2a3a5e;
    }

    /* === Dark theme: iframe content === */
    body {
      background-color: #0b0f1e !important;
    }

    .main-panel {
      background-color: #0b0f1e !important;
    }

    .content-wrapper {
      background-color: #0b0f1e !important;
    }

    .page-title {
      color: #e2e8f0 !important;
    }

    /* Cards */
    .card {
      background: #111628 !important;
      border: 1px solid rgba(100, 160, 255, 0.1) !important;
    }

    .card-title {
      color: #e2e8f0 !important;
    }

    .card-body h4 {
      color: #e2e8f0 !important;
    }

    .text-muted {
      color: #718096 !important;
    }

    /* Border dividers in dark mode */
    .border-right {
      border-right-color: rgba(100, 160, 255, 0.1) !important;
    }

    /* Tables */
    .table {
      color: #a0aec0 !important;
    }

    .table thead th {
      background-color: #0d1224 !important;
      color: #63b3ed !important;
      border-color: rgba(100, 160, 255, 0.15) !important;
    }

    .table td, .table th {
      border-color: rgba(100, 160, 255, 0.08) !important;
    }

    .table-striped tbody tr:nth-of-type(odd) {
      background-color: rgba(100, 160, 255, 0.04) !important;
    }

    .table-hover tbody tr:hover {
      background-color: rgba(100, 160, 255, 0.08) !important;
    }

    /* Forms */
    .form-control {
      background-color: #0d1224 !important;
      border-color: rgba(100, 160, 255, 0.2) !important;
      color: #e2e8f0 !important;
    }

    .form-control:focus {
      background-color: #151d35 !important;
      border-color: rgba(100, 160, 255, 0.5) !important;
      color: #e2e8f0 !important;
      box-shadow: 0 0 0 0.2rem rgba(100, 160, 255, 0.15) !important;
    }

    .form-control::placeholder {
      color: #4a5568 !important;
    }

    label {
      color: #a0aec0 !important;
    }

    /* Select */
    select.form-control option {
      background-color: #0d1224;
    }

    /* Footer */
    .footer {
      background: #0d1224 !important;
      border-top: 1px solid rgba(100, 160, 255, 0.08) !important;
    }

    .footer .text-muted {
      color: #4a5568 !important;
    }

    /* Page header icon */
    .page-title-icon {
      background: linear-gradient(135deg, #1a3a6e, #2563a8) !important;
    }
  </style>
</head>
<body>
<script src="/assets/layer/layer.js"></script>
<script src="/assets/wangEditor/dist/js/wangEditor.min.js"></script>
@yield('base')
  <!-- plugins:js -->
  <script src="/assets/vendors/js/vendor.bundle.base.js"></script>
  <script src="/assets/vendors/js/vendor.bundle.addons.js"></script>
  <!-- endinject -->
  <!-- Plugin js for this page-->
  <!-- End plugin js for this page-->
  <!-- inject:js -->
  <script src="/assets/js/off-canvas.js"></script>
  <script src="/assets/js/misc.js"></script>
  <!-- endinject -->
  <!-- Custom js for this page-->
<script src="/assets/js/dashboard.js"></script>
<script src="/assets/js/common.js"></script>
  <!-- End custom js for this page-->
</body>

</html>
