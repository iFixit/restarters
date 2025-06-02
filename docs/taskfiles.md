# Taskfiles Documentation

This document provides comprehensive documentation for the Taskfiles used in the Restarters project. The project uses [Task](https://taskfile.dev/) as a task runner to manage development workflows, Docker operations, and build processes.

## Overview

The Taskfiles are organized in a modular structure with a main `Taskfile.yml` that includes specialized taskfiles for different aspects of the project:

```
Taskfile.yml              # Main taskfile with includes and environment setup
├── Taskfiles/
    ├── docker.yml        # Docker container management
    ├── app.yml           # Laravel application tasks
    ├── deps.yml          # Dependency management
    └── build.yml         # Docker image building
```

## Main Taskfile Structure

### Taskfile.yml

The main taskfile serves as the entry point and orchestrates the modular taskfiles with separate configurations for development and production environments:

#### Includes
- **build**: Docker image building tasks
- **docker:dev**: Development Docker container management tasks
- **docker:prod**: Production Docker container management tasks  
- **deps**: Dependency management (Composer, NPM) with development Docker namespace
- **app**: Laravel application-specific tasks with development Docker namespace

#### Environment Configuration
The taskfile loads environment variables from multiple `.env` files in order of precedence:
1. `.env.docker.local` (highest priority)
2. `.env.docker`
3. `.env.local`
4. `.env.base` (lowest priority)

#### Available Tasks
- `task` or `task default`: List all available tasks
- `task env:generate`: Generate `.env` file from `.env.template`
- `task setup:dev`: Perform the complete development environment setup
- `task setup:prod`: Perform the production environment setup

## Docker Tasks (docker.yml)

Manages Docker container lifecycle and operations for the Laravel application with support for both development and production environments.

### Container Profiles

The project uses Docker Compose profiles to manage different sets of containers:

- **core**: Essential services (app, database, redis)
- **debug**: Core + development tools (phpMyAdmin, Mailhog)
- **discourse**: Core + Discourse integration containers
- **all**: All available containers

### Available Tasks

#### Container Management
The Docker tasks now use wildcard patterns for flexible profile-based operations:

```bash
# Start containers by profile
task docker:dev:up-core        # Start core services only
task docker:dev:up-debug       # Start core + debug tools
task docker:dev:up-discourse   # Start core + discourse
task docker:dev:up-all         # Start all containers

# Stop containers by profile
task docker:dev:down-core      # Stop core services
task docker:dev:down-debug     # Stop core + debug tools
task docker:dev:down-discourse # Stop core + discourse
task docker:dev:down-all       # Stop all containers

# Restart containers by profile
task docker:dev:restart-core
task docker:dev:restart-debug
task docker:dev:restart-discourse
task docker:dev:restart-all

# Rebuild containers (removes volumes and images)
task docker:dev:rebuild-core
task docker:dev:rebuild-debug
task docker:dev:rebuild-discourse
task docker:dev:rebuild-all

# Rebuild just the application container
task docker:dev:rebuild-app

# Production equivalents
task docker:prod:up-core
task docker:prod:down-core
# ... (same pattern for production)
```

#### Container Interaction
```bash
# View application logs
task docker:dev:logs

# Open shell in application container
task docker:dev:shell

# Run bash commands in container
task docker:dev:run:bash -- ls -la

# Run artisan commands in container
task docker:dev:run:artisan -- migrate

# Run tests in container
task docker:dev:run:tests
```

#### Database Management
```bash
# Initialize test database
task docker:dev:init:test_db
```

### Key Features
- **Auto-detection**: Automatically detects `docker-compose` vs `docker compose` command
- **Profile-based**: Uses Docker Compose profiles for flexible container management
- **Environment generation**: Automatically generates `.env` file before starting containers
- **Wildcard patterns**: Uses Task's wildcard feature for dynamic profile-based operations
- **Dev/Prod separation**: Separate namespaces for development and production environments

## Application Tasks (app.yml)

Manages Laravel-specific operations with automatic Docker container execution.

### Available Tasks

#### Laravel & Artisan
```bash
# Run any artisan command
task app:artisan -- migrate
task app:artisan -- make:controller UserController

# Run tests
task app:tests
task app:tests -- --filter=UserTest
```

#### Cache Management
```bash
# Clear all Laravel caches
task app:clear:caches
```

#### Database Operations
```bash
# Run migrations
task app:db:migrate
task app:db:migrate -- --seed

# Run database seeders
task app:db:seed
task app:db:seed -- --class=UserSeeder
```

#### Code Generation
```bash
# Generate JavaScript translations
task app:generate:translations

# Generate Swagger API documentation
task app:generate:swagger

# Generate application key
task app:generate:key
```

#### User Management
```bash
# Create a new user
task app:user:create -- "John Doe" "john@example.com" "password" "en" "1" --role="2"
```

### Smart Container Detection

All app tasks automatically detect whether they're running inside or outside a Docker container:
- **Outside container**: Automatically executes the command inside the Docker container
- **Inside container**: Runs the command directly

This is achieved through the `IN_DOCKER` environment variable check.

## Dependency Management (deps.yml)

Handles package management for both PHP (Composer) and JavaScript (NPM) dependencies.

### Available Tasks

#### Package Management
```bash
# Clear all dependencies
task deps:clear

# Run composer commands
task deps:composer -- require laravel/sanctum
task deps:composer -- update

# Install composer dependencies
task deps:composer:install

# Run npm commands  
task deps:npm -- install
task deps:npm -- run dev

# Install npm dependencies
task deps:npm:install
```

### Smart Dependency Management

- **Source tracking**: Composer install tracks `composer.json` and `composer.lock` changes
- **Generated files**: Automatically tracks generated files like `vendor/autoload.php`
- **Container awareness**: Like app tasks, automatically runs inside Docker when needed

## Build Tasks (build.yml)

Manages Docker image building for different environments.

### Available Tasks

```bash
# Build development image
task build:dev
task build:dev NAME=myapp TAG=v1.0.0

# Build staging image  
task build:staging
task build:staging NAME=myapp TAG=staging-v1.0.0

# Build production image
task build:prod
task build:prod NAME=myapp TAG=prod-v1.0.0
```

### Build Configurations

- **Development** (`build:dev`): Uses `docker/development/Dockerfile` for development builds
- **Staging** (`build:staging`): Uses `docker/production/Dockerfile.prod` with staging defaults
- **Production** (`build:prod`): Uses `docker/production/Dockerfile.prod` with production defaults

### Customization

All build tasks accept optional parameters:
- `NAME`: Docker image name (default: "restarters")
- `TAG`: Docker image tag (default varies by environment)

## Development Environment Setup

The main `Taskfile.yml` includes comprehensive setup tasks for both development and production environments.

### Development Setup Task

```bash
# Complete development environment setup
task setup:dev
```

The `setup:dev` task performs the following operations in sequence:
1. **Install Composer dependencies** (`task deps:composer:install`)
2. **Run database migrations** (`task app:db:migrate`)
3. **Generate JavaScript translations** (`task app:generate:translations`)
4. **Generate Swagger API documentation** (`task app:generate:swagger`)
5. **Generate application key** (`task app:generate:key`)
6. **Install NPM dependencies** with legacy peer deps (`task deps:npm:install`)
7. **Rebuild node-sass** (`task deps:npm`)

### Production Setup Task

```bash
# Production environment setup
task setup:prod
```

The `setup:prod` task performs conditional operations based on environment variables:
1. **Run database migrations** (if `MIGRATE=true`)
2. **Seed default skills** (if `SEED_SKILLS=true`)
3. **Create admin user** (if `CREATE_ADMIN_USER=true`)
4. **Clear all caches** (`task app:clear:caches`)

## Usage Examples

### Common Development Workflows

#### Initial Project Setup
```bash
# Start core containers
task docker:dev:up-core

# Complete development setup
task setup:dev
```

#### Daily Development
```bash
# Start containers with debug tools
task docker:dev:up-debug

# Run migrations after pulling changes
task app:db:migrate

# Run tests
task app:tests

# Clear caches after config changes
task app:clear:caches
```

#### Adding Dependencies
```bash
# Add PHP package
task deps:composer -- require spatie/laravel-permission

# Add NPM package
task deps:npm -- install vue-router
```

#### Database Operations
```bash
# Create and run migration
task app:artisan -- make:migration create_users_table
task app:db:migrate

# Seed database
task app:db:seed

# Initialize test database
task docker:dev:init:test_db
```

#### User Management
```bash
# Create admin user
task app:user:create -- "Admin User" "admin@example.com" "secure_password" "en" "1" --role="2"
```

#### Building for Deployment
```bash
# Build production image
task build:prod NAME=restarters TAG=v2.1.0
```

## Advanced Features

### Environment Variable Precedence

The taskfiles support multiple environment configurations with clear precedence:
1. `.env.docker.local` (local overrides)
2. `.env.docker` (Docker-specific config)
3. `.env.local` (local config overrides)
4. `.env.base` (base configuration)

### Task Namespacing

Tasks are organized into logical namespaces:
- `docker:dev:*` - Development container operations
- `docker:prod:*` - Production container operations
- `app:*` - Laravel application tasks
- `deps:*` - Dependency management
- `build:*` - Image building

### Wildcard Task Patterns

The Docker tasks use Task's wildcard feature for dynamic operations:
- `up-*`, `down-*`, `restart-*`, `rebuild-*` patterns allow flexible profile-based operations
- Profile validation ensures only valid profiles (core, debug, discourse, all) are used

### Variable Inheritance

The taskfiles use variable inheritance and namespacing:
- `DOCKER_NAMESPACE` allows flexible task composition between dev and prod
- Variables can be overridden at runtime
- Default values provide sensible fallbacks

### Error Handling

- Commands use `ignore_error: true` where appropriate
- Silent execution for routine operations
- Comprehensive error messages for debugging

## Best Practices

1. **Use profiles**: Start only the containers you need with appropriate profiles
2. **Environment separation**: Use `docker:dev:*` for development and `docker:prod:*` for production
3. **Environment files**: Keep sensitive data in `.env.docker.local` (gitignored)
4. **Task composition**: Leverage task dependencies for complex workflows
5. **Container awareness**: Let tasks automatically handle Docker execution
6. **Namespace consistency**: Use consistent namespacing when extending taskfiles

## Troubleshooting

### Common Issues

1. **Docker command not found**: Task auto-detects `docker-compose` vs `docker compose`
2. **Permission errors**: Ensure Docker daemon is running and user has permissions
3. **Port conflicts**: Check if ports are already in use before starting containers
4. **Environment variables**: Verify `.env` files are properly configured

### Debugging

```bash
# View container logs
task docker:dev:logs

# View Laravel Logs
tail -f storage/logs/laravel.log

# Verify environment
task app:artisan -- env

# Open shell for debugging
task docker:dev:shell
```

### Production Debugging

```bash
# View production container logs
task docker:prod:logs

# Open shell in production container
task docker:prod:shell

# Check production environment
task app:artisan -- env
```