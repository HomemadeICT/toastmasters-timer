<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/site.php';

site_head('About us', 'Who builds the Toastmasters Timer and why.', 'about.php');
?>
<body class="site">
    <?php site_nav('about'); ?>
    <main class="site-main">
        <h1>About us</h1>
        <p class="lead">Hey, I'm Theekshana, welcome to TheekMind.</p>
        <div class="post-body">
            <p>TheekMind is where I share what I learn, build and explore in tech and beyond. The idea is in the tagline: build the skills, create the future.</p>
            <p>The Toastmasters Timer is one of the things I built along the way. I run meeting timing for my club, and I wanted something that shows the cards clearly on a projector or a Zoom call, remembers each role's limits, and prints a report when the meeting ends.</p>
            <h2>What the timer does</h2>
            <ul>
                <li>Full-screen green, yellow and red cards that change at each role's limits.</li>
                <li>Built-in limits for the common meeting roles, and custom limits for anything else.</li>
                <li>A roster you can reorder during the meeting.</li>
                <li>An A4 timing report with qualified, grace and disqualified results.</li>
                <li>Your own account: you only ever see your own meetings.</li>
                <li>Automatic tidy-up: meetings are removed <?php echo retention_days(); ?> days after their date to keep the free service fast. Accounts are kept, and you can delete meetings yourself any time.</li>
            </ul>
            <h2>Where to find more</h2>
            <p>Read <a href="blog.php">the blogs</a> for role-by-role timing guides, visit <a href="<?php echo h(SITE_URL); ?>" rel="noopener">theekmind.com</a>, connect with me on <a href="<?php echo h(SITE_LINKEDIN_URL); ?>" rel="noopener">LinkedIn</a>, or <a href="contact.php">get in touch</a>.</p>
            <p class="site-footer-note">This is an independent tool. It is not affiliated with or endorsed by Toastmasters International.</p>
        </div>
    </main>
    <?php site_footer(); ?>
</body>
</html>
