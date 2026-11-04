# Brainstorm Force: DevOps Take-Home Assignment

Thanks for applying! This assignment is a small copy of the kind of stack you'd look after with us: WordPress on Nginx + PHP-FPM, MariaDB, Redis, a Next.js SaaS app behind a load balancer, and S3 backups.

**It's broken.** Your job is to find out why, fix it properly, and explain what you did.

- **Time:** plan for about **3–4 hours** of focused work. Please don't spend much more; we care about how you think, not polish.
- **Deadline:** 3 days from when you receive it.
- **AI tools are allowed and encouraged.** We use Claude every day. Use it however you like. We'll ask you to tell us how you used it (see `AI-LOG.md`).

---

## 1. Requirements

- Docker Desktop (or Docker Engine) with Docker Compose v2
- ~4 GB free RAM, ~5 GB disk
- Works on macOS (Intel/Apple Silicon), Linux, and Windows with WSL2

## 2. Start the stack

First, unzip the folder and commit it as a baseline (you'll push this repo to GitHub when you submit, see section 5) **before changing anything**, so each fix shows up as its own commit:

```bash
git init && git add -A && git commit -m "baseline"
```

Then start the stack:

```bash
docker compose up -d --build
docker compose logs -f setup      # wait until you see "[setup] done"
```

The first start takes about 5–10 minutes, mostly downloading ~1 GB of images; then the database seeds ~2M demo orders and WordPress installs. Later starts (including after `docker compose down -v`) take under a minute.

| What | URL / access |
|---|---|
| WordPress site | https://localhost:8443 (self-signed certificate, accept the browser warning) |
| WordPress admin | https://localhost:8443/wp-admin (user `admin`, password `admin-pass-2026`) |
| SaaS app (Next.js) | https://localhost:8443/app |
| S3 console (MinIO) | http://localhost:9001 |
| Backup job | `docker compose run --rm backup` |

Useful commands:

```bash
docker compose ps
docker compose logs <service>
docker compose exec <service> sh
docker compose restart <service>
docker compose down -v      # full reset (deletes all data)
```

## 3. What's reported as broken

These came in from the team and from customers this morning. They are symptoms, not diagnoses.

1. "The website just shows **502 Bad Gateway**."
2. "Browser says **too many redirects**, at least on wp-admin."
3. "When someone opens the **sales report** (`/?bsf=report`), the whole site hangs for everyone."
4. "**Export orders** (`/?bsf=export`) gives a blank page / error."
5. "**Order lookup** (`/?bsf=orders&email=customer42@example.com`) takes forever."
6. "The **SaaS app** at `/app` fails randomly. Refresh a few times and you'll see it."
7. "The **object cache** plugin is showing errors."

Also:

- We suspect there are **security problems** in this setup that nobody has reported. Find what you can.
- The **backup job** "works", but we don't trust it. Review it and fix what's wrong.

There are **at least 6** distinct root causes. Some issues hide others, so expect to peel layers.

### Rules

- Treat the `bsf-shop` plugin's PHP code as third-party: **don't edit it.** Fix the platform around it.
- Don't "fix" things by removing features (e.g. deleting the report page).
- Keep fixes realistic: what you'd actually do on a production server.

## 4. Written questions

Answer the questions in **`WRITTEN.md`** (about 30 minutes). No infrastructure needed.

## 5. What to submit

1. Your fixes as **git commits**, ideally **one commit per issue** with a clear message.
2. **`INCIDENT.md`**: one entry per issue (template inside).
3. **`AI-LOG.md`**: how you used AI (template inside).
4. **`WRITTEN.md`**: your answers.

To submit:

1. Create a **private** repository on your own GitHub account (don't make it public).
2. Push your work to it, including the `baseline` commit from step 2 and your fix commits on top of it.
3. Add the GitHub usernames the recruiter gave you as **collaborators** (Settings → Collaborators → Add people).
4. Reply to the recruiter with the repository URL.

## 6. What happens next

We'll review your submission, then book a **30–45 minute call** where you walk us through two of your fixes and we debug one new issue together, screen-shared. Using AI during the call is fine.

## 7. How we evaluate

- Did you find and fix the real root causes (not just the symptoms)?
- Can you explain *why* something broke and how to prevent it?
- Do your fixes make the stack safer and more reliable, or just make the error go away?
- How well do you use AI: do you verify its suggestions?
- Clear, small commits and clear writing.

Questions about the assignment itself? Email the recruiter who sent it. Good luck!
