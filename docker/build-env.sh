#!/bin/bash

# This script combines all environment files into a single .env file
# for Docker and deployment environments

set -e

# Source directory for environment files
ENV_DIR="./config/environments"
OUTPUT_FILE="./.env"

# Environment to use (default: local)
ENVIRONMENT=${1:-local}

echo "Building .env file for environment: $ENVIRONMENT"

# Check if environment directory exists
if [ ! -d "$ENV_DIR" ]; then
  echo "Error: Environment directory $ENV_DIR not found"
  exit 1
fi

# Create output file with header
cat > "$OUTPUT_FILE" << EOL
# Generated from environment files
# DO NOT EDIT DIRECTLY
# Environment: $ENVIRONMENT
# Generated: $(date)

EOL

# Concatenate base environment
if [ -f "$ENV_DIR/base.env" ]; then
  echo "Adding base environment..."
  cat "$ENV_DIR/base.env" >> "$OUTPUT_FILE"
  echo "" >> "$OUTPUT_FILE"
fi

# Concatenate environment-specific configuration
if [ -f "$ENV_DIR/$ENVIRONMENT.env" ]; then
  echo "Adding $ENVIRONMENT environment..."
  cat "$ENV_DIR/$ENVIRONMENT.env" >> "$OUTPUT_FILE"
  echo "" >> "$OUTPUT_FILE"
fi

# Concatenate features
if [ -f "$ENV_DIR/features.env" ]; then
  echo "Adding features..."
  cat "$ENV_DIR/features.env" >> "$OUTPUT_FILE"
  echo "" >> "$OUTPUT_FILE"
fi

# Concatenate secrets
if [ -f "$ENV_DIR/secrets.env" ]; then
  echo "Adding secrets..."
  cat "$ENV_DIR/secrets.env" >> "$OUTPUT_FILE"
fi

echo "Environment file built successfully at $OUTPUT_FILE"

# Make the file executable
chmod +x "$OUTPUT_FILE" 