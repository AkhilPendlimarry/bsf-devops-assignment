# Incident notes

Copy this block once per issue you found.

---

## Issue 1: WordPress website returns 502 Bad gateway

- **Symptom:** The website running at https://localhost:8443/ returned a 502 bad gateway response.
- **How I found it:** 
1. Inspected the WordPress Nginx config in web/default.conf
2. Checked the FastCGI upstream configuration using the command - docker compose logs --tail=100 web and identified upstream connection failures.
3. Compared the upstream port with the PHP-FPM listening port. 
4. Inspected Nginx logs and identified the connection failures to the PHP-FPM upstream. 
- **Root cause:** Nginx was forwarding PHP requests to WordPress on 9001, but the PHP-FPM was listening on port 9000. This port mismatch prevented nginx from communicating with PHP-FPM. 
- **Fix:** changed the fastcgi_pass from WordPress:9001 to 9000 in web/default.conf. 
- **Prevention:** Keep the Nginx FastCGI upstream port aligned with the PHP-FPM listening port and validate the Nginx configuration after changes. 
- **Verification:** Validated the Nginx configuration adn confirmed that the WordPress website loaded at https://localhost:8443/ successfully. 

---

## Issue 2: WordPress Admin wp-admin - Too many redirects
- **Symptom:** Opening https://localhost:8443/wp-admin/ resulted in repeated redirects to the same URL instead of loading the WordPress admin dashboard.
- **How I found it:** 
1. Checked the HTTP response headers using:
curl.exe -k -sS -D - -o NUL https://localhost:8443/wp-admin/
2. The response showed HTTP/1.1 302 Found and a Location header pointing back to the same HTTPS admin URL.
3. Inspected the WordPress HTTPS detection logic in wp-config.php.
4. Reviewed the reverse-proxy configuration in edge/nginx.conf.
5. Inspected the FastCGI configuration in web/default.conf.
6. Identified that the original request protocol was not being forwarded through the complete proxy chain.
- **Root cause:** Edge Nginx was not forwarding the X-Forwarded-Proto header to the WordPress web server. The web Nginx configuration was also not passing this information to PHP-FPM. Consequently, WordPress could incorrectly identify the original HTTPS request and repeatedly redirect the admin URL.
- **Fix:**
1. Added the following header to the Edge Nginx proxy configuration: proxy_set_header X-Forwarded-Proto $scheme;
2. Added the following FastCGI parameter in web/default.conf: fastcgi_param HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;
3. This allows WordPress to receive the original request protocol and identify HTTPS requests correctly.
- **Prevention:** Ensure that reverse proxies forward the original request protocol and that the application receives the correct HTTPS information. Include admin login and redirect checks during deployment testing.
- **Verification:**  Checked the admin URL response headers again and confirmed that the repeated redirect to the same URL was resolved and the WordPress admin page loaded successfully.
- **Files Changed:**
1. edge/nginx.conf
2. web/default.conf

## Issue 3: Intermittent 502 gateway on SaaS application

- **Symptom:** The Next.js application at `https://localhost:8443/app` intermittently returned 502 Bad Gateway. Refreshing the page sometimes worked and sometimes failed.

- **How I found it:**
  1. Inspected the Edge Nginx upstream configuration:
   ## Get-Content .\edge\nginx.conf
    
  2. Inspected the application port configuration in Docker Compose:
    ## Get-Content .\docker-compose.yml

  3. Checked the port configured inside app1:

    ## docker compose exec app1 sh -c "echo APP1; printenv PORT; wget -qO- http://127.0.0.1:3000/app/api/health"
     App1 returned a successful health response on port 3000.

  4. Checked the port configured inside app2:

    ## docker compose exec app2 sh -c "echo APP2; printenv PORT; wget -qO- http://127.0.0.1:3000/app/api/health"
     App2 showed `PORT=3001`, but the health request to port 3000 returned connection refused.

  5. Compared the application ports with the Nginx upstream configuration and identified the mismatch.

- **Root cause:** Edge Nginx was configured to forward requests to both `app1:3000` and `app2:3000`. However, Docker Compose configured app2 to listen on port 3001. Since Nginx distributed requests between both instances, requests sent to app2 on port 3000 failed and resulted in intermittent 502 responses.

- **Fix:** Updated the app2 upstream port in `edge/nginx.conf` from `3000` to `3001`, matching its Docker Compose configuration.

  **Before:**
  ```nginx
  upstream saas_app {
      server app1:3000;
      server app2:3000;
  }
  ```

  **After:**
  ```nginxex
  upstream saas_app {
      server app1:3000;
      server app2:3001;
  }
  ```

- **Verification:** Tested the app1 and app2 health endpoints on their respective ports and tested repeated requests through the Edge Nginx proxy to confirm that the application no longer intermittently returned 502.

- **Prevention:** Keep load-balancer upstream ports aligned with the actual application listening ports. Validate backend health and test repeated requests through the load balancer after configuration changes.

- **Files changed:**
  1. edge/nginx.conf


## Issue 4: Sales report blocks other WordPress requests

**Symptom:** When the sales report (`/?bsf=report`) was requested, other WordPress pages became slow.

**Evidence:**
- Sales report response: HTTP 200, approximately 8–11.5 seconds.
    ## curl.exe -k -sS -o NUL -w "HTTP: %{http_code} | Time: %{time_total}s`n" "https://localhost:8443/?bsf=report"
- Homepage response time 
    ## curl.exe -k -sS -o NUL -w "HTTP: %{http_code} | Time: %{time_total}s`n" "https://localhost:8443/"
- Inspected PHP-FPM pool configuration 
    ## Get-Content .\php\zz-bsf-pool.conf
- WordPress memory usage
    ## docker stats --no-stream bsf-stack-wordpress-1 
- Validated PHP-FPM config after changes
    ## docker compose exec wordpress php-fpm -tt
- Homepage during report execution before the fix: 6.37 seconds.
- Homepage during report execution after the fix: 0.60 seconds.

**Root cause:** PHP-FPM was configured with `pm = static` and `pm.max_children = 1`. Only one PHP request could execute at a time. The long-running sales report occupied the only worker, causing other requests to wait.

**Fix:** Changed PHP-FPM from static to dynamic process management:
- `pm.max_children = 3`
- `pm.start_servers = 2`
- `pm.min_spare_servers = 1`
- `pm.max_spare_servers = 2`

**Validation:** Repeated the concurrent report and homepage test. The homepage response improved from 6.37 seconds to 0.60 seconds while the report was running.

**Remaining observation:** The report itself still takes approximately 8–10 seconds. Its execution time has not been optimized; the fix prevents it from blocking other PHP requests.

**Files changed:** `php/zz-bsf-pool.conf`


## Issue 5: Export Orders returns HTTP 500 error/blank page

**Symptom:** The `/?bsf=export` endpoint returned HTTP 500 instead of downloading the CSV.

**Evidence:**
- Reproduced the export failure and captured the HTTP response headers and body:
  ## curl.exe -k -sS -D .\export-headers.txt -o .\export-response.txt -w "HTTP: %{http_code} | Time: %{time_total}s | Downloaded: %{size_download} bytes`n" "https://localhost:8443/?bsf=export"

- Inspected the WordPress debug log & the export handler in the third-party plugin without modifying it
    ## docker compose exec wordpress sh -c "tail -n 80 /var/www/html/wp-content/debug.log"
    ## Select-String -Path .\wordpress\plugins\bsf-shop\bsf-shop.php -Pattern "export|fputcsv|wp_die|memory_limit|set_time_limit" -Context 5,10
  
- Checked the database order count
    ## docker compose exec db mariadb -u root -p -e "SELECT COUNT(*) AS total_orders FROM wordpress.wp_bsf_orders;"
    total_orders
    2000000

**Root cause:** The third-party export handler loads up to 50,000 order records into memory using `$wpdb->get_results()`. The PHP-FPM pool had a memory limit of 48 MB, resulting in a PHP fatal error:

`Allowed memory size of 50331648 bytes exhausted`

**Fix:** Increased the PHP-FPM `memory_limit` from 48 MB to 256 MB in `php/zz-bsf-pool.conf`. Restarted the WordPress container to apply the setting.

**Validation:** PHP-FPM configuration test passed. The export endpoint returned HTTP 200 in approximately 2.10 seconds and downloaded 10,096,450 bytes.

**Files changed:** `php/zz-bsf-pool.conf`

**Status:** Resolved and verified.

---

## Issue 6: Redis object-cache writes fail with OOM errors

**Symptom:** WordPress generated Redis errors when attempting to write object-cache entries.

**Evidence:**
- Inspected the WordPress debug log
    ## docker compose exec wordpress sh -c "tail -n 80 /var/www/html/wp-content/debug.log"
    
    # error: Predis\Response\ServerException: OOM command not allowed when used memory > 'maxmemory'.
- Checked the Redis memory limit 
    ## docker compose exec redis redis-cli CONFIG GET maxmemory
    The limit is only 1 MB. and is using almost 1.08 MB against the limit of 1 MB. 

**Root cause:** Redis was configured with `maxmemory 1mb` and `maxmemory-policy noeviction`. Redis memory usage exceeded the configured limit, causing it to reject new cache writes causing the Redis object-cache errors.

**Fix:** Updated `redis/redis.conf` to use `maxmemory 256mb` and `maxmemory-policy allkeys-lru`. Recreated only the Redis container to apply the persistent configuration.

**Validation:** Redis reported a 256 MB memory limit, `allkeys-lru` policy, and responded with `PONG`. Subsequent WordPress homepage request returned HTTP 200. Redis statistics showed zero rejected connections and zero error replies. No new OOM entries found post fixes. 

**Files changed:** `redis/redis.conf`

**Status:** Resolved and verified.


## Issue 7: Slow Order Lookup

**Symptom:**  
The order lookup endpoint (`/?bsf=orders&email=customer42@example.com`) was taking approximately 13.72 seconds to respond.

**Investigation:**
- Inspected the third-party `bsf-shop` plugin without modifying it.
- Identified that the order lookup executes a query filtering by `customer_email` and sorting by `created_at DESC`.
- The page performs six lookups: one for the requested customer and five for the recent-customer widget.
- The `wp_bsf_orders` table contains 2 million demo orders.
- `SHOW INDEX` showed only the primary key on `id`.
- The initial `EXPLAIN` showed:
  - Access type: `ALL` (full table scan).
  - No usable index for the query.
  - Estimated rows: 1,026,158.
  - Extra: `Using where; Using filesort`.

**Root Cause:**  
The database table did not have an index on `customer_email` and `created_at`. MariaDB had to scan a large number of rows and sort matching records for each lookup.

**Fix Implemented:**
Created a composite database index:

## CREATE INDEX idx_customer_email_created_at ON wp_bsf_orders (customer_email, created_at);

Added the same index creation statement to `db/init/10-bsf-orders.sql` after the demo data insertion so fresh database initialization also creates the index.

**Validation:**
- Post-fix `EXPLAIN` uses `idx_customer_email_created_at`.
- Access type changed from `ALL` to `ref`.
- Estimated rows reduced from 1,026,158 to 40.
- `Using filesort` no longer appears.
- Endpoint before fix: HTTP 200 in 13.72 seconds.
- Endpoint after fix:
  - Run 1: HTTP 200 in 0.552 seconds.
  - Run 2: HTTP 200 in 0.124 seconds.
  - Run 3: HTTP 200 in 0.071 seconds.

**Result:**  
Order lookup performance improved significantly. The third-party plugin was left unchanged; the fix was implemented at the database and initialization-script level.

**Files changed:**
- db/init/10-bsf-orders.sql — added the composite index for fresh database initialization.
- Existing database — added the index through an ALTER TABLE operation.


## Issue 8: Problems Identified in the Backup Job

### 8.1. Database dump failure is not detected in the pipeline

**Original command:**

```sh
mariadb-dump -h db -u root -proot-pass-2026 --all-databases | gzip > "$FILE"
```

**Problem:**
The database dump and compression are combined into a single shell pipeline.
The script uses `set -e`, but this alone does not guarantee that a failure in `mariadb-dump` will stop the pipeline. If `mariadb-dump` fails while `gzip` successfully compresses its input, the pipeline can still return a successful exit status.
This can result in an empty or incomplete backup archive being uploaded while the script reports success.

**How we investigated it:**
We deliberately supplied an invalid database hostname to reproduce a database dump failure:

```sh
docker compose run --rm --no-deps --entrypoint sh backup -c 'set -e; mariadb-dump -h invalid-db-host -u root -ptest --connect-timeout=3 2>/tmp/dump-error.log | gzip > /tmp/failed-dump.sql.gz; echo PIPELINE_SUCCEEDED; ls -lh /tmp/failed-dump.sql.gz'
```

**Observed result:**

```text
PIPELINE_SUCCEEDED
-rw-r--r-- 1 root root 20 ... /tmp/failed-dump.sql.gz
CONTAINER_EXIT_CODE=0
```

The database dump failed, but `gzip` successfully created a small archive from the empty input. The pipeline returned exit code `0`, and the success message was printed.

**Root cause:**
The shell pipeline did not propagate the failure of the first command. The existing `set -e` setting was insufficient to catch this condition.


### 8.2. No backup integrity validation

**Original script:**

```sh
$S3 cp "$FILE" "s3://$BUCKET/site-x/"
```

**Problem:**
The original script uploads the compressed file immediately without validating its gzip integrity.
A backup file existing on disk does not guarantee that it is a valid, readable archive.

**How we investigated it:**
We downloaded the existing backup from MinIO:

```sh
aws --endpoint-url http://s3:9000 s3 cp \
  s3://bsf-backups/site-x/wordpress-20261106-113707.sql.gz \
  /tmp/backup-test.sql.gz
```

Then tested it:

```sh
gzip -t /tmp/backup-test.sql.gz
```

**Observed result:**
The archive passed the gzip integrity test.
This establishes that this particular backup was not corrupted at the gzip level. It does not prove that every backup is valid or that the SQL can be restored.


### 8.3. No restore verification

**Original script completion message:**

```sh
echo "[backup] done: s3://$BUCKET/site-x/$(basename "$FILE")"
```

**Problem:**
The script treats a successful upload as successful backup completion.
However, the actual purpose of a backup is to restore application data when required. A valid gzip archive and successful upload do not guarantee that the SQL dump can be restored.

**How we investigated it:**
We downloaded the backup and inspected its contents.
We found:
- MariaDB `mysql` system database.
- WordPress `wordpress` database.
- WordPress tables, including `wp_bsf_orders`.

**Verification status:**
A restore test has not yet been performed.


### 8.4. Backing up all databases instead of only the application database

**Original command:**

```sh
mariadb-dump --all-databases
```

**Problem:**
The backup includes MariaDB system databases and internal privilege tables in addition to the WordPress application database.
This introduces unnecessary coupling to MariaDB server internals and can make application recovery more complicated.

**Evidence:**
Inspection of the downloaded archive confirmed the presence of:

```sql
CREATE DATABASE `mysql`;
CREATE DATABASE `wordpress`;
```

**Suggested resolution:**

Back up the application database explicitly:

```sh
mariadb-dump --databases wordpress
```

This keeps the application backup focused on WordPress data. A separate full-server recovery strategy can be maintained if required.

### 8.5. Bucket creation suppresses all errors

**Original command:**

```sh
$S3 mb "s3://$BUCKET" 2>/dev/null || true
```

**Problem:**

The `|| true` expression suppresses every error and allows the script to continue.

Different situations are treated identically, including:
- Bucket already exists.
- Authentication failure.
- MinIO unavailable.
- Network error.
- Permission denied.

This can hide genuine backup failures.

**How we investigated it:**
We inspected the original script and identified the unconditional error suppression.
Authentication, permission and network failures have not yet been deliberately reproduced.


### 8.6. Hardcoded credentials

**Original backup script:**

```sh
export AWS_ACCESS_KEY_ID=bsfadmin
export AWS_SECRET_ACCESS_KEY=bsf-s3-root-secret
```

Database credentials are also embedded directly in the dump command:

```sh
mariadb-dump -h db -u root -proot-pass-2026
```

**Problem:**
Credentials are embedded in source-controlled shell code and are also present in `docker-compose.yml`.
Anyone with repository access can read these values. Credential rotation also requires modifying source files.

**How we investigated it:**
We inspected the backup script and Docker Compose configuration and confirmed that the credentials are literal values.


Previously exposed credentials should also be rotated.

### 8.7. No automated nightly schedule
The Docker Compose comment describes the backup as a nightly job:

```yaml
# Nightly backup job. Run manually with: docker compose run --rm backup
```

The actual service is configured as:

```yaml
backup:
  build: ./backup
  entrypoint: ["sh", "/backup.sh"]
  profiles: ["jobs"]
```

**Problem:**
No cron service, scheduler or other automatic trigger was found in the inspected Compose configuration.
The backup only runs when someone manually invokes it:

```sh
docker compose run --rm backup
```
Therefore, the configuration does not establish an automated nightly backup schedule.

**How we investigated it:**
We inspected the backup service configuration and found that it is under the `jobs` profile and is manually invoked.


### Overall Investigation Status

Finding - Investigation status
1. Dump failure masked by pipeline -  Reproduced and confirmed 
2. No gzip integrity validation - Confirmed; existing archive passed manual test 
3. No restore verification - Confirmed; restore test pendin |
4. All databases included - Confirmed by archive inspection 
5. Bucket errors suppressed - Confirmed by script inspection; failure scenarios not reproduced 
6. Hardcoded credentials - Confirmed by source inspection 
7. No automated nightly schedule - Confirmed by Compose configuration inspection 

