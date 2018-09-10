<?php 
/**
 * @var \Ufw\Helper\Render $this
 * @var string $assetSet
 */

?>
<meta charset="utf-8">
<meta name="viewport" content="initial-scale=1, maximum-scale=1, user-scalable=no, width=device-width">
<title>Whistler form submissions</title>
<meta name="description" content="Whistler port of Kobo, suite of tools for field data collection for use in challenging environments.">
<meta name="author" content="">
<meta name="msapplication-config" content="/static2/favicon/browserconfig.xml" />
<link rel="apple-touch-icon" sizes="57x57" href="/static2/favicon/apple-icon-57x57.png">
<link rel="apple-touch-icon" sizes="60x60" href="/static2/favicon/apple-icon-60x60.png">
<link rel="apple-touch-icon" sizes="72x72" href="/static2/favicon/apple-icon-72x72.png">
<link rel="apple-touch-icon" sizes="76x76" href="/static2/favicon/apple-icon-76x76.png">
<link rel="apple-touch-icon" sizes="114x114" href="/static2/favicon/apple-icon-114x114.png">
<link rel="apple-touch-icon" sizes="120x120" href="/static2/favicon/apple-icon-120x120.png">
<link rel="apple-touch-icon" sizes="144x144" href="/static2/favicon/apple-icon-144x144.png">
<link rel="apple-touch-icon" sizes="152x152" href="/static2/favicon/apple-icon-152x152.png">
<link rel="apple-touch-icon" sizes="180x180" href="/static2/favicon/apple-icon-180x180.png">
<link rel="icon" type="image/png" sizes="192x192"  href="/static2/favicon/android-icon-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="/static2/favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="96x96" href="/static2/favicon/favicon-96x96.png">
<link rel="icon" type="image/png" sizes="16x16" href="/static2/favicon/favicon-16x16.png">
<link rel="manifest" href="/static2/favicon/manifest.json">
<meta name="msapplication-TileColor" content="#ffffff">
<meta name="msapplication-TileImage" content="/static2/favicon/ms-icon-144x144.png">
<meta name="theme-color" content="#ffffff">
<?php if ($gaId = @$this->getApplication()->getConfig()['ga']['trackingID']): ?>
<!-- Global Site Tag (gtag.js) - Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $gaId; ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments)};
  gtag('js', new Date());

  gtag('config', '<?php echo $gaId; ?>');
</script>
<?php endif; ?>
<meta name="viewport" content="width=device-width">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link rel="shortcut icon" href="/favicon.ico">
<title>Whistler Form Reports</title>
<meta charset="UTF-8">
<?php echo $this->assets($assetSet)->css(); ?>
<?php echo $this->assets($assetSet)->js('head'); ?>