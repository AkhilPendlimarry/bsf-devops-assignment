# How I used AI

## Tools used
e.g. Claude (claude.ai / Claude Code), others
- ChatGPT (OpenAI): Used as a technical learning and troubleshooting assistant to understand the existing application architecture, Docker environment, configuration files, and unfamiliar technical concepts.

- Docker Desktop, PowerShell, VS Code and Git: Used for inspecting the application, running containers, modifying configurations, testing changes, and managing commits.

## How I used them
A few bullets: what you asked for help with, and what you did yourself.
1. Used ChatGPT initially to understand the application's architecture, Redis, and MinIO interact.
2. Asked for explanations of the Docker Compose setup, container networking, port mappings, volumes, environment variables, and service dependencies.
3. Used AI to understand unfamiliar Docker commands, configuration options, application errors, and log messages.
4. Discussed possible root causes when investigating issues such as Redis memory errors, and slow database queries.

Used AI explanations to understand the purpose and potential impact of suggested configuration changes.

## What I did myself:
1. Explored the project structure and inspected the existing configuration files.
2. Started and monitored containers, reproduced application issues, and examined relevant logs.
3. Identified the affected services and investigated the underlying causes.
4. Made the required configuration and database changes.
5. Tested the application after each fix and compared results before and after changes.
6. Maintained separate Git commits for individual fixes and documented the investigation in INCIDENT.md.
7. AI suggestions were treated as references and possible approaches, not as instructions to apply without verification.

## Key prompts
2–5 of the most useful prompts (paste or summarise).

The following are representative prompts summarising how I used AI during the assignment:

# Understanding the application architecture:
"Explain the architecture of this application, how the different Docker containers communicate, and trace the request flow from the browser through Nginx to the application and backend services."

# Understanding Docker and the environment:
"Explain the Docker Compose configuration, including services, networks, volumes, port mappings, environment variables, and dependencies. Also explain the commands I can use to inspect and troubleshoot these containers."

# Investigating application errors:
"Help me understand this error and identify the possible root causes. Explain which configuration files, container logs, or services I should inspect to narrow down the issue before making changes."

# Understanding performance issues:
"Help me investigate why this application endpoint is responding slowly. Explain how I can identify whether the bottleneck is in PHP-FPM, the database query, or another service, and how to verify the improvement after a fix."

# Validating changes:
"Explain what this configuration change does, why it might resolve the issue, what impact it could have on other services, and how I can test whether the change has actually worked."

## When the AI was wrong or risky
**Required.** Describe at least one suggestion that was wrong, incomplete or unsafe, how you noticed, and what you did instead.

1. During the investigation of the PHP-FPM worker configuration, I asked AI for help understanding the configuration syntax and how to modify the worker settings.
2. An initial suggestion used # to comment out PHP-FPM pool configuration entries. After applying the change, the PHP-FPM container entered a restart loop.
3. I did not assume that the suggested configuration was correct. I inspected the container logs and identified a configuration parsing error caused by the comment syntax.
4. I corrected the comments to use the semicolon (;) syntax supported by PHP-FPM pool configuration files, validated the configuration, and restarted the affected service. I then verified that the container was running correctly and that the application was responding as expected.
5. This experience reinforced that AI-generated suggestions need to be checked against the actual software, configuration syntax, and application behaviour.

## What I'd never let an AI do unchecked on a production server
1–3 bullets.

1. Modify infrastructure or production configurations directly: Changes to Nginx, Docker, databases, networking, security rules, or application services must be reviewed, tested, and approved before deployment.
2. Execute destructive commands or database operations: Commands involving volume deletion, database migrations, data deletion, backups, restores, or permission changes must be understood and verified before execution.
3. Handle credentials or security-sensitive changes without review: Secrets, access policies, firewall rules, authentication settings, and production permissions must be handled securely and independently validated.

AI can help explain a problem and suggest possible solutions, but responsibility for understanding the impact, approving changes, and verifying the outcome must remain with the engineer.
