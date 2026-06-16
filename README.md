## Микросервис уведомлений (Notification Service)

```php
Цель этого задания — посмотреть, как ты проектируешь распределенные системы,
работаешь с асинхронными процессами и обеспечиваешь качество кода через
автоматизированное тестирование.
```

## ⚙️ Функциональные требования (Бизнес-требования)

```php
● Массовая рассылка уведомлений: Система должна предоставлять API для
запуска массовой отправки SMS или Email-сообщений. Инициатор запроса
передает канал связи, текст сообщения и массив идентификаторов
получателей.
● Приоритезация трафика: Система должна гарантировать доставку
критичных сообщений без задержек. Транзакционные уведомления (коды
доступа, срочные изменения маршрутов) должны получать наивысший
приоритет и отправляться вне очереди, обгоняя маркетинговые рассылки.
● Детализация статусов доставки: Система должна предоставлять API для
запроса истории и текущего статуса всех уведомлений конкретного
подписчика. Статусы:
○ в очереди (сообщение принято и ожидает отправки);
○ отправлено (передано шлюзу/провайдеру);
○ доставлено (подтверждено провайдером);
○ отброшено (ошибка доставки, несуществующий номер/email и т.д.).
 Нефункциональные требования (Архитектура и качество)
● Гарантия доставки сообщений (Reliability):
○ Персистентность: Использование брокеров сообщений для хранения
очереди.
○ Модель доставки: Поддержка семантики at-least-once. Огромным плюсом
будет реализация exactly-once на уровне бизнес-логики.
○ Retry-механизмы: Автоматический повтор попыток отправки при
временной недоступности шлюзов.
● Дедубликация (Idempotency): Защита от повторной отправки одного и того
же сообщения при дублировании запросов от вызывающего сервиса.
● Тестирование: Обязательное наличие интеграционных тестов на
основные сценарии. Мы хотим видеть автоматизированную проверку всей
цепочки: от получения сообщения из очереди до корректного изменения
статуса в базе данных и вызова нужного провайдера.
● Cloud Native и развертывание:
○ Упаковка сервиса в Docker-образ.
○ Весь проект (БД, брокер, кэш, приложение) должен запускаться одной
командой docker-compose up.
 Рекомендуемый технологический стек
● Язык и фреймворк: PHP (Laravel)
● База данных: PostgreSQL.
● Брокер сообщений: Apache Kafka или RabbitMQ.
● Кэш / In-memory хранилище: Redis (для дедубликации и контроля лимитов).
Примечание: для внешних шлюзов используй классы-заглушки (моки), которые
имитируют работу реальных провайдеров.
```

## 🚀 Как сдавать результат

```php
1. Опубликуй код в публичный репозиторий на GitLab или GitHub и пришли
ссылку.
2. В файле README.md опиши пошаговую инструкцию по запуску через
docker-compose.
3. Предоставь описание API для тестирования. Ссылка на Swagger (OpenAPI)
или приложенная Postman-коллекция будет идеальным решением.
Удачи! Мы ценим основательный подход к архитектуре и качеству кода.
Ждем твое решение!
```

## Документация API (OpenAPI / Swagger)

#### спецификация OpenAPI 3.0.

```yaml
openapi: 3.0.0
info:
  title: Notification Service API
  version: 1.0.0
paths:
  /api/v1/notifications/bulk:
    post:
      summary: Массовая отправка уведомлений
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [request_id, channel, priority, message, recipients]
              properties:
                request_id:
                  type: string
                  description: Уникальный ID запроса для идемпотентности
                  example: "batch-xyz-789"
                channel:
                  type: string
                  enum: [sms, email]
                priority:
                  type: string
                  enum: [transactional, marketing]
                message:
                  type: string
                  example: "Ваш код подтверждения: 4591"
                recipients:
                  type: array
                  items:
                    type: string
                  example: ["+79001234567", "+79007654321"]
      responses:
        '202':
          description: Запрос принят в обработку
          content:
            application/json:
              schema:
                type: object
                properties:
                  message:
                    type: string
                  accepted:
                    type: integer
                  total_requested:
                    type: integer
  /api/v1/notifications/{recipient_id}/history:
    get:
      summary: История и статусы уведомлений подписчика
      parameters:
        - name: recipient_id
          in: path
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Успешный ответ
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      type: object
                      properties:
                        id: { type: integer }
                        channel: { type: string }
                        status: { type: string, enum: [queued, sent, delivered, failed] }
                        priority: { type: string }
                        created_at: { type: string, format: date-time }
```

## 🏃‍♂️ Пошаговый запуск

#### 1. Клонируйте репозиторий:

```bash
git clone https://github.com/SlavKoVrn/Notifications notification-service
cd notification-service
```

#### 2. Скопируйте файл окружения:
```bash
cp .env.example .env

# Database (PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=notifications
DB_USERNAME=user
DB_PASSWORD=password

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue (RabbitMQ)
QUEUE_CONNECTION=rabbitmq
RABBITMQ_HOST=rabbitmq
RABBITMQ_PORT=5672
RABBITMQ_LOGIN=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/

*(Убедитесь, что в `.env` указаны `QUEUE_CONNECTION=rabbitmq`, `DB_CONNECTION=pgsql`)*
```

#### 3. Запустите всю инфраструктуру одной командой:
```bash
docker-compose up -d --build
```

#### 4. Установите зависимости PHP и сгенерируйте ключ приложения (выполнить внутри контейнера `notification_service_app`):
```bash
docker exec -it notification_service_app bash
composer install
php artisan key:generate
```

#### 5. Выполните миграции БД:
```bash
docker exec -it notification_service_app bash
php artisan migrate
```

#### 6. Запустите интеграционные тесты:
```bash
docker exec -it notification_service_app bash
php artisan test
```

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
