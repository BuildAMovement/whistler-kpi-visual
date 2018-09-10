<header class="header-bar">
  <div class="container__wide">
    <span class="header-bar__top-logo pull-right"></span>
    <a href="http://support.kobotoolbox.org/" class="header-bar__support pull-right hidden" target="_blank" title="Visit our self-help pages or ask a support question (opens in new tab)">Support</a>
      <span class="header-bar__top-level-menu-button">
        <i class="fa fa-bars fa-lg fa-inverse"></i>
      </span>
    <h1 class="header-bar__title"><a href="/"><span class="header-bar__title-text">Projects</span></a></h1>
  </div>
</header>

<section class="top-level-menu">
  <ul>
    <li class="top-level-menu__item">
      <a href="https://<?php echo preg_replace('~^collect\.~', 'admin.', $_SERVER['HTTP_HOST']); ?>/">
        <i class="header-bar__page-icon fa fa-fw fa-file-text-o"></i>
        Forms
      </a>
    </li>
    <li class="top-level-menu__item">
      <a href="https://<?php echo preg_replace('~^collect\.~', 'admin.', $_SERVER['HTTP_HOST']); ?>/#/library">
        <i class="header-bar__page-icon  fa  fa-fw  fa-folder"></i>
        Question Library
      </a>
    </li>

    <li class="top-level-menu__item">
      <a href="/">
        <i class="header-bar__page-icon fa fa-fw fa-globe"></i>
        Projects
      </a>
    </li>
    
    <li class="top-level-menu__item">
      <a href="/<?php echo $this->getRequest()->getParam('username'); ?>/settings">
        <i class="header-bar__page-icon fa fa-fw fa-cog"></i>
        Settings
      </a>
    </li>

    <li class="top-level-menu__item">
      <a href="/accounts/logout">
        <i class="header-bar__page-icon  fa  fa-fw  fa-sign-out"></i>
        Sign out
      </a>
    </li>

  </ul>
</section>
