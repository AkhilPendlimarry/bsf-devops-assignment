# Incident notes

Copy this block once per issue you found.

---

## Issue 1: WordPress website returns 502 Bad gateway

- **Symptom:** The website running at https://localhost:8443/ returned a 502 bad gateway response.
- **How I found it:** 
1. Inspected the wordpess Nginx config in web/default.conf
2. Checked the FastCGI upstream configuration using the command - docker compose logs --tail=100 web
docker compose exec web nginx -t
docker compose exec web nginx -s reload
3. Compared the upstream port with the PHP-FPM listening port. 
4. Inspected nginx logs and identified the connection failures to the PHP-FPM upstream. 
- **Root cause:** Nginx was forwarding PHP requests to wordpress on 9001, but the PHP-FPM was listening on port 9000. This port mismatch prevented nginx from communicating with PHP-FPM. 
- **Fix:** changed the fastcgi_pass from wordpress:9001 to 9000 in web/default.conf. 
- **Prevention:** Keep the nginx FastCGI upstream port aligned with the PHP-FPM listening port and validate the Nginx configuration after changes. 
- **Verification:** Validated the Nginx configuration adn confirmed that the Wordpress website loaded at https://localhost:8443/ successfully. 

---
