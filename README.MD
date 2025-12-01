# <h1 align="center">Internal platform base</h1>
### <p align="center">Solution for building internal systems</p>
<p align="center">
    <a href="LICENSE" target="_blank">License</a> |
    <a href="https://becvar.xyz" target="_blank">Author website</a>
</p>

## Dashboard preview
![Dashboard](.github/assets/preview.gif)

## Usage example
An example usage of this base is the [admin-suite](https://github.com/lukasbecvar/admin-suite)

## Description
Base app template for building internal applications on symfony framework.
This template includes components: login system, user management, logs, account settings, basic dashboard, and other essential features needed in app to application development — for example, for intranet, ERP systems, and similar use cases.

## Tech stack
#### Development
The complete stack for development is in Docker Compose, including PHP, Node, and Composer. You just need to run the ``sh docker-start-dev.sh`` script to launch it.

## Index
- [Requirements](#requirements)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Notifications](#notifications)
- [Logging](#logging)
- [Dependencies](#dependencies)
- [License](#license)

## Requirements
#### Software
* PHP 8.5 and compatible web server
* MySQL 8 or higher
* Composer
* NPM
#### PHP extensions
* PHP-PDO extension (db driver)
* PHP-OpenSSL extension
* PHP-Intl extension
* PHP-Curl extension
* PHP-DOM extension

## Configuration
#### Environment variables
The environment variables are loaded from .env file. You can create .env file in root directory with your configuration.
| Variable | Description | Example value |
| --- | --- | --- |
| APP_ENV | Application environment | prod |
| APP_SECRET | Secret encryption key | 0cb9325e8b4b59a90249865085 |
| TRUSTED_HOSTS | Trusted url hosts | becvar.xyz,becvar.test |
| SSL_ONLY | Enable only ssl traffic | true |
| MAINTENANCE_MODE | Enable maintenance mode | true |
| PWA_APP_SUPPORT | Enable pwa app support | true |
| ADMIN_CONTACT | The system maintener contact email | admin@becvar.xyz |
| IP_INFO_API | API for get information about visitor ip | http://ip-api.com |
| ANTI_LOG_TOKEN | Token for disable logging | 1234567890 |
| DATABASE_LOGGING | Enable database logging | true |
| LOG_LEVEL | Log level | 4 |
| MEMORY_COST | Hash memory cost | 1024 |
| TIME_COST | Hash time cost | 10 |
| THREADS | Hash threads | 2 |
| LIMIT_CONTENT_PER_PAGE | Limit items per page | 10 |
| DATABASE_DRIVER | Database driver | pdo_mysql |
| DATABASE_HOST | Database host | localhost |
| DATABASE_PORT | Database port | 3306 |
| DATABASE_NAME | Database name | internal_platform |
| DATABASE_USERNAME | Database user | root |
| DATABASE_PASSWORD | Database password | root |
| MAILER_ENABLED | Enable mailer | true |
| MAILER_HOST | Mailer host | localhost |
| MAILER_PORT | Mailer port | 25 |
| MAILER_USERNAME | Mailer username | service@becvar.xyz |
| MAILER_PASSWORD | Mailer password | password |
| PUSH_NOTIFICATIONS_ENABLED | Enable push notifications | true |
| PUSH_NOTIFICATIONS_MAX_TTL | Push notifications max ttl | 86400 |
| PUSH_NOTIFICATIONS_CONTENT_ENCODING | Push notifications content encoding | aes-256-gcm |
| PUSH_NOTIFICATIONS_VAPID_PUBLIC_KEY | Public key for push notifications | 12938747T6T6R236 |
| PUSH_NOTIFICATIONS_VAPID_PRIVATE_KEY | Private key for push notifications | 12938747T6T6R236 |

## Authentication
Authentication uses a completely custom user system with a username and password, managed by the application user manager. Only the first user can use the registration form to create an admin user. Login uses standard PHP session and cookie for the 'remember me' feature.
#### API key authentication
Backend-to-backend consumers can call any `/api/**` route by reusing an existing user token as an API key and sending it via the `API-KEY` HTTP header. After the middleware validates the token, it hydrates the Symfony session, so the request runs with the same authorizations as if the user had passed through the HTML login form.

**Issuing and rotating tokens**
- Every user record has a randomly generated value in the `token` column. You can rotate it from *Users Manager → Regenerate authentication token* (route `/manager/users/token/regenerate`) or through your own automation by calling `AuthManager::regenerateSpecificUserToken`.
- API access is additionally guarded by the `allow_api_access` flag that you must enable to use the user-token as an API key.

**Using the header**
- Attach the header to any API call: `curl -H "API-KEY: <user-token>" https://internal_platform/api/system/resources`.
- Only `/api/**` paths accept header-based login. Browser sessions still rely on the standard cookie-based flow.
- Each API request is fully audited. Invalid or disabled tokens are logged under the `api-authentication` channel so you can track brute-force attempts.

## Notifications
Notifications from application are sent to admin users via web push notifications if the admin enables them. It also allows sending alerts directly to the admin's email.

## Logging
The system stores user actions in the database (respecting `LOG_LEVEL`, anti-log cookies, and `DATABASE_LOGGING`). You can also push structured events from other services through a dedicated API.

#### External log ingestion API
- **Endpoint**: `POST /api/external/log`
- **Authentication**: `API-KEY` header with a token that belongs to a user allowed to call the API
- **Payloads**: either query parameters (`name`, `message`, `level`) or an `application/xml` body with the same fields
- **Levels**: use the numeric constants from `LogManager` (`1=CRITICAL`, `2=WARNING`, `3=NOTICE`, `4=INFO`). Only levels lower than or equal to the configured `LOG_LEVEL` are saved.

Example with query parameters:
```bash
curl -X POST https://internal_platform/api/external/log \
  -H "API-KEY: <user-token>" \
  --data-urlencode "name=backup-service" \
  --data-urlencode "message=Backup finished in 142s" \
  --data-urlencode "level=4"
```

Example with XML payload:
```bash
curl -X POST https://internal_platform/api/external/log \
  -H "API-KEY: <user-token>" \
  -H "Content-Type: application/xml" \
  -d '<log><name>payment-worker</name><message>Bank API timeout</message><level>2</level></log>'
```

Successful requests return `{"status":"success"}`; missing fields, invalid XML, or bad credentials respond with `{"status":"error","message":"..."}` and the relevant HTTP code so you can react from the caller.

## Contributing
Contributions are welcome! Please fork this repository and submit pull requests for any features, bug fixes, or enhancements. If you have any questions or need help, feel free to open an issue or contact me directly.

## Dependencies
* PHP
    * [Website](https://php.net)
* Symfony with doctrine
    * [Website](https://symfony.com)
* PHPUnit
    * [Github](https://github.com/sebastianbergmann/phpunit)
* Better PHPUnit CLI output
    * [Github](https://github.com/robiningelbrecht/phpunit-pretty-print)
* PHPStan
    * [Github](https://github.com/phpstan/phpstan)
* Tailwind
    * [Website](https://tailwindcss.com)
* NPM / Webpack encore
    * [NPM](https://docs.npmjs.com)
    * [Encore](https://symfony.com/doc/current/frontend/encore/index.html)

## License
This software is licensed under the [MIT license](LICENSE). 
