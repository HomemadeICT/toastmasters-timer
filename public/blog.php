<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/site.php';

$posts = blog_posts();
$slug = (string)($_GET['post'] ?? '');
$post = $slug !== '' ? ($posts[$slug] ?? null) : null;
if ($slug !== '' && $post === null) {
    abort(404, 'That post does not exist.');
}

site_head(
    $post['title'] ?? 'Blogs',
    $post['excerpt'] ?? 'Guides on Toastmasters timing, role by role.',
    $post !== null ? 'blog.php?post=' . urlencode($slug) : 'blog.php',
    $post['cover'] ?? '',
    $post['date'] ?? ''
);
?>
<body class="site">
    <?php site_nav('blog'); ?>
    <main class="site-main">
    <?php if ($post === null): ?>
        <h1>Blogs</h1>
        <p class="lead">Timing at Toastmasters, role by role, and how to use this timer to prepare. Start with the overview, then jump to your role.</p>
        <div class="post-grid">
            <?php foreach ($posts as $s => $p): ?>
                <a class="post-card" href="blog.php?post=<?php echo h(urlencode($s)); ?>">
                    <img src="<?php echo h(asset($p['cover'])); ?>" alt="" width="1200" height="675" loading="lazy">
                    <div class="post-card-body">
                        <span class="post-tag"><?php echo h($p['tag']); ?></span>
                        <h2><?php echo h($p['title']); ?></h2>
                        <p><?php echo h($p['excerpt']); ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="crumbs"><a href="blog.php">&larr; All blogs</a></p>
        <article class="post">
            <span class="post-tag"><?php echo h($post['tag']); ?></span>
            <h1><?php echo h($post['title']); ?></h1>
            <p class="post-date"><time datetime="<?php echo h($post['date']); ?>"><?php echo h(date('j F Y', strtotime($post['date']))); ?></time> · Theekshana Prabodha</p>
            <img class="post-cover" src="<?php echo h(asset($post['cover'])); ?>" alt="" width="1200" height="675">
            <div class="post-body"><?php echo blog_body($slug); /* trusted, repo-authored HTML */ ?></div>
        </article>
        <aside class="post-cta">
            <h2>Try the timer</h2>
            <p>Free to use. Create an account and set up your next meeting in a couple of minutes.</p>
            <a class="btn btn-primary" href="register.php">Create an account</a>
            <a class="btn btn-secondary" href="login.php">Sign in</a>
        </aside>
    <?php endif; ?>
    </main>
    <?php site_footer(); ?>
</body>
</html>
