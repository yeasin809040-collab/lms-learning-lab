<?php get_header(); ?>
<main id="main-content" class="inner-page"><div class="container readable-content">
  <div class="eyebrow dark-eyebrow"><span class="eyebrow-line"></span>LATEST ARTICLES</div>
  <h1><?php echo esc_html(get_the_archive_title() ?: get_bloginfo('name')); ?></h1>
  <?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
      <article <?php post_class('post-card'); ?>><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p class="post-date"><?php echo esc_html(get_the_date()); ?></p><div><?php the_excerpt(); ?></div></article>
    <?php endwhile; ?>
    <?php the_posts_pagination(); ?>
  <?php else : ?>
    <p>No posts published yet.</p>
  <?php endif; ?>
</div></main>
<?php get_footer(); ?>
