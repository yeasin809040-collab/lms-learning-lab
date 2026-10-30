<!doctype html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="site-shell">
  <header class="site-header" id="top">
    <div class="container header-inner">
      <?php if (has_custom_logo()) : ?>
        <?php the_custom_logo(); ?>
      <?php else : ?>
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php echo esc_attr(get_bloginfo('name')); ?> — home">
          <span class="brand-mark" aria-hidden="true"><span></span><span></span></span>
          <span class="brand-wordmark">NORTHLINE <small>STUDIO</small></span>
        </a>
      <?php endif; ?>
      <button class="menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" aria-label="Open menu">
        <span></span><span></span><span></span>
      </button>
      <nav class="main-nav" id="primary-navigation" aria-label="Primary navigation">
        <?php if (has_nav_menu('primary')) : ?>
          <?php wp_nav_menu(array('theme_location' => 'primary', 'container' => false, 'menu_class' => 'nav-links')); ?>
        <?php else : ?>
          <ul class="nav-links">
            <li><a href="<?php echo esc_url(home_url('/#services')); ?>">Services</a></li>
            <li><a href="<?php echo esc_url(home_url('/#about')); ?>">About</a></li>
            <li><a href="<?php echo esc_url(home_url('/#approach')); ?>">Our approach</a></li>
          </ul>
        <?php endif; ?>
        <a class="nav-cta" href="<?php echo esc_url(home_url('/#contact')); ?>">Let's talk <span aria-hidden="true">↗</span></a>
      </nav>
    </div>
  </header>
