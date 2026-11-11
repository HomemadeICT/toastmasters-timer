<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/site.php';

site_head('Contact me', 'Get in touch with Theekshana at TheekMind.', 'contact.php');
?>
<body class="site">
    <?php site_nav('contact'); ?>
    <main class="site-main">
        <h1>Contact me</h1>
        <p class="lead">Questions, bug reports or ideas for the timer? I read everything.</p>
        <ul class="contact-list">
            <li><strong>Email</strong> <a href="mailto:<?php echo h(SITE_CONTACT_EMAIL); ?>"><?php echo h(SITE_CONTACT_EMAIL); ?></a></li>
            <li><strong>LinkedIn</strong> <a href="<?php echo h(SITE_LINKEDIN_URL); ?>" rel="noopener">Theekshana Prabodha</a></li>
            <li><strong>Website</strong> <a href="<?php echo h(SITE_URL); ?>" rel="noopener">theekmind.com</a></li>
            <li><strong>Blog</strong> <a href="<?php echo h(SITE_BLOG_URL); ?>" rel="noopener">blogs.theekmind.com</a></li>
        </ul>
        <p>If you report a bug, tell me which page you were on and what you expected to happen. A screenshot helps.</p>
    </main>
    <?php site_footer(); ?>
</body>
</html>
