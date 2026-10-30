<?php get_header(); ?>
<main id="main-content" class="inner-page"><div class="container readable-content">
<?php while (have_posts()) : the_post(); ?>
  <article <?php post_class(); ?>><div class="eyebrow dark-eyebrow"><span class="eyebrow-line"></span>INSIGHTS</div><h1><?php the_title(); ?></h1><p class="post-date"><?php echo esc_html(get_the_date()); ?></p><div class="entry-content"><?php the_content(); ?></div></article>
<?php endwhile; ?>
</div></main>
<?php get_footer(); ?>
