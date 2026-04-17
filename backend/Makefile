# ============================================
# SECURITY ASSESSMENT PLATFORM - MAKEFILE
# ============================================
# 
# Các lệnh tự động hóa cho dự án
# Sử dụng: make <command>
#
# Ví dụ:
#   make install      # Cài đặt dự án
#   make start        # Khởi động server
#   make test         # Chạy tests
#   make backup       # Backup database
#
# ============================================

# ============================================
# VARIABLES
# ============================================
PHP = php
COMPOSER = composer
NPM = npm
MYSQL = mysql
CURL = curl
ECHO = echo

# Colors
RED = \033[0;31m
GREEN = \033[0;32m
YELLOW = \033[0;33m
BLUE = \033[0;34m
NC = \033[0m # No Color

# Paths
BACKEND_DIR = backend
FRONTEND_DIR = frontend
REALTIME_DIR = realtime
STORAGE_DIR = $(BACKEND_DIR)/storage
LOGS_DIR = $(STORAGE_DIR)/logs
UPLOADS_DIR = $(STORAGE_DIR)/uploads
BACKUPS_DIR = $(STORAGE_DIR)/backups

# Database
DB_HOST = localhost
DB_NAME = security_db
DB_USER = root
DB_PASS = 

# ============================================
# HELP (Mặc định)
# ============================================
.PHONY: help
help:
	@$(ECHO) "$(BLUE)===========================================$(NC)"
	@$(ECHO) "$(GREEN)Security Assessment Platform - Makefile$(NC)"
	@$(ECHO) "$(BLUE)===========================================$(NC)"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Installation:$(NC)"
	@$(ECHO) "  make install          - Cài đặt toàn bộ dự án"
	@$(ECHO) "  make setup            - Thiết lập môi trường"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Development:$(NC)"
	@$(ECHO) "  make start            - Khởi động server"
	@$(ECHO) "  make dev              - Chạy môi trường development"
	@$(ECHO) "  make watch            - Watch cho changes"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Backend:$(NC)"
	@$(ECHO) "  make backend-install  - Cài đặt PHP dependencies"
	@$(ECHO) "  make backend-start    - Chạy PHP server"
	@$(ECHO) "  make migrate          - Chạy database migrations"
	@$(ECHO) "  make migrate-fresh    - Reset database"
	@$(ECHO) "  make seed             - Seed database"
	@$(ECHO) "  make db-fresh         - Migrate fresh + seed"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Frontend:$(NC)"
	@$(ECHO) "  make frontend-install - Cài đặt JS dependencies"
	@$(ECHO) "  make frontend-build   - Build frontend"
	@$(ECHO) "  make frontend-watch   - Watch frontend changes"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Realtime:$(NC)"
	@$(ECHO) "  make realtime-install - Cài đặt Node dependencies"
	@$(ECHO) "  make realtime-start   - Chạy WebSocket server"
	@$(ECHO) "  make realtime-stop    - Dừng WebSocket server"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Testing:$(NC)"
	@$(ECHO) "  make test             - Chạy tất cả tests"
	@$(ECHO) "  make test-unit        - Chạy unit tests"
	@$(ECHO) "  make test-feature     - Chạy feature tests"
	@$(ECHO) "  make test-coverage    - Chạy tests với coverage"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Database:$(NC)"
	@$(ECHO) "  make db-create        - Tạo database"
	@$(ECHO) "  make db-drop          - Xóa database"
	@$(ECHO) "  make db-backup        - Backup database"
	@$(ECHO) "  make db-restore       - Restore database"
	@$(ECHO) "  make db-optimize      - Optimize database"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Maintenance:$(NC)"
	@$(ECHO) "  make backup           - Backup toàn bộ"
	@$(ECHO) "  make clean            - Dọn dẹp cache và logs"
	@$(ECHO) "  make cache-clear      - Xóa cache"
	@$(ECHO) "  make logs-clear       - Xóa logs"
	@$(ECHO) "  make maintenance-on   - Bật chế độ bảo trì"
	@$(ECHO) "  make maintenance-off  - Tắt chế độ bảo trì"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)Other:$(NC)"
	@$(ECHO) "  make lint             - Kiểm tra code style"
	@$(ECHO) "  make lint-fix         - Sửa code style"
	@$(ECHO) "  make security-check   - Kiểm tra bảo mật dependencies"
	@$(ECHO) "  make update           - Cập nhật dependencies"
	@$(ECHO) "  make info             - Hiển thị thông tin hệ thống"
	@$(ECHO) ""

# ============================================
# INSTALLATION
# ============================================

.PHONY: install
install: setup backend-install frontend-install realtime-install db-create migrate seed
	@$(ECHO) "$(GREEN)✅ Installation completed!$(NC)"

.PHONY: setup
setup:
	@$(ECHO) "$(BLUE)📦 Setting up environment...$(NC)"
	@cp -n .env.example .env 2>/dev/null || true
	@mkdir -p $(LOGS_DIR) $(UPLOADS_DIR) $(BACKUPS_DIR)
	@mkdir -p $(UPLOADS_DIR)/evidence $(UPLOADS_DIR)/reports $(UPLOADS_DIR)/avatars $(UPLOADS_DIR)/temp
	@mkdir -p $(BACKUPS_DIR)/database $(BACKUPS_DIR)/files
	@chmod -R 755 $(STORAGE_DIR)
	@$(ECHO) "$(GREEN)✅ Setup completed!$(NC)"

# ============================================
# BACKEND
# ============================================

.PHONY: backend-install
backend-install:
	@$(ECHO) "$(BLUE)📦 Installing PHP dependencies...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) install
	@$(ECHO) "$(GREEN)✅ PHP dependencies installed!$(NC)"

.PHONY: backend-start
backend-start:
	@$(ECHO) "$(BLUE)🚀 Starting PHP server...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) -S localhost:8000 -t public

.PHONY: backend-optimize
backend-optimize:
	@$(ECHO) "$(BLUE)⚡ Optimizing backend...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) dump-autoload --optimize
	@$(ECHO) "$(GREEN)✅ Backend optimized!$(NC)"

# ============================================
# DATABASE
# ============================================

.PHONY: db-create
db-create:
	@$(ECHO) "$(BLUE)🗄️ Creating database...$(NC)"
	$(MYSQL) -u $(DB_USER) -e "CREATE DATABASE IF NOT EXISTS $(DB_NAME) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
	@$(ECHO) "$(GREEN)✅ Database created!$(NC)"

.PHONY: db-drop
db-drop:
	@$(ECHO) "$(YELLOW)⚠️  Dropping database...$(NC)"
	$(MYSQL) -u $(DB_USER) -e "DROP DATABASE IF EXISTS $(DB_NAME);"
	@$(ECHO) "$(GREEN)✅ Database dropped!$(NC)"

.PHONY: migrate
migrate:
	@$(ECHO) "$(BLUE)📋 Running migrations...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) artisan migrate
	@$(ECHO) "$(GREEN)✅ Migrations completed!$(NC)"

.PHONY: migrate-fresh
migrate-fresh:
	@$(ECHO) "$(YELLOW)⚠️  Resetting database...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) artisan migrate:fresh
	@$(ECHO) "$(GREEN)✅ Database reset!$(NC)"

.PHONY: seed
seed:
	@$(ECHO) "$(BLUE)🌱 Seeding database...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) artisan db:seed
	@$(ECHO) "$(GREEN)✅ Seeding completed!$(NC)"

.PHONY: db-fresh
db-fresh: migrate-fresh seed
	@$(ECHO) "$(GREEN)✅ Fresh database with seed completed!$(NC)"

.PHONY: db-backup
db-backup:
	@$(ECHO) "$(BLUE)💾 Backing up database...$(NC)"
	@mkdir -p $(BACKUPS_DIR)/database
	$(MYSQL) -u $(DB_USER) -p$(DB_PASS) $(DB_NAME) > $(BACKUPS_DIR)/database/backup_$(shell date +%Y%m%d_%H%M%S).sql
	@$(ECHO) "$(GREEN)✅ Database backup completed!$(NC)"

.PHONY: db-restore
db-restore:
	@$(ECHO) "$(YELLOW)⚠️  Restoring database...$(NC)"
	@$(ECHO) "Enter backup filename: "; \
	read filename; \
	$(MYSQL) -u $(DB_USER) -p$(DB_PASS) $(DB_NAME) < $(BACKUPS_DIR)/database/$$filename
	@$(ECHO) "$(GREEN)✅ Database restore completed!$(NC)"

.PHONY: db-optimize
db-optimize:
	@$(ECHO) "$(BLUE)🔧 Optimizing database...$(NC)"
	$(MYSQL) -u $(DB_USER) -p$(DB_PASS) $(DB_NAME) -e "OPTIMIZE TABLE users, servers, assessment_criteria, assessment_results;"
	@$(ECHO) "$(GREEN)✅ Database optimized!$(NC)"

# ============================================
# FRONTEND
# ============================================

.PHONY: frontend-install
frontend-install:
	@$(ECHO) "$(BLUE)📦 Installing frontend dependencies...$(NC)"
	cd $(FRONTEND_DIR) && $(NPM) install
	@$(ECHO) "$(GREEN)✅ Frontend dependencies installed!$(NC)"

.PHONY: frontend-build
frontend-build:
	@$(ECHO) "$(BLUE)🔨 Building frontend...$(NC)"
	cd $(FRONTEND_DIR) && $(NPM) run build
	@$(ECHO) "$(GREEN)✅ Frontend built!$(NC)"

.PHONY: frontend-watch
frontend-watch:
	@$(ECHO) "$(BLUE)👀 Watching frontend changes...$(NC)"
	cd $(FRONTEND_DIR) && $(NPM) run watch

# ============================================
# REALTIME (WEBSOCKET)
# ============================================

.PHONY: realtime-install
realtime-install:
	@$(ECHO) "$(BLUE)📦 Installing WebSocket dependencies...$(NC)"
	cd $(REALTIME_DIR) && $(NPM) install
	@$(ECHO) "$(GREEN)✅ WebSocket dependencies installed!$(NC)"

.PHONY: realtime-start
realtime-start:
	@$(ECHO) "$(BLUE)⚡ Starting WebSocket server...$(NC)"
	cd $(REALTIME_DIR) && $(NPM) start

.PHONY: realtime-stop
realtime-stop:
	@$(ECHO) "$(YELLOW)🛑 Stopping WebSocket server...$(NC)"
	@killall node 2>/dev/null || true
	@$(ECHO) "$(GREEN)✅ WebSocket server stopped!$(NC)"

.PHONY: realtime-restart
realtime-restart: realtime-stop realtime-start
	@$(ECHO) "$(GREEN)✅ WebSocket server restarted!$(NC)"

# ============================================
# TESTING
# ============================================

.PHONY: test
test:
	@$(ECHO) "$(BLUE)🧪 Running all tests...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) run test
	@$(ECHO) "$(GREEN)✅ Tests completed!$(NC)"

.PHONY: test-unit
test-unit:
	@$(ECHO) "$(BLUE)🧪 Running unit tests...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) vendor/bin/phpunit --testsuite Unit
	@$(ECHO) "$(GREEN)✅ Unit tests completed!$(NC)"

.PHONY: test-feature
test-feature:
	@$(ECHO) "$(BLUE)🧪 Running feature tests...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) vendor/bin/phpunit --testsuite Feature
	@$(ECHO) "$(GREEN)✅ Feature tests completed!$(NC)"

.PHONY: test-coverage
test-coverage:
	@$(ECHO) "$(BLUE)📊 Running tests with coverage...$(NC)"
	cd $(BACKEND_DIR) && $(PHP) vendor/bin/phpunit --coverage-html coverage/
	@$(ECHO) "$(GREEN)✅ Coverage report generated in coverage/$(NC)"

# ============================================
# DEVELOPMENT
# ============================================

.PHONY: start
start:
	@$(ECHO) "$(BLUE)🚀 Starting development environment...$(NC)"
	@make backend-start &
	@make realtime-start &
	@make frontend-watch
	@$(ECHO) "$(GREEN)✅ Development environment started!$(NC)"

.PHONY: dev
dev:
	@$(ECHO) "$(BLUE)💻 Starting dev servers...$(NC)"
	@$(ECHO) "$(YELLOW)Backend: http://localhost:8000$(NC)"
	@$(ECHO) "$(YELLOW)WebSocket: ws://localhost:3000$(NC)"
	@$(ECHO) "$(YELLOW)Frontend: http://localhost:8080$(NC)"
	@make start

.PHONY: watch
watch:
	@$(ECHO) "$(BLUE)👀 Watching for changes...$(NC)"
	@cd $(BACKEND_DIR) && $(COMPOSER) run watch

# ============================================
# MAINTENANCE
# ============================================

.PHONY: backup
backup: db-backup
	@$(ECHO) "$(BLUE)💾 Creating full backup...$(NC)"
	@tar -czf $(BACKUPS_DIR)/files/backup_$(shell date +%Y%m%d_%H%M%S).tar.gz $(BACKEND_DIR) $(FRONTEND_DIR) --exclude=$(STORAGE_DIR)
	@$(ECHO) "$(GREEN)✅ Full backup completed!$(NC)"

.PHONY: clean
clean: cache-clear logs-clear
	@$(ECHO) "$(BLUE)🧹 Cleaning up...$(NC)"
	@rm -rf $(BACKEND_DIR)/vendor/.cache
	@rm -rf $(FRONTEND_DIR)/node_modules/.cache
	@$(ECHO) "$(GREEN)✅ Cleanup completed!$(NC)"

.PHONY: cache-clear
cache-clear:
	@$(ECHO) "$(BLUE)🗑️ Clearing cache...$(NC)"
	@cd $(BACKEND_DIR) && $(PHP) artisan cache:clear
	@rm -rf $(STORAGE_DIR)/cache/*
	@rm -rf $(STORAGE_DIR)/framework/cache/*
	@$(ECHO) "$(GREEN)✅ Cache cleared!$(NC)"

.PHONY: logs-clear
logs-clear:
	@$(ECHO) "$(BLUE)🗑️ Clearing logs...$(NC)"
	@rm -rf $(LOGS_DIR)/*.log
	@$(ECHO) "$(GREEN)✅ Logs cleared!$(NC)"

.PHONY: maintenance-on
maintenance-on:
	@$(ECHO) "$(YELLOW)🔧 Enabling maintenance mode...$(NC)"
	@touch $(BACKEND_DIR)/storage/maintenance.flag
	@$(ECHO) "$(GREEN)✅ Maintenance mode enabled!$(NC)"

.PHONY: maintenance-off
maintenance-off:
	@$(ECHO) "$(GREEN)🔧 Disabling maintenance mode...$(NC)"
	@rm -f $(BACKEND_DIR)/storage/maintenance.flag
	@$(ECHO) "$(GREEN)✅ Maintenance mode disabled!$(NC)"

# ============================================
# CODE QUALITY
# ============================================

.PHONY: lint
lint:
	@$(ECHO) "$(BLUE)🔍 Checking code style...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) run lint
	@$(ECHO) "$(GREEN)✅ Code style check completed!$(NC)"

.PHONY: lint-fix
lint-fix:
	@$(ECHO) "$(BLUE)🔧 Fixing code style...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) run lint-fix
	@$(ECHO) "$(GREEN)✅ Code style fixed!$(NC)"

.PHONY: security-check
security-check:
	@$(ECHO) "$(BLUE)🔒 Checking security vulnerabilities...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) audit
	cd $(FRONTEND_DIR) && $(NPM) audit
	@$(ECHO) "$(GREEN)✅ Security check completed!$(NC)"

# ============================================
# UPDATE
# ============================================

.PHONY: update
update:
	@$(ECHO) "$(BLUE)🔄 Updating dependencies...$(NC)"
	cd $(BACKEND_DIR) && $(COMPOSER) update
	cd $(FRONTEND_DIR) && $(NPM) update
	cd $(REALTIME_DIR) && $(NPM) update
	@$(ECHO) "$(GREEN)✅ Dependencies updated!$(NC)"

# ============================================
# INFORMATION
# ============================================

.PHONY: info
info:
	@$(ECHO) "$(BLUE)===========================================$(NC)"
	@$(ECHO) "$(GREEN)Security Assessment Platform - System Info$(NC)"
	@$(ECHO) "$(BLUE)===========================================$(NC)"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)PHP Version:$(NC)      $(shell php -v | head -1)"
	@$(ECHO) "$(YELLOW)MySQL Version:$(NC)     $(shell mysql --version)"
	@$(ECHO) "$(YELLOW)Node Version:$(NC)      $(shell node -v 2>/dev/null || echo 'Not installed')"
	@$(ECHO) "$(YELLOW)NPM Version:$(NC)       $(shell npm -v 2>/dev/null || echo 'Not installed')"
	@$(ECHO) "$(YELLOW)Composer Version:$(NC)  $(shell composer -V 2>/dev/null || echo 'Not installed')"
	@$(ECHO) ""
	@$(ECHO) "$(YELLOW)OS:$(NC)               $(shell uname -s 2>/dev/null || echo 'Windows')"
	@$(ECHO) "$(YELLOW)Date:$(NC)             $(shell date)"
	@$(ECHO) ""

# ============================================
# DOCKER (Optional)
# ============================================

.PHONY: docker-up
docker-up:
	@$(ECHO) "$(BLUE)🐳 Starting Docker containers...$(NC)"
	docker-compose up -d
	@$(ECHO) "$(GREEN)✅ Docker containers started!$(NC)"

.PHONY: docker-down
docker-down:
	@$(ECHO) "$(BLUE)🐳 Stopping Docker containers...$(NC)"
	docker-compose down
	@$(ECHO) "$(GREEN)✅ Docker containers stopped!$(NC)"

.PHONY: docker-build
docker-build:
	@$(ECHO) "$(BLUE)🐳 Building Docker images...$(NC)"
	docker-compose build
	@$(ECHO) "$(GREEN)✅ Docker images built!$(NC)"

# ============================================
# PRODUCTION
# ============================================

.PHONY: prod-build
prod-build:
	@$(ECHO) "$(BLUE)🏭 Building for production...$(NC)"
	@make backend-optimize
	@make frontend-build
	@$(ECHO) "$(GREEN)✅ Production build completed!$(NC)"

.PHONY: prod-deploy
prod-deploy:
	@$(ECHO) "$(YELLOW)⚠️  Deploying to production...$(NC)"
	@make maintenance-on
	@make prod-build
	@make migrate
	@make maintenance-off
	@$(ECHO) "$(GREEN)✅ Deployment completed!$(NC)"

# ============================================
# DEFAULT
# ============================================
.DEFAULT_GOAL := help