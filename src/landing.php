<?php
// Signed-out home page. Included by public/index.php.
$guides = array_filter(blog_posts(), fn($slug) => str_starts_with($slug, 'role-') || $slug === 'rehearse-prepared-speech', ARRAY_FILTER_USE_KEY);
site_head(
    'Free timer for every Toastmasters role',
    'Green, yellow and red timing cards, role presets and a printable timing report for Toastmasters meetings. Free, with your own private account.',
    '/',
    'img/blog/timer-green.png'
);
?>
<body class="site landing">
    <?php site_nav('home'); ?>
    <main>
        <section class="hero">
            <div class="hero-copy">
                <p class="eyebrow">Toastmasters Timer by TheekMind</p>
                <h1>Time every role with confidence.</h1>
                <p class="lead">Full-screen green, yellow and red cards, the right limits for each role, and a timing report ready to print. Built by a club timer, for club timers.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="register.php">Create free account</a>
                    <a class="btn btn-secondary" href="login.php">Sign in</a>
                </div>
                <p class="hero-note">No install. Works on a laptop, a phone or a Zoom screen share.</p>
            </div>
            <img class="hero-shot" src="img/blog/timer-green.png" width="1100" height="700" alt="The timer showing a green card during a prepared speech">
        </section>

        <section class="band" id="how">
            <h2>From sign-up to report in three steps</h2>
            <ol class="steps">
                <li><span class="step-no">1</span><h3>Create your meeting</h3><p>Name it, set the date and the theme. It takes ten seconds and only you can see it.</p></li>
                <li><span class="step-no">2</span><h3>Add speakers and roles</h3><p>Pick a role and the limits fill in: Prepared Speech, Ice Breaker, Table Topics, Evaluators and more. Change them if your club does.</p></li>
                <li><span class="step-no">3</span><h3>Run the cards, print the report</h3><p>Start the timer, the card turns green, yellow then red. Afterwards print a clean A4 timing report.</p></li>
            </ol>
        </section>

        <section class="band alt">
            <h2>Cards everyone can read</h2>
            <p class="section-lead">The card changes colour at the role's minimum, target and maximum, with grace time on each side.</p>
            <div class="card-row">
                <figure><img src="img/blog/timer-green.png" width="1100" height="700" loading="lazy" alt="Green card: minimum time reached"><figcaption><strong>Green</strong> at the minimum</figcaption></figure>
                <figure><img src="img/blog/timer-yellow.png" width="1100" height="700" loading="lazy" alt="Yellow card: target time reached"><figcaption><strong>Yellow</strong> at the target</figcaption></figure>
                <figure><img src="img/blog/timer-red.png" width="1100" height="700" loading="lazy" alt="Red card: maximum time reached"><figcaption><strong>Red</strong> at the maximum</figcaption></figure>
            </div>
            <p><a href="blog.php?post=how-timing-works">How timing and grace work →</a></p>
        </section>

        <section class="band" id="roles">
            <h2>Start with your role</h2>
            <p class="section-lead">Short guides with setup screenshots, so you know exactly what to click before the meeting.</p>
            <div class="role-grid">
                <?php foreach ($guides as $slug => $post): ?>
                    <a class="role-card" href="blog.php?post=<?php echo h($slug); ?>">
                        <img src="<?php echo h($post['cover']); ?>" width="1200" height="675" loading="lazy" alt="">
                        <span><?php echo h($post['title']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="band alt trust">
            <h2>Private, light and free</h2>
            <ul class="ticks">
                <li>Your meetings are yours. Nobody else can open them.</li>
                <li>To keep this free service fast, meetings are removed <?php echo retention_days(); ?> days after their date. Your account stays.</li>
                <li>Delete any meeting yourself, any time.</li>
                <li>Built and maintained by <a href="<?php echo h(SITE_URL); ?>" rel="noopener">TheekMind</a>. Not affiliated with Toastmasters International.</li>
            </ul>
        </section>

        <section class="band cta-band">
            <h2>Ready for your next meeting?</h2>
            <div class="hero-actions">
                <a class="btn btn-primary" href="register.php">Create free account</a>
                <a class="btn btn-secondary" href="login.php">Sign in</a>
            </div>
            <dl class="faq">
                <dt>Is it free?</dt><dd>Yes. There is a fair limit of <?php echo max_meetings(); ?> meetings per account.</dd>
                <dt>Do I need to install anything?</dt><dd>No. It runs in your browser.</dd>
                <dt>How long are meetings kept?</dt><dd><?php echo retention_days(); ?> days after the meeting date, or until you delete them.</dd>
            </dl>
        </section>
    </main>
    <?php site_footer(); ?>
</body>
</html>
