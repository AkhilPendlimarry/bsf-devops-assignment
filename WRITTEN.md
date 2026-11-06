# Written questions

Short, practical answers are best. Bullet points are fine. Aim for ~30 minutes total.

## 1. Least-privilege S3 access

Our backup job should be able to **upload new files to `s3://bsf-backups/site-x/`** and nothing else: no reading or deleting other backups, no access to other buckets.

a) Write the IAM policy (JSON) you'd attach.
b) The job runs on an EC2 instance / VPS. How should it get credentials? Why is that better than an access key in a script?

### a) IAM policy

Attach a policy like the following to the role used by the backup job. It grants only `s3:PutObject` on objects in the required prefix. It does not grant bucket listing, object reads, deletes, or access to other buckets.

```json
// your policy here
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "UploadOnlyToSiteXBackupPrefix",
      "Effect": "Allow",
      "Action": "s3:PutObject",
      "Resource": "arn:aws:s3:::bsf-backups/site-x/*"
    }
  ]
}
```
### b) Credential delivery

- **On EC2:** Attach an IAM role to the instance using an instance profile. The AWS SDK/CLI obtains short-lived credentials from the instance metadata service. There is no long-lived access key in the script or repository, and the credentials are rotated by AWS.
- **On a non-AWS VPS:** Prefer short-lived federated credentials, such as IAM Roles Anywhere where appropriate, or a secrets manager/secure workload identity supported by the hosting platform. If a static credential is unavoidable, store it in a secret manager, restrict its permissions and rotation, and inject it at runtime rather than committing it to source code.
- Keep the role limited to `PutObject` for `arn:aws:s3:::bsf-backups/site-x/*`. The process should not receive permissions to list, read or delete backups.

## 2. Cloudflare "Error 525"

A customer moved their WordPress site to one of our servers. DNS is on Cloudflare (proxied, orange cloud). Since the move, visitors see **Error 525: SSL handshake failed**.

List what you'd check, in order, and what each check tells you.

I would check in this order:

1. Confirm the origin address - Check the proxied DNS A/AAAA records and confirm they point to the new server's public IP. Check for a stale AAAA record sending IPv6 traffic to the wrong host.
2. Confirm origin port and reachability -  Verify that the origin is listening on TCP 443 and that the server/network firewall allows inbound HTTPS from Cloudflare's published IP ranges. A closed port or blocked Cloudflare source can prevent the handshake.
3. Test TLS directly against the origin with the correct SNI name - 
   # In powershell
   openssl s_client -connect ORIGIN_IP:443 -servername example.com -showcerts

   - This checks whether the origin completes a TLS handshake for the requested hostname and shows the certificate chain and TLS errors.

4. Check the certificate and virtual host - Confirm that the certificate is not expired, its SAN covers the customer hostname, the full certificate chain is installed, and the origin web server selects the correct TLS virtual host using SNI. With Cloudflare **Full (strict)** mode, the origin certificate must be valid and trusted by Cloudflare, or be a valid Cloudflare Origin CA certificate.
5. Check Cloudflare SSL/TLS mode - Use **Full (strict)** when the origin has a valid certificate. Do not use Flexible as a workaround for an origin HTTPS handshake problem. Confirm that the configured mode matches the origin's certificate setup.
6. Check TLS protocol and cipher compatibility - Ensure the origin supports modern TLS versions and cipher suites accepted by Cloudflare; inspect Nginx/Apache TLS configuration and error logs for handshake alerts.
7. Correlate logs and retest - Check the origin web-server error log at the exact time of a 525, then retest the origin handshake and the proxied hostname. This distinguishes network/firewall failure from certificate, SNI or TLS configuration failure.

## 3. Emails landing in spam

A customer's WooCommerce order emails are sent through **Amazon SES** and are landing in spam for Gmail users.

Which DNS records and SES settings would you check, and what should each look like?

I would check DNS authentication, identity alignment and SES sending health:

1. SPF - Inspect the TXT record for the envelope MAIL FROM domain. It should authorize SES, for example `v=spf1 include:amazonses.com ~all`, adjusted to the customer's other legitimate senders. There must be only one SPF record for a hostname; merge mechanisms into one record rather than publishing multiple SPF TXT records.
2. DKIM - Enable Easy DKIM for the SES identity and publish all three CNAME records supplied by SES. Verify that SES reports DKIM as successful and that messages contain a valid DKIM signature aligned with the visible From domain where possible.
3. DMARC - Check the TXT record at `_dmarc.example.com`. Start with a monitored policy such as `v=DMARC1; p=none; rua=mailto:dmarc-reports@example.com`, review aggregate reports, fix alignment failures, and then move to `quarantine` or `reject` when appropriate. Ensure at least SPF or DKIM passes with alignment to the From domain.
4. Custom MAIL FROM domain - If configured in SES, verify its MX record points to the SES feedback endpoint for the correct AWS Region and its SPF record authorizes SES. Confirm that the MAIL FROM domain is configured and verified in SES.
5. SES identity and Region - Confirm the sending domain/address is verified in the same SES Region used by the application, the account is out of the SES sandbox, and production sending is approved.
6. Bounce, complaint and suppression health - Review SES bounce and complaint metrics, account reputation, the account-level suppression list, and configuration-set event destinations. Remove invalid recipients and investigate complaints instead of repeatedly sending to them.
7. Message and application configuration - Confirm the From address uses the authenticated domain, avoid misleading From/Reply-To combinations, check message headers and content, and ensure WooCommerce is not sending through an unintended SMTP/API configuration or a different SES Region.

The target is authenticated, aligned mail (SPF/DKIM/DMARC), a healthy SES reputation, and clean recipient lists—not simply adding a DNS record without checking alignment.

## 4. cPanel server at 100% CPU

A shared cPanel/WHM server hosting 40 customer WordPress sites is at 100% CPU. Sites are slow. You have root SSH.

What do you do in your **first 10 minutes**? Be specific about commands/tools and what you're looking for.

### First 10 minutes

**In the first 2 mins, I Confirm the pressure and capture a baseline.**

```bash
 uptime
 top -c
 free -h
 vmstat 1 5
```

- Check load average, CPU user/system/iowait, runnable processes, memory pressure and swap activity.
- Do not reboot immediately; first identify which process and account are responsible.

**Minute 2–4: Identify the top processes and hosting accounts.**

```bash
ps -eo user,pid,ppid,pcpu,pmem,etime,cmd --sort=-pcpu | head -25
ps aux --sort=-%cpu | head -20
```
- Look for `php-fpm`, `lsphp`, `httpd`, `mysqld`, backup, antivirus or unfamiliar processes.
- Map the process owner to a cPanel account.

**Minute 4–6: Determine whether the web tier or database is responsible.**

```bash
mysqladmin processlist
mysql -e 'SHOW FULL PROCESSLIST;'
ss -tanp | head -50
```
- Check for long-running or repeated SQL & locked queries, too many database connections, and unusual connection volume.
- Check Apache/PHP-FPM status pages or WHM service status if available.

**Minute 6–8: Correlate processes with traffic and errors.**

```bash
tail -n 100 /usr/local/apache/logs/error_log
tail -n 100 /usr/local/apache/domlogs/DOMAIN-ACCESS-LOG
```

- Inspect the busiest virtual-host access logs and error logs.
- Look for one account or IP generating high request volume, repeated expensive WordPress endpoints, XML-RPC/login abuse, crawlers, 5xx errors, or a recently changed plugin/theme.
- Check cron jobs and scheduled backups if the CPU spike coincides with a scheduled task.

**Minute 8–10: Apply a narrow, reversible mitigation and preserve evidence.**

- If a single abusive source is confirmed, apply a targeted WAF/firewall or rate-limit rule rather than blocking broad customer traffic.
- If one compromised site/account is clearly responsible, isolate that account using the appropriate cPanel controls and notify the owner; do not terminate unrelated tenants.
- If a specific runaway worker is confirmed, use the service's graceful restart or terminate only that process after capturing its command, owner and logs.
- Record timestamps, top processes, account, IPs, relevant log lines and the mitigation. Continue root-cause analysis after service pressure is controlled.

The first ten minutes are for evidence-based triage and targeted containment. I would not run a blanket `killall`, disable MySQL, reboot the server, or suspend accounts without identifying the responsible workload.
