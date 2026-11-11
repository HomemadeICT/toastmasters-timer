# Toastmasters Timer — Ubuntu Beginner & Developer Guide

## Active Status (As of June 21, 2026)
- **Current Setup**: Up and running on Docker.
- **Port Mapping**: Web interface at `http://localhost:8080` (mapped to port 80 in container), DB at `localhost:3306`.
- **Database Status**: Fully functional, tables seeded.

## Introduction
This guide helps an Ubuntu newcomer get the project running, develop locally, and perform common DevOps tasks.

1) Prerequisites (one-time)
- Ubuntu 20.04 / 22.04 recommended
- User with sudo privileges

2) Install Docker & Compose
```bash
# Install Docker
sudo apt update
sudo apt install -y ca-certificates curl gnupg lsb-release
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg
echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] \
  https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io
# Optional: Docker Compose plugin
sudo apt install -y docker-compose-plugin
# Allow current user to run Docker
sudo usermod -aG docker $USER
# Log out and back in for group changes to take effect
```

3) Move project to a native Linux path (important)
- Avoid using Windows-mounted drives (NTFS via fuse) as Docker bind mounts can behave unexpectedly.
```bash
mkdir -p ~/projects
# Move your project folder (adjust source path if necessary)
mv "/path/to/Toastmasters Timer" ~/projects/toastmasters-timer
cd ~/projects/toastmasters-timer
# Fix ownership
sudo chown -R $(id -u):$(id -g) .
```

4) Start the app (dev)
- The repo is set up to support both a baked image (production) and a bind-mounted dev environment.
- To start services and initialize the database (this will seed initial rows):
```bash
docker compose down -v
docker compose up -d --build
```
- Check the web app:
```bash
curl -I http://localhost:8080
# or open http://localhost:8080 in a browser
```

5) Common tasks
- View logs (live):
```bash
docker compose logs -f web
docker compose logs -f db
```
- Enter the DB container and run queries:
```bash
docker compose exec db bash
# inside container
mysql -uroot -prootpassword -e "USE toast_timer; SHOW TABLES; SELECT COUNT(*) FROM meetings;"
```
- Reset the DB (destructive — returns to seed state):
```bash
docker compose down -v
docker compose up -d --build
```

6) Backup & restore DB
- Backup:
```bash
# run from host
mkdir -p ~/backups
docker compose exec db bash -lc "mysqldump -uroot -p\$MYSQL_ROOT_PASSWORD toast_timer > /var/lib/mysql/backups/toast_timer_$(date +%F).sql"
# then copy out
docker cp $(docker compose ps -q db):/var/lib/mysql/backups/toast_timer_$(date +%F).sql ~/backups/
```
- Restore example:
```bash
# copy SQL into container then restore
docker cp ~/backups/toast_timer_2026-03-11.sql $(docker compose ps -q db):/tmp/restore.sql
docker compose exec db bash -lc "mysql -uroot -p\$MYSQL_ROOT_PASSWORD toast_timer < /tmp/restore.sql"
```

7) Development workflow
- With the project under `~/projects/toastmasters-timer` the `docker-compose.yml` bind mount allows live editing.
- Edit files in your editor (VS Code recommended) and refresh the browser. If you change PHP code, reload the page. If you change `Dockerfile`, rebuild with `docker compose up -d --build`.

8) Troubleshooting tips
- If Apache returns 403 or missing files, check the container mount:
```bash
docker compose exec web ls -la /var/www/html
```
- If DB init fails (no tables), ensure the `sql/schema.sql` is a regular file and the DB container had a chance to run initialization (check `docker compose logs db`).
- If permissions errors occur, fix ownership: `sudo chown -R $(id -u):$(id -g) ~/projects/toastmasters-timer`.

9) Useful Ubuntu commands
- Check disk usage: `df -h`
- Check Docker disk usage: `docker system df`
- Clean unused images/volumes: `docker system prune --volumes`

10) Next steps for a beginner operator
- Learn how to inspect logs, restart services, and take backups regularly.
- Consider adding a small systemd unit to start Docker Compose on boot or use Docker Compose's `restart` settings (already configured).


Support & links
- `DEVOPS.md` contains a production checklist and recommended changes.
- If you want, I can create a `devcontainer.json` so VS Code will open a remote container for development.

*** End Patch