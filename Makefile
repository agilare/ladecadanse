# Ladecadanse Docker Management Makefile
# Usage: make [target] PROFILE=dev|prod

.PHONY: help start stop build restart logs shell clean status install-deps composer-update composer-require

# Default profile
PROFILE ?= dev

# Docker Compose command with profile
DC = docker-compose --profile $(PROFILE)

# Container names based on profile
WEB_CONTAINER = web-$(PROFILE)
COMPOSER_CONTAINER = composer-$(PROFILE)

# Colors for output
GREEN = \033[0;32m
YELLOW = \033[1;33m
RED = \033[0;31m
NC = \033[0m # No Color

help: ## Show this help message
	@echo "Ladecadanse Docker Management"
	@echo "============================="
	@echo ""
	@echo "Usage: make [target] PROFILE=dev|prod"
	@echo ""
	@echo "Available targets:"
	@echo "  start            Start environment (default: dev)"
	@echo "  build            Build images"
	@echo "  stop             Stop services"
	@echo "  restart          Restart services"
	@echo "  logs             Show logs (follow mode)"
	@echo "  shell            Open shell in web container"
	@echo "  clean            Clean environment (containers, images, volumes)"
	@echo "  status           Show service status"
	@echo "  install-deps     Install PHP dependencies"
	@echo "  composer-update  Update Composer dependencies"
	@echo "  composer-require Add a new Composer package (usage: make composer-require PACKAGE=package/name)"
	@echo "  help             Show this help message"
	@echo ""
	@echo "Examples:"
	@echo "  make start                           # Start development (default)"
	@echo "  make start PROFILE=prod              # Start production"
	@echo "  make build PROFILE=dev               # Build dev images"
	@echo "  make logs PROFILE=prod               # Show prod logs"
	@echo "  make shell                           # Open dev shell"
	@echo "  make composer-require PACKAGE=monolog/monolog"
	@echo ""
	@echo "Current profile: $(PROFILE)"

start: ## Start environment
	@echo "$(GREEN)[INFO]$(NC) Starting $(PROFILE) environment..."
	$(DC) up -d
	@echo "$(GREEN)[INFO]$(NC) $(PROFILE) environment started!"

build: ## Build images
	@echo "$(GREEN)[INFO]$(NC) Building $(PROFILE) images..."
	$(DC) build --no-cache
	@echo "$(GREEN)[INFO]$(NC) $(PROFILE) images built!"

stop: ## Stop services
	@echo "$(GREEN)[INFO]$(NC) Stopping $(PROFILE) services..."
	$(DC) down
	@echo "$(GREEN)[INFO]$(NC) $(PROFILE) services stopped!"

restart: ## Restart services
	@echo "$(GREEN)[INFO]$(NC) Restarting $(PROFILE) services..."
	$(DC) restart
	@echo "$(GREEN)[INFO]$(NC) $(PROFILE) services restarted!"

logs: ## Show logs
	@echo "$(GREEN)[INFO]$(NC) Showing $(PROFILE) logs..."
	$(DC) logs -f

shell: ## Open shell in web container
	@echo "$(GREEN)[INFO]$(NC) Opening shell in $(PROFILE) container..."
	$(DC) exec $(WEB_CONTAINER) /bin/bash

clean: ## Clean environment
	@echo "$(YELLOW)[WARN]$(NC) This will remove $(PROFILE) containers, images, and volumes for this project only."
	@read -p "Are you sure? (y/N): " confirm && [ "$$confirm" = "y" ] || exit 1
	@echo "$(GREEN)[INFO]$(NC) Cleaning $(PROFILE) environment..."
	$(DC) down -v --rmi local
	@echo "$(GREEN)[INFO]$(NC) $(PROFILE) environment cleaned!"

status: ## Show service status
	@echo "$(GREEN)[INFO]$(NC) $(PROFILE) environment status:"
	$(DC) ps

install-deps: ## Install PHP dependencies
	@echo "$(GREEN)[INFO]$(NC) Installing PHP dependencies for $(PROFILE)..."
	$(DC) run --rm $(COMPOSER_CONTAINER)
	@echo "$(GREEN)[INFO]$(NC) Dependencies installed!"

composer-update: ## Update Composer dependencies
	@echo "$(GREEN)[INFO]$(NC) Updating Composer dependencies for $(PROFILE)..."
	$(DC) run --rm $(COMPOSER_CONTAINER) update --ignore-platform-reqs
	@echo "$(GREEN)[INFO]$(NC) Dependencies updated!"

composer-require: ## Add a new Composer package (usage: make composer-require PACKAGE=package/name)
	@if [ -z "$(PACKAGE)" ]; then \
		echo "$(RED)[ERROR]$(NC) PACKAGE variable is required. Usage: make composer-require PACKAGE=package/name"; \
		exit 1; \
	fi
	@echo "$(GREEN)[INFO]$(NC) Adding Composer package: $(PACKAGE) for $(PROFILE)"
	$(DC) run --rm $(COMPOSER_CONTAINER) require $(PACKAGE) --ignore-platform-reqs
	@echo "$(GREEN)[INFO]$(NC) Package added!"

# Convenient aliases
dev: PROFILE=dev
dev: start

prod: PROFILE=prod
prod: start
