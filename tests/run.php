<?php
declare(strict_types=1);
// Dependency-free tests for the pure helpers. Run: php tests/run.php
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/src/security.php';
require APP_ROOT . '/src/validate.php';
require APP_ROOT . '/src/domain.php';

$failures = 0;
function check(string $label, mixed $actual, mixed $expected): void
{
    global $failures;
    if ($actual !== $expected) {
        $failures++;
        echo "FAIL $label\n  expected: " . var_export($expected, true) . "\n  actual:   " . var_export($actual, true) . "\n";
    }
}

// validate
check('final_time m:ss', normalize_final_time('7:31'), '7:31');
check('final_time m.ss', normalize_final_time('7.31'), '7:31');
check('final_time pads', normalize_final_time('07:05'), '7:05');
check('final_time empty', normalize_final_time('  '), '');
check('final_time bad seconds', normalize_final_time('7:75'), null);
check('final_time junk', normalize_final_time('<script>'), null);
check('date ok', valid_date('2026-03-11'), '2026-03-11');
check('date impossible', valid_date('2026-02-30'), null);
check('date junk', valid_date('x'), null);
check('limits ok', valid_limits(300, 360, 420), [300, 360, 420]);
check('limits unordered', valid_limits(420, 360, 300), null);
check('limits negative', valid_limits(-1, 0, 1), null);
check('limits huge', valid_limits(0, 0, 999999), null);
check('email lowercases', valid_email(' A@B.com '), 'a@b.com');
check('email bad', valid_email('nope'), null);
check('clean_text trims+cuts', clean_text('  ' . str_repeat('x', 300) . ' ', 255), str_repeat('x', 255));

// escaping
check('h quotes', h('"><img src=x onerror=1>\''), '&quot;&gt;&lt;img src=x onerror=1&gt;&#039;');
check('js_value breaks out of script', str_contains(js_value('</script><script>alert(1)'), '<'), false);
check('js_value quotes', str_contains(js_value('"; alert(1);//'), '"; alert'), false);

// classification (role-name substring traps)
check('classify prepared', classify_speaker('Prepared Speech'), 'ps_speaker');
check('classify attitude is not TT', classify_speaker('Attitude Talk'), 'ps_speaker');
check('classify table topic', classify_speaker('Table Topic'), 'tt_speaker');
check('classify TTE', classify_speaker('TTE (O)'), 'tt_eval');
check('classify evaluator', classify_speaker('Evaluator'), 'ps_eval');
check('classify TT evaluator', classify_speaker('Table Topic Evaluator (Odd)'), 'tt_eval');
check('classify GE', classify_speaker('General Evaluator'), 'other');
check('classify ge prefix', classify_speaker('GE'), 'other');
check('classify gender not GE', classify_speaker('Gender Talk'), 'ps_speaker');
check('classify round robin', classify_speaker('Round Robin'), 'other');

// grading (min 300, max 420, grace 30)
check('grade qualified', grade_time('Prepared Speech', 300, 420, 360)['text'], '(QUALIFIED)');
check('grade grace under', grade_time('Prepared Speech', 300, 420, 280)['text'], '(GRACE -0:20)');
check('grade dq under', grade_time('Prepared Speech', 300, 420, 200)['disqualified'], true);
check('grade grace over', grade_time('Prepared Speech', 300, 420, 440)['text'], '(GRACE +0:20)');
check('grade dq over', grade_time('Prepared Speech', 300, 420, 500)['text'], '(+1:20 OVER)');
check('grade table topic no lower grace', grade_time('Table Topic', 60, 120, 40)['disqualified'], true);
check('grade bounds', [grade_time('Prepared Speech', 300, 420, 360)['lower'], grade_time('Prepared Speech', 300, 420, 360)['upper']], [270, 450]);

check('grade lower bound never negative', grade_time('Prepared Speech', 10, 60, 5)['lower'], 0);

// blog content integrity
require_once APP_ROOT . '/src/site.php';
foreach (blog_posts() as $slug => $post) {
    $body = blog_body($slug);
    check("blog $slug has body", $body !== null && $body !== '', true);
    check("blog $slug cover exists", is_file(APP_ROOT . '/public/' . $post['cover']), true);
    check("blog $slug cover PNG twin exists (Open Graph)", is_file(APP_ROOT . '/public/' . preg_replace('/\.svg$/', '.png', $post['cover'])), true);
    check("blog $slug body has no H1", str_contains((string)$body, '<h1'), false);
    preg_match_all('#src="(img/blog/[^"]+)"#', (string)$body, $m);
    $missing = array_filter($m[1], fn($f) => !is_file(APP_ROOT . '/public/' . $f));
    check("blog $slug images exist", array_values($missing), []);
}

echo $failures === 0 ? "All tests passed\n" : "$failures failure(s)\n";
exit($failures === 0 ? 0 : 1);
