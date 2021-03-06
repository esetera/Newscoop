<!DOCTYPE html
    PUBLIC "-//W3C//DTD HTML 4.01//EN"
    "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
  <title>{{ $siteinfo.title }}</title>
  <meta http-equiv="content-type" content="text/html; charset=utf-8" />
  <meta name="generator" content="{{ $siteinfo.generator }}" />
  <meta name="description" content="{{ $siteinfo.description }}" />
  <meta name="keywords" content="{{ $siteinfo.keywords }}" />

  <link rel="stylesheet" type="text/css" href="/{{ $siteinfo.templates_path }}/system_templates/css/_style_offline.css" />
</head>
<body>
<div id="offline">
  <div><img src="/{{ $siteinfo.templates_path }}/system_templates/img/campsite_logo_gn.jpg" />
  <div>{{ $siteinfo.info_message }}</div>
</div>
</body>
</html>
