<?php get_header(); ?>
<main id="main-content" class="inner-page"><div class="container readable-content">
<?php while (have_posts()) : the_post(); ?>
  <div class="eyebrow dark-eyebrow"><span class="eyebrow-line"></span>NORTHLINE STUDIO</div>
  <h1><?php the_title(); ?></h1>
  <div class="entry-content"><?php the_content(); ?></div>
<?php endwhile; ?>
</div></main>
<?php get_footer(); ?>
