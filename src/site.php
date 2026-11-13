<?php
declare(strict_types=1);

const SITE_URL = 'https://theekmind.com';
const SITE_LINKEDIN_URL = 'https://www.linkedin.com/in/theekshana-prabodha/';
const SITE_BLOG_URL = 'https://blogs.theekmind.com';
const SITE_CONTACT_EMAIL = 'homemadeict@gmail.com';
// The three TheekMind hosts are managed as one site; the registry is theekmind.com's resources/seo/sites.toml.
const SITE_PERSON_ID = SITE_URL . '/#person';
const SITE_SITEMAPS = [
    'https://theekmind.com/sitemap.xml',
    'https://blogs.theekmind.com/sitemap.xml',
    'https://blogs.theekmind.com/sitemap-pages.xml',
];

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

/** JSON-LD for the landing page (the app) and blog posts; both point at the shared TheekMind person. */
function site_jsonld(string $canonical, string $url, string $title, string $description, string $img, string $published): string
{
    $person = ['@type' => 'Person', '@id' => SITE_PERSON_ID, 'name' => 'Theekshana Prabodha', 'url' => SITE_URL . '/'];
    if ($canonical === '/') {
        $node = ['@type' => 'WebApplication', 'name' => 'Toastmasters Timer', 'url' => $url, 'description' => $description,
            'applicationCategory' => 'UtilitiesApplication', 'operatingSystem' => 'Any (web browser)', 'image' => $img,
            'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'], 'author' => $person, 'publisher' => $person];
    } elseif ($published !== '') {
        $node = ['@type' => 'BlogPosting', 'headline' => $title, 'description' => $description, 'url' => $url,
            'mainEntityOfPage' => $url, 'image' => $img, 'datePublished' => $published, 'inLanguage' => 'en',
            'author' => $person, 'publisher' => $person];
    } else {
        return '';
    }
    $json = json_encode(['@context' => 'https://schema.org'] + $node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    return '<script type="application/ld+json">' . $json . '</script>';
}

/** $canonical (e.g. 'blog.php?post=x') marks a page as publicly indexable and adds canonical/Open Graph tags. */
function site_head(string $title, string $description = '', string $canonical = '', string $image = '', string $published = ''): void
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
    <?php echo site_jsonld($canonical, $url, $title, $description, $img, $published); ?>
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
