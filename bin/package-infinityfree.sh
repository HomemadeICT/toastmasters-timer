#!/usr/bin/env bash
# Builds an FTP-ready folder for InfinityFree (or any host with a fixed htdocs/ web root and no SSH).
# Usage: bin/package-infinityfree.sh   ->  dist/infinityfree/{htdocs,database}
set -euo pipefail
cd "$(dirname "$0")/.."
OUT=dist/infinityfree
mkdir -p "$OUT/htdocs" "$OUT/database"

rsync -a --delete public/ "$OUT/htdocs/public/"
rsync -a --delete src/ "$OUT/htdocs/src/"
cp .htaccess "$OUT/htdocs/.htaccess"
cp sql/schema.sql "$OUT/database/schema.sql"

# Placeholder only: fill in the values from the InfinityFree control panel, then upload.
if [ ! -f "$OUT/htdocs/config.php" ]; then
  cat > "$OUT/htdocs/config.php" <<'PHP'
<?php
// InfinityFree: Control Panel -> MySQL Databases. The host is NOT localhost (e.g. sql123.infinityfree.com).
return [
    'db_host'     => 'sqlXXX.infinityfree.com',
    'db_name'     => 'if0_XXXXXXXX_timer',
    'db_user'     => 'if0_XXXXXXXX',
    'db_password' => 'CHANGE-ME',
];
PHP
fi

# Sanity checks on what will be uploaded.
bad=$(find "$OUT/htdocs" \( -name '.git*' -o -name '.env*' -o -name '*.md' -o -name 'Dockerfile*' -o -name '*.sql' -o -name '*.pdf' \) -print)
[ -z "$bad" ] || { echo "Refusing: unexpected files in package:"; echo "$bad"; exit 1; }
for f in .htaccess public/.htaccess src/.htaccess public/index.php public/login.php src/bootstrap.php config.php; do
  [ -f "$OUT/htdocs/$f" ] || { echo "Missing $f"; exit 1; }
done

echo "Files: $(find "$OUT/htdocs" -type f | wc -l), size: $(du -sh "$OUT/htdocs" | cut -f1)"
echo "1. phpMyAdmin: import $OUT/database/schema.sql into your database."
echo "2. Edit $OUT/htdocs/config.php with your database details (it is not overwritten on re-run)."
echo "3. FTP the *contents* of $OUT/htdocs/ into the htdocs/ folder on the host."
