#!/usr/bin/env bash
# Integration smoke test against a running stack. Usage: tests/smoke.sh [http://localhost:8080]
# Registers two throwaway users and checks auth, CSRF, cross-user isolation and escaping.
set -u
B=${1:-http://localhost:8080}
W=$(mktemp -d); trap 'rm -rf "$W"' EXIT
C() { curl -s --noproxy '*' "$@"; }
tok() { C -b "$W/$1" -c "$W/$1" "$B/$2" | grep -oE '(name="csrf" value|name="csrf-token" content)="[a-f0-9]{64}"' | head -1 | grep -oE '[a-f0-9]{64}'; }
fail=0
expect() { # label actual expected
  if [ "$2" = "$3" ]; then echo "ok   $1"; else echo "FAIL $1 (got $2, want $3)"; fail=1; fi
}
code() { C -o /dev/null -w '%{http_code}' "$@"; }
EMAIL_A="a$RANDOM$RANDOM@example.test"; EMAIL_B="b$RANDOM$RANDOM@example.test"; PW=longenoughpw1

expect "signed-out home is the landing page" "$(code "$B/")" 200
C "$B/" | grep -q 'Create free account' && echo "ok   landing has sign-up call to action" || { echo "FAIL landing CTA"; fail=1; }
C -I "$B/" | grep -qi '^x-robots-tag: index' && echo "ok   landing indexable" || { echo "FAIL landing robots header"; fail=1; }
expect "signed-out meeting page redirects" "$(code "$B/meeting.php?id=1")" 302
expect "signed-out delete page redirects" "$(code "$B/delete_meeting.php?id=1")" 302
for p in src/db.php sql/schema.sql config.php Dockerfile README.md .git/config; do
  r=$(code "$B/$p"); [[ $r = 403 || $r = 404 ]] && echo "ok   $p not served ($r)" || { echo "FAIL $p served ($r)"; fail=1; }
done
C -I "$B/login.php" | grep -qi '^content-security-policy' && echo "ok   CSP header" || { echo "FAIL CSP header"; fail=1; }
C -I "$B/login.php" | grep -qi '^x-powered-by' && { echo "FAIL x-powered-by exposed"; fail=1; } || echo "ok   no x-powered-by"

T=$(tok a.jar login.php)
expect "register without csrf" "$(code -b $W/a.jar -c $W/a.jar -d "email=$EMAIL_A&password=$PW&password2=$PW" "$B/register.php")" 403
T=$(tok a.jar register.php)
expect "register A" "$(code -b $W/a.jar -c $W/a.jar --data-urlencode "csrf=$T" -d "email=$EMAIL_A&password=$PW&password2=$PW" "$B/register.php")" 302
T=$(tok b.jar register.php)
expect "register B" "$(code -b $W/b.jar -c $W/b.jar --data-urlencode "csrf=$T" -d "email=$EMAIL_B&password=$PW&password2=$PW" "$B/register.php")" 302

TA=$(tok a.jar index.php)
expect "A creates meeting" "$(code -b $W/a.jar -c $W/a.jar --data-urlencode "csrf=$TA" -d "create_meeting=1&name=M1&date=2026-11-11" "$B/index.php")" 302
expect "meeting with bad date rejected" "$(code -b $W/a.jar -c $W/a.jar --data-urlencode "csrf=$TA" -d "create_meeting=1&name=M2&date=2026-02-30" "$B/index.php")" 400
M=$(C -b $W/a.jar "$B/index.php" | grep -oE 'meeting.php\?id=[0-9]+' | head -1 | cut -d= -f2)
TA=$(tok a.jar "meeting.php?id=$M")
expect "A adds speaker" "$(code -b $W/a.jar -c $W/a.jar --data-urlencode "csrf=$TA" --data-urlencode 'name=Sam <b>x</b>' --data-urlencode 'role=Prepared Speech"; alert(1);//' -d "add_speaker=1&min_min=5&min_sec=0&target_min=6&target_sec=0&max_min=7&max_sec=0" "$B/meeting.php?id=$M")" 302
expect "unordered limits rejected" "$(code -b $W/a.jar -c $W/a.jar --data-urlencode "csrf=$TA" -d "name=X&role=Y&add_speaker=1&min_min=9&min_sec=0&target_min=6&target_sec=0&max_min=7&max_sec=0" "$B/meeting.php?id=$M")" 400
S=$(C -b $W/a.jar "$B/meeting.php?id=$M" | grep -oE 'timer.php\?id=[0-9]+' | head -1 | cut -d= -f2)

C -b $W/a.jar "$B/timer.php?id=$S" | grep -q 'Prepared Speech\\u0022; alert(1)' && echo "ok   role JSON-escaped in data-config" || { echo "FAIL role escaping on timer page"; fail=1; }
C -b $W/a.jar "$B/timer.php?id=$S" | grep -qF '"; alert(1)' && { echo "FAIL raw quote-breakout on timer page"; fail=1; } || echo "ok   no raw script breakout on timer page"
C -b $W/a.jar "$B/meeting.php?id=$M" | grep -q '<b>x</b>' && { echo "FAIL speaker name unescaped"; fail=1; } || echo "ok   speaker name escaped"

TB=$(tok b.jar index.php)
for u in "meeting.php?id=$M" "report.php?id=$M" "timer.php?id=$S"; do
  expect "B cannot open $u" "$(code -b $W/b.jar "$B/$u")" 404
done
expect "B cannot save A's time" "$(code -b $W/b.jar -H "X-CSRF-Token: $TB" -H 'X-Requested-With: fetch' -d "id=$S&time=5:12" "$B/api/save_time.php")" 404
expect "B cannot delete A's speaker" "$(code -b $W/b.jar --data-urlencode "csrf=$TB" -d "delete_speaker=1&speaker_id=$S" "$B/meeting.php?id=$M")" 404
expect "B cannot list A's event types" "$(code -b $W/b.jar -H "X-CSRF-Token: $TB" -d "action=get_types&meeting_id=$M" "$B/api/event_types.php")" 404

expect "GET save_time = 405" "$(code -b $W/a.jar "$B/api/save_time.php")" 405
expect "save_time without csrf" "$(code -b $W/a.jar -d "id=$S&time=5:12" "$B/api/save_time.php")" 403
expect "save_time bad time" "$(code -b $W/a.jar -H "X-CSRF-Token: $TA" -d "id=$S&time=abc" "$B/api/save_time.php")" 400
expect "save_time ok" "$(code -b $W/a.jar -H "X-CSRF-Token: $TA" -d "id=$S&time=05:12" "$B/api/save_time.php")" 200
C -b $W/a.jar "$B/report.php?id=$M" | grep -q '5:12' && echo "ok   report shows saved time" || { echo "FAIL report missing saved time"; fail=1; }
expect "unknown api action" "$(code -b $W/a.jar -H "X-CSRF-Token: $TA" -d "action=zzz" "$B/api/event_types.php")" 400
expect "logout needs POST" "$(code -b $W/a.jar "$B/logout.php")" 405

# Public marketing pages: reachable without login, navbar only on the login/public pages.
for p in login.php blog.php about.php contact.php; do expect "public $p without login" "$(code "$B/$p")" 200; done
C "$B/login.php" | grep -q 'class="site-nav"' && echo "ok   login page has navbar" || { echo "FAIL login navbar"; fail=1; }
C "$B/login.php" | grep -q 'by <strong>TheekMind</strong>' && echo "ok   login page says by TheekMind" || { echo "FAIL by TheekMind"; fail=1; }
C -b $W/a.jar "$B/index.php" | grep -q 'class="site-nav"' && { echo "FAIL navbar leaked into app page"; fail=1; } || echo "ok   no navbar inside the app"
C -b $W/a.jar "$B/index.php" | grep -q 'of [0-9]* used' && echo "ok   dashboard shows the usage meter" || { echo "FAIL usage meter"; fail=1; }
C -b $W/a.jar "$B/index.php" | grep -q 'Create free account' && { echo "FAIL signed-in user sees landing"; fail=1; } || echo "ok   signed-in home is the dashboard"
expect "delete page shows confirmation for owner" "$(code -b $W/a.jar "$B/delete_meeting.php?id=$M")" 200
expect "other user cannot open delete page" "$(code -b $W/b.jar "$B/delete_meeting.php?id=$M")" 404
expect "delete without csrf rejected" "$(code -b $W/a.jar -d "id=$M" "$B/delete_meeting.php")" 403
expect "export needs POST" "$(code -b $W/a.jar "$B/export.php")" 405
expect "export without csrf rejected" "$(code -b $W/a.jar -d "format=csv&ids[]=$M" "$B/export.php")" 403
expect "other user cannot export my meeting" "$(code -b $W/b.jar -c $W/b.jar --data-urlencode "csrf=$TB" -d "format=csv&ids[]=$M" "$B/export.php")" 400
C -b $W/a.jar --data-urlencode "csrf=$TA" -d "format=csv&ids[]=$M" "$B/export.php" | grep -q 'Meeting,Date' && echo "ok   owner can export CSV" || { echo "FAIL CSV export"; fail=1; }
C -b $W/a.jar --data-urlencode "csrf=$TA" -d "format=print&ids[]=$M" "$B/export.php" | grep -q 'class="sheet"' && echo "ok   owner can open print reports" || { echo "FAIL print export"; fail=1; }
C -b $W/a.jar --data-urlencode "csrf=$TA" -d "ids[]=$M" "$B/manage_meetings.php" | grep -q 'Review before deleting' && echo "ok   delete review page" || { echo "FAIL review page"; fail=1; }
for slug in $(C "$B/blog.php" | grep -oE 'blog.php\?post=[a-z0-9-]+' | sort -u | cut -d= -f2); do
  expect "blog post $slug" "$(code "$B/blog.php?post=$slug")" 200
done
expect "robots.txt" "$(code "$B/robots.txt")" 200
C "$B/robots.txt" | grep -q '^Sitemap: .*/sitemap.xml' && echo "ok   robots.txt names the sitemap" || { echo "FAIL robots sitemap line"; fail=1; }
expect "sitemap.xml" "$(code "$B/sitemap.xml")" 200
C "$B/sitemap.xml" | grep -q 'blog.php?post=role-evaluator' && echo "ok   sitemap lists blog posts" || { echo "FAIL sitemap posts"; fail=1; }
C "$B/sitemap.xml" | grep -qE 'index.php|meeting.php|login.php' && { echo "FAIL sitemap lists private pages"; fail=1; } || echo "ok   sitemap has only public pages"
C -I "$B/blog.php" | grep -qi '^x-robots-tag: index' && echo "ok   blog indexable" || { echo "FAIL blog robots header"; fail=1; }
C -I "$B/login.php" | grep -qi '^x-robots-tag: noindex' && echo "ok   login stays noindex" || { echo "FAIL login robots header"; fail=1; }
expect "favicon.ico" "$(code "$B/favicon.ico")" 200
expect "unknown blog post" "$(code "$B/blog.php?post=nope")" 404
expect "blog path traversal" "$(code "$B/blog.php?post=../../config")" 404
for p in src/site.php src/blog/posts.php src/blog/content/role-timer.html; do
  r=$(code "$B/$p"); [[ $r = 403 || $r = 404 ]] && echo "ok   $p not served ($r)" || { echo "FAIL $p served ($r)"; fail=1; }
done

for i in 1 2 3 4 5 6; do T=$(tok l.jar login.php); r=$(code -b $W/l.jar -c $W/l.jar --data-urlencode "csrf=$T" -d "email=$EMAIL_A&password=wrong-password" "$B/login.php"); done
T=$(tok l.jar login.php)
C -b $W/l.jar -c $W/l.jar --data-urlencode "csrf=$T" -d "email=$EMAIL_A&password=$PW" "$B/login.php" | grep -q 'Too many attempts' && echo "ok   login throttled after repeated failures" || { echo "FAIL login not throttled"; fail=1; }

# The throttle test leaves failures recorded against this IP; clear them so real logins aren't blocked.
if command -v docker >/dev/null && [ -f "$(dirname "$0")/../docker-compose.yml" ]; then
  (cd "$(dirname "$0")/.." && docker compose exec -T web php -r 'require "/var/www/html/src/bootstrap.php"; db()->query("DELETE FROM login_attempts");' >/dev/null 2>&1)
  # Remove the throwaway accounts (meetings and speakers cascade) so repeated runs don't pile up test data.
  (cd "$(dirname "$0")/.." && docker compose exec -T -e EA="$EMAIL_A" -e EB="$EMAIL_B" web php -r 'require "/var/www/html/src/bootstrap.php"; $s=db()->prepare("DELETE FROM users WHERE email IN (?, ?)"); $a=getenv("EA"); $b=getenv("EB"); $s->bind_param("ss",$a,$b); $s->execute();' >/dev/null 2>&1)
fi
exit $fail
