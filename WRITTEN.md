# Written questions

Short, practical answers are best. Bullet points are fine. Aim for ~30 minutes total.

## 1. Least-privilege S3 access

Our backup job should be able to **upload new files to `s3://bsf-backups/site-x/`** and nothing else: no reading or deleting other backups, no access to other buckets.

a) Write the IAM policy (JSON) you'd attach.
b) The job runs on an EC2 instance / VPS. How should it get credentials? Why is that better than an access key in a script?

```json
// your policy here
```

## 2. Cloudflare "Error 525"

A customer moved their WordPress site to one of our servers. DNS is on Cloudflare (proxied, orange cloud). Since the move, visitors see **Error 525: SSL handshake failed**.

List what you'd check, in order, and what each check tells you.

## 3. Emails landing in spam

A customer's WooCommerce order emails are sent through **Amazon SES** and are landing in spam for Gmail users.

Which DNS records and SES settings would you check, and what should each look like?

## 4. cPanel server at 100% CPU

A shared cPanel/WHM server hosting 40 customer WordPress sites is at 100% CPU. Sites are slow. You have root SSH.

What do you do in your **first 10 minutes**? Be specific about commands/tools and what you're looking for.
