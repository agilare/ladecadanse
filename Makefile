# Ladecadanse Docker Management Makefile
# Usage: make [target] [PROFILE=dev|prod]

.PHONY: help dev prod build-dev build-prod start-dev start-prod stop-dev stop-prod restart-dev restart-prod logs-dev logs-prod shell-dev shell-prod clean-dev clean-prod status-dev status-prod install-deps

# Default profile
PROFILE ?= dev

# Docker Compose commands
DC_DEV = docker-compose --profile dev
DC_PROD = docker-compose --profile prod

# Colors for output
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
NC = \033[0m # No Color

help: ## Show this help message
	@echo "Ladecadanse Docker Management"
	@echo "============================="
	@echo ""
	@echo "Usage: make [target] [PROFILE=dev|prod]"
	@echo ""
	@echo "Development targets:"
	@echo "  dev              Start development environment"
	@echo "  build-dev        Build development images"
	@echo "  start-dev        Start development services"
	@echo "  stop-dev         Stop development services"
	@echo "  restart-dev      Restart development services"
	@echo "  logs-dev         Show development logs"
	@echo "  shell-dev        Open shell in development container"
	@echo "  clean-dev        Clean development environment"
	@echo "  status-dev       Show development status"
	@echo ""
	@echo "Production targets:"
	@echo "  prod             Start production environment"
	@echo "  build-prod       Build production images"
	@echo "  start-prod       Start production services"
	@echo "  stop-prod        Stop production services"
	@echo "  restart-prod     Restart production services"
	@echo "  logs-prod        Show production logs"
	@echo "  shell-prod       Open shell in production container"
	@echo "  clean-prod       Clean production environment"
	@echo "  status-prod      Show production status"
	@echo ""
	@echo "General targets:"
	@echo "  install-deps         Install PHP dependencies (development)"
	@echo "  install-deps-prod    Install PHP dependencies (production)"
	@echo "  composer-update      Update Composer dependencies"
	@echo "  composer-require     Add a new Composer package"
	@echo "  help                 Show this help message"
	@echo ""
	@echo "Examples:"
	@echo "  make dev                    # Start development"
	@echo "  make prod                   # Start production"
	@echo "  make build-dev              # Build dev images"
	@echo "  make logs-prod              # Show prod logs"
	@echo "  make shell-dev              # Open dev shell"

# Development targets
dev: ## Start development environment
	@echo "$(GREEN)[INFO]$(NC) Starting development environment..."
	$(DC_DEV) up -d
	@echo "$(GREEN)[INFO]$(NC) Development environment started!"

build-dev: ## Build development images
	@echo "$(GREEN)[INFO]$(NC) Building development images..."
	$(DC_DEV) build --no-cache
	@echo "$(GREEN)[INFO]$(NC) Development images built!"

start-dev: ## Start development services
	@echo "$(GREEN)[INFO]$(NC) Starting development services..."
	$(DC_DEV) up -d
	@echo "$(GREEN)[INFO]$(NC) Development services started!"

stop-dev: ## Stop development services
	@echo "$(GREEN)[INFO]$(NC) Stopping development services..."
	$(DC_DEV) down
	@echo "$(GREEN)[INFO]$(NC) Development services stopped!"

restart-dev: ## Restart development services
	@echo "$(GREEN)[INFO]$(NC) Restarting development services..."
	$(DC_DEV) restart
	@echo "$(GREEN)[INFO]$(NC) Development services restarted!"

logs-dev: ## Show development logs
	@echo "$(GREEN)[INFO]$(NC) Showing development logs..."
	$(DC_DEV) logs -f

shell-dev: ## Open shell in development container
	@echo "$(GREEN)[INFO]$(NC) Opening shell in development container..."
	$(DC_DEV) exec web-dev /bin/bash

clean-dev: ## Clean development environment
	@echo "$(YELLOW)[WARN]$(NC) This will remove all development containers, images, and volumes."
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	@echo "$(GREEN)[INFO]$(NC) Cleaning development environment..."
	$(DC_DEV) down -v --rmi all
	docker system prune -f
	@echo "$(GREEN)[INFO]$(NC) Development environment cleaned!"

status-dev: ## Show development status
	@echo "$(GREEN)[INFO]$(NC) Development environment status:"
	$(DC_DEV) ps

# Production targets
prod: ## Start production environment
	@echo "$(GREEN)[INFO]$(NC) Starting production environment..."
	$(DC_PROD) up -d
	@echo "$(GREEN)[INFO]$(NC) Production environment started!"

build-prod: ## Build production images
	@echo "$(GREEN)[INFO]$(NC) Building production images..."
	$(DC_PROD) build --no-cache
	@echo "$(GREEN)[INFO]$(NC) Production images built!"

start-prod: ## Start production services
	@echo "$(GREEN)[INFO]$(NC) Starting production services..."
	$(DC_PROD) up -d
	@echo "$(GREEN)[INFO]$(NC) Production services started!"

stop-prod: ## Stop production services
	@echo "$(GREEN)[INFO]$(NC) Stopping production services..."
	$(DC_PROD) down
	@echo "$(GREEN)[INFO]$(NC) Production services stopped!"

restart-prod: ## Restart production services
	@echo "$(GREEN)[INFO]$(NC) Restarting production services..."
	$(DC_PROD) restart
	@echo "$(GREEN)[INFO]$(NC) Production services restarted!"

logs-prod: ## Show production logs
	@echo "$(GREEN)[INFO]$(NC) Showing production logs..."
	$(DC_PROD) logs -f

shell-prod: ## Open shell in production container
	@echo "$(GREEN)[INFO]$(NC) Opening shell in production container..."
	$(DC_PROD) exec web-prod /bin/bash

clean-prod: ## Clean production environment
	@echo "$(YELLOW)[WARN]$(NC) This will remove all production containers, images, and volumes."
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	@echo "$(GREEN)[INFO]$(NC) Cleaning production environment..."
	$(DC_PROD) down -v --rmi all
	docker system prune -f
	@echo "$(GREEN)[INFO]$(NC) Production environment cleaned!"

status-prod: ## Show production status
	@echo "$(GREEN)[INFO]$(NC) Production environment status:"
	$(DC_PROD) ps

# General targets
install-deps: ## Install PHP dependencies (development)
	@echo "$(GREEN)[INFO]$(NC) Installing PHP dependencies..."
	$(DC_DEV) run --rm composer-dev
	@echo "$(GREEN)[INFO]$(NC) Dependencies installed!"

install-deps-prod: ## Install PHP dependencies (production)
	@echo "$(GREEN)[INFO]$(NC) Installing production PHP dependencies..."
	$(DC_PROD) run --rm composer-prod
	@echo "$(GREEN)[INFO]$(NC) Production dependencies installed!"

composer-update: ## Update Composer dependencies
	@echo "$(GREEN)[INFO]$(NC) Updating Composer dependencies..."
	$(DC_DEV) run --rm composer-dev update --ignore-platform-reqs
	@echo "$(GREEN)[INFO]$(NC) Dependencies updated!"

composer-require: ## Add a new Composer package (usage: make composer-require PACKAGE=package/name)
	@echo "$(GREEN)[INFO]$(NC) Adding Composer package: $(PACKAGE)"
	$(DC_DEV) run --rm composer-dev require $(PACKAGE) --ignore-platform-reqs
	@echo "$(GREEN)[INFO]$(NC) Package added!"

# Profile-based targets (for backward compatibility)
ifeq ($(PROFILE),dev)
start: start-dev
stop: stop-dev
restart: restart-dev
logs: logs-dev
shell: shell-dev
clean: clean-dev
status: status-dev
build: build-dev
else ifeq ($(PROFILE),prod)
start: start-prod
stop: stop-prod
restart: restart-prod
logs: logs-prod
shell: shell-prod
clean: clean-prod
status: status-prod
build: build-prod
else
start:
	@echo "$(RED)[ERROR]$(NC) Invalid profile: $(PROFILE). Use 'dev' or 'prod'"
	@exit 1
endif
