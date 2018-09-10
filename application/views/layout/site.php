<?php
/**
 * @var Controller\Base $this
 * @var string $content
 * 
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php echo $this->partial('html-head.php', ['assetSet' => 'site']); ?>
</head>
<body class="<?php echo $this->getCurrentControllerName(), ' ', \Ufw\Registry::getInstance()->get('bodyCssClass'); ?>">
<?php echo $this->assets('site')->js('body-begin'); ?>

<?php echo $this->partial('topbar.php'); ?>

<?php echo $content; ?>
    
<?php echo $this->assets('site')->js('body-bottom'); ?>
<?php echo \Ufw\JsOut::getInstance()->readyPlaceholder(); ?>
<?php echo $this->assets('site')->js('body-end'); ?>
</body>
</html>