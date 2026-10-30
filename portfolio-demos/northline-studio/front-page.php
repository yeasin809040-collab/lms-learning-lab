<?php get_header(); ?>
<main id="main-content">
  <section class="hero">
    <div class="container hero-grid">
      <div class="hero-copy">
        <div class="eyebrow"><span class="eyebrow-line"></span><?php echo esc_html(get_theme_mod('northline_eyebrow', 'A SMALL STUDIO FOR BIG NEXT STEPS')); ?></div>
        <h1><?php echo esc_html(get_theme_mod('northline_heading', 'Make your next move')); ?> <em><?php echo esc_html(get_theme_mod('northline_heading_accent', 'a meaningful one.')); ?></em></h1>
        <p><?php echo esc_html(get_theme_mod('northline_intro', 'A thoughtful digital presence brings your story, services and next steps into focus. Let\'s create a website that feels distinctly yours.')); ?></p>
        <div class="hero-actions">
          <a class="button button-dark" href="#services">Explore what we do <span aria-hidden="true">↗</span></a>
          <a class="text-link" href="#about">Get to know us <span aria-hidden="true">↗</span></a>
        </div>
        <div class="hero-signature"><span class="small-star" aria-hidden="true">✳</span><span>Independent thinking.<br>Thoughtfully executed.</span></div>
      </div>
      <div class="hero-image-wrap">
        <img class="hero-image" src="<?php echo esc_url(get_template_directory_uri() . '/assets/studio-hero.jpg'); ?>" alt="Warm light-filled creative workspace with a wooden desk and plants" width="1440" height="960" fetchpriority="high">
        <div class="image-caption"><span class="caption-symbol" aria-hidden="true">N.</span><span>Great work starts with clarity.</span></div>
      </div>
    </div>
  </section>

  <section class="proof-bar" aria-label="Our values">
    <div class="container proof-items">
      <div><span class="proof-number">01 /</span><span>Clear communication</span></div>
      <div><span class="proof-number">02 /</span><span>Mobile-first thinking</span></div>
      <div><span class="proof-number">03 /</span><span>Made for your goals</span></div>
    </div>
  </section>

  <section class="section services-section" id="services">
    <div class="container">
      <div class="section-header">
        <div><div class="eyebrow dark-eyebrow"><span class="eyebrow-line"></span>WHAT WE CAN HELP WITH</div><h2>Good design is just<br>the <em>beginning.</em></h2></div>
        <p>Your website should do more than look beautiful. It should make your business easier to understand, navigate and trust.</p>
      </div>
      <div class="service-grid">
        <article class="service-card"><span class="service-number">01</span><span class="service-icon" aria-hidden="true">↗</span><h3>Business websites</h3><p>Purposeful layouts that introduce your brand, show what you do and guide visitors toward the next step.</p><a href="#contact">Let's discuss <span aria-hidden="true">↗</span></a></article>
        <article class="service-card"><span class="service-number">02</span><span class="service-icon" aria-hidden="true">◈</span><h3>Content & clarity</h3><p>Well-structured pages, readable sections and thoughtful details so people find what they came for.</p><a href="#contact">Let's discuss <span aria-hidden="true">↗</span></a></article>
        <article class="service-card"><span class="service-number">03</span><span class="service-icon" aria-hidden="true">✳</span><h3>Launch & support</h3><p>Responsive checks, straightforward handover and a clear plan for updates as your business grows.</p><a href="#contact">Let's discuss <span aria-hidden="true">↗</span></a></article>
      </div>
    </div>
  </section>

  <section class="section about-section" id="about">
    <div class="container about-grid">
      <div class="about-aside"><span class="asterisk" aria-hidden="true">✳</span><span class="vertical-note">A MORE HUMAN KIND OF DIGITAL</span></div>
      <div class="about-main"><div class="eyebrow dark-eyebrow"><span class="eyebrow-line"></span>OUR POINT OF VIEW</div><h2>Less noise.<br><em>More intention.</em></h2><p>We believe the strongest websites make things feel simple. They tell your story honestly, put your customers first, and give every page a reason to exist.</p><a class="button button-outline" href="#approach">See our approach <span aria-hidden="true">↗</span></a></div>
      <div class="about-card"><span class="mini-label">A NOTE ON THE PROCESS</span><div class="about-quote">“First, understand. Then, design.”</div><p>Every good project starts with the right questions—and an honest conversation about what success looks like.</p><div class="signature-line"></div><span class="small-text">The Northline approach</span></div>
    </div>
  </section>

  <section class="section approach-section" id="approach">
    <div class="container"><div class="eyebrow dark-eyebrow"><span class="eyebrow-line"></span>THE PROCESS</div><div class="approach-heading"><h2>Simple steps.<br><em>Stronger outcomes.</em></h2><p>A small, clear process that keeps the work focused and makes the decisions easier.</p></div><div class="step-list">
      <div class="step"><span>01</span><h3>Understand</h3><p>We start with your business, audience and must-haves.</p><span class="step-arrow" aria-hidden="true">↗</span></div>
      <div class="step"><span>02</span><h3>Design & build</h3><p>Turn the essentials into a distinctive, useful website.</p><span class="step-arrow" aria-hidden="true">↗</span></div>
      <div class="step"><span>03</span><h3>Test & launch</h3><p>Check the details and prepare for a confident handover.</p><span class="step-arrow" aria-hidden="true">↗</span></div>
    </div></div>
  </section>

  <section class="section contact-section" id="contact">
    <div class="container contact-grid"><div class="contact-copy"><div class="eyebrow"><span class="eyebrow-line"></span>LET'S START A CONVERSATION</div><h2><?php echo esc_html(get_theme_mod('northline_contact_heading', 'Let\'s make something clear and compelling.')); ?></h2><p>Tell us what you are building and what you want your website to do. We'll start with the essentials.</p><div class="contact-mark" aria-hidden="true">↗</div></div><div class="contact-form-panel">
      <?php
      // Contact form intentionally sends only when WordPress email is configured by the site owner.
      $contact_status = isset($_GET['contact']) ? sanitize_key(wp_unslash($_GET['contact'])) : '';
      if ($contact_status === 'sent') {
          echo '<p class="form-status success" role="status">Thank you. Your message has been sent.</p>';
      } elseif ($contact_status === 'invalid') {
          echo '<p class="form-status error" role="alert">Please check your details and try again.</p>';
      } elseif ($contact_status === 'slowdown') {
          echo '<p class="form-status error" role="alert">Please wait a minute before trying again.</p>';
      } elseif ($contact_status === 'error') {
          echo '<p class="form-status error" role="alert">Mail could not be sent. Please contact the site owner.</p>';
      }
      ?>
      <form class="contact-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="northline_contact">
        <?php wp_nonce_field('northline_contact', 'northline_nonce'); ?>
        <div class="hidden-trap" aria-hidden="true"><label for="website">Website</label><input type="text" name="website" id="website" tabindex="-1" autocomplete="off"></div>
        <label for="contact-name">Your name <span>*</span></label><input id="contact-name" name="name" autocomplete="name" type="text" required maxlength="120" placeholder="How should we call you?">
        <label for="contact-email">Email address <span>*</span></label><input id="contact-email" name="email" autocomplete="email" type="email" required maxlength="254" placeholder="you@example.com">
        <label for="contact-message">Tell us about your project <span>*</span></label><textarea id="contact-message" name="message" required maxlength="3000" rows="4" placeholder="A little about your business and what you need..."></textarea>
        <button class="button button-dark submit-button" type="submit">Send your message <span aria-hidden="true">↗</span></button>
        <p class="form-fine-print">Demo form: WordPress email delivery must be configured and tested before a real launch.</p>
      </form>
    </div></div>
  </section>
</main>
<?php get_footer(); ?>
