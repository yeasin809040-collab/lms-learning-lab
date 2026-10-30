  <footer class="site-footer">
    <div class="container footer-grid">
      <div>
        <a class="brand brand-footer" href="<?php echo esc_url(home_url('/')); ?>">
          <span class="brand-mark" aria-hidden="true"><span></span><span></span></span>
          <span class="brand-wordmark">NORTHLINE <small>STUDIO</small></span>
        </a>
        <p>Thoughtful websites for real people and growing businesses.</p>
      </div>
      <div class="footer-end">
        <a href="<?php echo esc_url(home_url('/#services')); ?>">Services</a>
        <a href="<?php echo esc_url(home_url('/#about')); ?>">About</a>
        <a href="<?php echo esc_url(home_url('/#contact')); ?>">Contact</a>
      </div>
    </div>
    <div class="container footer-bottom">
      <small>&copy; <?php echo esc_html(date_i18n('Y')); ?> <?php bloginfo('name'); ?>.</small>
      <small>Fictional portfolio demonstration. No real client is represented.</small>
      <a href="#top">Back to top ↑</a>
    </div>
  </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
