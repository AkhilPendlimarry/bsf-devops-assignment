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