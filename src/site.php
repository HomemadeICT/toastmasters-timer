<?php
declare(strict_types=1);

const SITE_URL = 'https://theekmind.com';
const SITE_LINKEDIN_URL = 'https://www.linkedin.com/in/theekshana-prabodha/';
const SITE_BLOG_URL = 'https://blogs.theekmind.com';
const SITE_CONTACT_EMAIL = 'homemadeict@gmail.com';

function site_base_url(): string
{
    $cfg = (string)(app_config()['base_url'] ?? '');
    if ($cfg !== '') {
        return rtrim($cfg, '/');
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if (!preg_match('/^[a-z0-9.-]+(:\d+)?$/i', $host)) {
        $host = 'localhost';
    }
    return (is_https() ? 'https' : 'http') . '://' . $host;
}

function favicon_links(): string
{
    return '<link rel="icon" href="' . h(asset('favicon.ico')) . '" sizes="any">' . "\n"
        . '    <link rel="icon" type="image/png" sizes="32x32" href="' . h(asset('icon-32.png')) . '">' . "\n"
        . '    <link rel="apple-touch-icon" href="' . h(asset('apple-touch-icon.png')) . '">' . "\n"
        . '    <link rel="manifest" href="site.webmanifest">' . "\n"
        . '    <meta name="theme-color" content="#0F2D5B">';
}

function site_nav(string $active = ''): void
{
    $links = [
        'blog'    => ['blog.php', 'Blogs'],
        'about'   => ['about.php', 'About us'],
        'contact' => ['contact.php', 'Contact me'],
    ];
    ?>
    <nav class="site-nav" aria-label="TheekMind">
        <a class="site-nav-brand" href="./">
            <img src="<?php echo h(asset('img/theekmind.png')); ?>" alt="" width="32" height="32">
            <span>TheekMind Timer</span>
        </a>
        <ul>
            <li><a class="site-nav-main" href="<?php echo h(SITE_URL); ?>" rel="noopener">theekmind.com <span aria-hidden="true">↗</span></a></li>
            <?php foreach ($links as $key => [$href, $label]): ?>
                <li><a href="<?php echo h($href); ?>"<?php echo $key === $active ? ' aria-current="page"' : ''; ?>><?php echo h($label); ?></a></li>
            <?php endforeach; ?>
            <?php if (current_user_id() === null): ?>
                <li><a href="login.php"<?php echo $active === 'login' ? ' aria-current="page"' : ''; ?>>Sign in</a></li>
                <li><a class="site-nav-cta" href="register.php">Create account</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php
}

/** $canonical (e.g. 'blog.php?post=x') marks a page as publicly indexable and adds canonical/Open Graph tags. */
function site_head(string $title, string $description = '', string $canonical = '', string $image = ''): void
{
    $full = $title . ' - Toastmasters Timer by TheekMind';
    if ($canonical !== '') {
        header('X-Robots-Tag: index, follow');
        $url = site_base_url() . '/' . ($canonical === '/' ? '' : $canonical);
        // Social crawlers don't render SVG, so Open Graph uses the PNG twin of each cover.
        $img = site_base_url() . '/' . ($image !== '' ? preg_replace('/\.svg$/', '.png', $image) : 'icon-512.png');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($full); ?></title>
    <?php if ($description !== ''): ?><meta name="description" content="<?php echo h($description); ?>"><?php endif; ?>
    <?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?php echo h($url); ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Toastmasters Timer by TheekMind">
    <meta property="og:title" content="<?php echo h($title); ?>">
    <meta property="og:description" content="<?php echo h($description); ?>">
    <meta property="og:url" content="<?php echo h($url); ?>">
    <meta property="og:image" content="<?php echo h($img); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <?php endif; ?>
    <?php echo favicon_links(); ?>
    <link rel="stylesheet" href="<?php echo h(asset('css/fonts.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/styles.css')); ?>">
    <link rel="stylesheet" href="<?php echo h(asset('css/site.css')); ?>">
</head>
<?php
}

function site_footer(): void
{
    ?>
    <footer class="site-footer">
        <p>Toastmasters Timer, built by <a href="<?php echo h(SITE_URL); ?>" rel="noopener">TheekMind</a>.
        <a href="<?php echo h(SITE_LINKEDIN_URL); ?>" rel="noopener">LinkedIn</a> ·
        <a href="login.php">Sign in</a> · <a href="register.php">Create account</a></p>
        <p class="site-footer-note">An independent tool. Not affiliated with or endorsed by Toastmasters International.</p>
    </footer>
    <?php
}

/** @return array<string,array{title:string,date:string,tag:string,excerpt:string,cover:string}> keyed by slug */
function blog_posts(): array
{
    static $posts = null;
    return $posts ??= require __DIR__ . '/blog/posts.php';
}

function blog_body(string $slug): ?string
{
    if (!isset(blog_posts()[$slug])) {
        return null;
    }
    $html = file_get_contents(__DIR__ . '/blog/content/' . $slug . '.html');
    return $html === false ? null : $html;
}
