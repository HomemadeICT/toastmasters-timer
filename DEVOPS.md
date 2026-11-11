# Toastmasters Timer — DevOps & Production Checklist

## Active Status (As of June 21, 2026)
- **Deployment Status**: Active, Running, and Verified.
- **Docker Compose Services**:
  - `web` (built using `Dockerfile` as `toastmasters-timer-web:latest`) -> Up on port `8080` (accessible at `http://localhost:8080`)
  - `db` (built using `Dockerfile-db` as `toastmasters-timer-db:latest`) -> Up on port `3306` (marked healthy)
- **Database Status**: Seeding and schemas successfully verified via container init scripts.
- **Verification Status**: Validated via HTTP requests (`curl -I http://localhost:8080` returns `200 OK`).

## Purpose
- Provide a concise, actionable production readiness checklist and DevOps guidance for deploying and operating the Toastmasters Timer web app.

Production Readiness Summary
- Current status: functional Dockerized app, MySQL schema and seed in `sql/schema.sql`, web app served by PHP-Apache image.
- Quick score: 70/100 — app is dockerized and works, but needs environment/config hardening, secrets management, backup/migration automation, monitoring, and CI/CD.

Critical Gaps (must fix before production)
- Secrets: Plaintext credentials in `docker-compose.yml`. Move to environment variables or secret store.
- TLS: No HTTPS termination. Use a reverse proxy (Traefik/Nginx) with Let's Encrypt.
- Persistent DB volume: Use named Docker volume for `/var/lib/mysql` (already configured by Compose local volume), ensure backups and retention.
- Automated backups: No scheduled DB backups. Add routine `mysqldump` snapshots stored off-host or in object storage.
- Health checks & readiness: `healthcheck` exists for DB; ensure web service has a health endpoint and Compose `depends_on` uses `service_healthy` only when meaningful.
- Logging & monitoring: No centralized logs or metrics. Add logging driver, or forward logs to a logging stack (ELK/Fluentd) and metrics (Prometheus/Grafana).

Recommended Production Architecture
- Reverse proxy: Deploy `nginx` or `traefik` in front of the `web` service to handle TLS, rate limiting, HSTS, and static caching.
- App image: Build `web` image that `COPY`s code (no bind mounts). Keep small and immutable.
- DB storage: Use Docker named volumes or managed DB (RDS, Cloud SQL). Schedule backups.
- Secrets: Use environment variables injected from a secret manager or use Docker secrets.
- CI/CD: Build images in CI, push to registry, deploy via `docker compose pull` + `docker compose up -d` or use GitHub Actions + deployment to host.

Concrete Changes to Make (priority order)
1. Secrets: replace credentials in `docker-compose.yml` with environment variable references and provide an example `.env.prod` (excluded from VCS).
2. TLS: add `nginx` reverse proxy with Let's Encrypt (or Traefik). Provide example configuration for cert renewal.
3. Backup: add `scripts/db-backup.sh` and a systemd timer or cronjob to run daily backups to `/backups` or S3.
4. Logging: configure `web` to log to stdout+stderr (already done via Apache) and collect using Docker logging driver or Fluent Bit.
5. Health endpoint: add a simple `health.php` that checks DB connectivity and returns 200.
6. CI/CD: add a `ci/` directory with a GitHub Actions workflow to build and push images and to run integration tests.

Example `docker-compose.prod.yml` (high level)

services:
  proxy:
    image: nginx:stable
    ports: [80:80, 443:443]
    volumes: [./nginx/conf.d:/etc/nginx/conf.d, ./certs:/etc/letsencrypt]
  web:
    image: toastmasterstimer/web:latest
    environment:
      - DB_HOST=db
      - DB_NAME=${DB_NAME}
      - DB_USER=${DB_USER}
      - DB_PASSWORD_FILE=/run/secrets/db_password
    secrets: [db_password]
    depends_on: [db]
  db:
    image: mysql:8.0
    volumes: [db_data:/var/lib/mysql]
    environment: [MYSQL_DATABASE=${DB_NAME}, MYSQL_USER=${DB_USER}, MYSQL_PASSWORD_FILE=/run/secrets/db_root_password]

volumes:
  db_data:

secrets:
  db_root_password:
    file: ./secrets/db_root_password
  db_password:
    file: ./secrets/db_password

Backup & Restore
- Backup (container run example):
  docker compose exec db bash -lc "mysqldump -uroot -p\$MYSQL_ROOT_PASSWORD toast_timer > /backups/toast_timer_$(date +%F).sql"
- Restore:
  docker compose exec db bash -lc "mysql -uroot -p\$MYSQL_ROOT_PASSWORD toast_timer < /backups/toast_timer_2026-03-11.sql"

CI/CD (GitHub Actions) — outline
- `build-and-push.yml` that:
  - Checks out code
  - Builds `web` and `db` images (db image optional)
  - Pushes to container registry
  - Optionally deploys to server by SSH and runs `docker compose pull && docker compose up -d`

Security hardening checklist
- Move secrets out of repo and into Docker secrets or environment variables managed by CI/CD.
- Set `ServerName` in Apache to avoid disclosure messages.
- Configure `Content-Security-Policy` and `X-Frame-Options` headers.
- Limit admin endpoints; add basic-auth for staging/prod where appropriate.
- Keep base images up-to-date and scan images with Snyk/Trivy.

Observability & Ops
- Logs: forward container logs to Fluent Bit -> Elasticsearch or choose a managed solution.
- Metrics: expose basic metrics (request rate, DB connections). Add Prometheus node exporter for host metrics.
- Alerts: configure alerting for unhealthy containers, high DB load, low disk space.

Runbook snippets (troubleshooting)
- Recreate the DB from the SQL seed (destructive):
  docker compose down -v
  docker compose up -d --build
- Inspect web logs:
  docker compose logs -f web
- Access DB shell:
  docker compose exec db mysql -utoast -ptoast123 toast_timer

Appendix: files I recommend adding
- `health.php` — simple 200/500 healthcheck
- `ci/build-and-push.yml` — GitHub Actions build pipeline
- `scripts/db-backup.sh` — simple dump script
- `nginx/` configs for TLS and proxying to `/index.php`


*** End Patch