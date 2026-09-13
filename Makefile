.PHONY: up down build restart logs shell db migrate \
        prod-build prod-up prod-down prod-migrate prod-logs prod-shell

## Запустить контейнеры
up:
	docker compose up -d

## Остановить контейнеры
down:
	docker compose down

## Пересобрать образы
build:
	docker compose up -d --build

## Перезапустить
restart:
	docker compose restart

## Логи всех контейнеров
logs:
	docker compose logs -f

## Логи конкретного сервиса: make logs-php
logs-%:
	docker compose logs -f $*

## Войти в PHP контейнер
shell:
	docker compose exec php sh

## Войти в psql
db:
	docker compose exec postgres psql -U vibe -d vibe

## Установить Symfony (выполнить один раз)
install-symfony:
	docker compose exec php composer create-project symfony/skeleton . --no-interaction
	docker compose exec php composer require symfony/orm-pack symfony/validator symfony/security-bundle lexik/jwt-authentication-bundle

## Миграции
migrate:
	docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction

## Создать миграцию
migration:
	docker compose exec php php bin/console doctrine:migrations:diff

## Очистить кэш
cache:
	docker compose exec php php bin/console cache:clear

# ── Production ────────────────────────────────────────────────────────────

## Собрать прод-образ
prod-build:
	docker compose -f docker-compose.prod.yml build --no-cache

## Запустить прод
prod-up:
	docker compose -f docker-compose.prod.yml up -d

## Остановить прод
prod-down:
	docker compose -f docker-compose.prod.yml down

## Выполнить миграции в проде
prod-migrate:
	docker compose -f docker-compose.prod.yml exec php php bin/console doctrine:migrations:migrate --no-interaction

## Логи прода
prod-logs:
	docker compose -f docker-compose.prod.yml logs -f

## Shell в PHP-контейнере прода
prod-shell:
	docker compose -f docker-compose.prod.yml exec php sh
