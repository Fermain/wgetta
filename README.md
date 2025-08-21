# Wgetta - WordPress wget Management Plugin

## Overview
Wgetta is a WordPress plugin that provides a user-friendly interface for managing and executing wget commands with advanced filtering capabilities. It allows users to test wget commands with dry-runs, apply regex exclusions to filter unwanted files, and then execute the final command.

## Goals

### Primary Objectives
1. **Safe wget Command Management**: Provide a controlled environment for configuring and testing wget commands before execution
2. **Visual Regex Testing**: Allow users to see in real-time which URLs will be included/excluded based on their regex patterns
3. **Dry-Run Capability**: Test wget commands without actually downloading files to preview what will be crawled
4. **Progressive Workflow**: Guide users through a logical flow: Configure → Test → Execute

### Key Features
- **Settings Page**: Configure and save wget command parameters
- **Plan Page**: 
  - Execute dry-runs to preview crawled URLs
  - Test regex exclusion patterns with visual feedback
  - Two-column layout showing included vs excluded URLs
- **Copy Page**: Execute the finalized wget command for actual file retrieval

## Architecture

### Plugin Structure
```
wgetta/
├── README.md
├── wgetta.php                 # Main plugin file
├── includes/
│   ├── class-wgetta.php       # Core plugin class
│   ├── class-wgetta-admin.php # Admin functionality
│   └── class-wgetta-wget.php  # wget command handling
├── admin/
│   ├── css/
│   │   └── wgetta-admin.css
│   ├── js/
│   │   └── wgetta-admin.js
│   └── partials/
│       ├── wgetta-admin-settings.php
│       ├── wgetta-admin-plan.php
│       └── wgetta-admin-copy.php
└── assets/
    └── icon.svg
```

### Database Schema
- **Options Table**: Store wget command configurations and regex patterns
- **Transients**: Cache dry-run results temporarily

### Security Considerations
- Sanitize all user inputs
- Validate wget commands to prevent command injection
- Implement nonce verification for all AJAX requests
- Capability checks for admin-only functionality
- Escape all output

## User Workflow

### 1. Settings Page
- Input field for wget command string
- Save/Update functionality
- Preset templates for common use cases
- Command validation feedback

### 2. Plan Page
- **Dry Run Section**:
  - Button to execute dry-run
  - Display crawled URLs
  - Show command output/errors
  
- **Regex Testing Section**:
  - Input field(s) for regex exclusion patterns
  - Two-column display:
    - Left: URLs that will be included
    - Right: URLs that will be excluded
  - Real-time update as regex patterns change
  - Save tested patterns

### 3. Copy Page
- Display final wget command with applied filters
- Confirmation before execution
- Progress indicator during execution
- Results display with download statistics
- Error handling and reporting

## Technical Requirements

### WordPress Compatibility
- Minimum WordPress Version: 5.0
- PHP Version: 7.4+
- Required PHP Extensions: exec/shell_exec for wget execution

### Dependencies
- wget installed on server
- Appropriate file system permissions for downloads

### AJAX Endpoints
- `/wp-admin/admin-ajax.php?action=wgetta_dry_run`
- `/wp-admin/admin-ajax.php?action=wgetta_test_regex`
- `/wp-admin/admin-ajax.php?action=wgetta_execute`

## Development Phases

### Phase 1: Foundation (Current)
1. Create plugin structure and basic files
2. Implement admin menu and page routing
3. Create UI shells for all three pages
4. Set up basic styling and JavaScript scaffolding

### Phase 2: Core Functionality
1. Implement wget command validation and sanitization
2. Add dry-run execution capability
3. Create regex testing engine
4. Implement AJAX handlers

### Phase 3: Enhancement
1. Add progress indicators and real-time feedback
2. Implement command history/templates
3. Add export/import functionality for configurations
4. Create detailed logging system

## Potential Challenges & Solutions

### Security
- **Challenge**: Preventing command injection through wget parameters
- **Solution**: Whitelist allowed wget options, escape shell arguments, run with minimal privileges

### Performance
- **Challenge**: Large dry-run results could overwhelm browser
- **Solution**: Implement pagination, limit results, use virtual scrolling

### User Experience
- **Challenge**: Complex regex patterns are hard for non-technical users
- **Solution**: Provide common pattern templates, visual feedback, pattern builder helper

### Server Compatibility
- **Challenge**: wget may not be available or have different versions
- **Solution**: Check for wget availability on activation, provide fallback options (curl), version detection