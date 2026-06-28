.PHONY: up down build rebuild logs ps config health restart clean

up:
	docker compose up -d --build

down:
	docker compose down

build:
	docker compose build

rebuild:
	docker compose down
	docker compose up -d --build

logs:
	docker compose logs -f

ps:
	docker compose ps

config:
	docker compose config

health:
	docker compose ps
	curl http://localhost:3000/health

restart:
	docker compose restart

clean:
	docker compose down --remove-orphans
