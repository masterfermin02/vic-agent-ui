# VICIdial Agent UI

A modern, real-time web interface for VICIdial call center agents built with Laravel 12, Inertia.js v2, and Vue 3.

## Overview

This application replaces the default VICIdial agent screen with a responsive, WebSocket-powered UI. It connects directly to your existing VICIdial/Asterisk infrastructure — no modifications to VICIdial are required.

**Key capabilities:**
- Agent login/logout with campaign selection
- Real-time status management (Ready, Paused, In-Call)
- Manual outbound dialing via Asterisk AMI
- Call disposition submission
- Live call status updates over WebSockets (Laravel Reverb)

## Technology Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.4, Laravel 12, Inertia.js v2 |
| Frontend | Vue 3, TypeScript, Tailwind CSS 4, Vite |
| Auth | Laravel Fortify (with 2FA) |
| WebSockets | Laravel Reverb v1 + Laravel Echo |
| Telephony | VICIdial DB + Asterisk AMI |
| Testing | Pest v4 |

## Requirements

- PHP 8.4+
- Node.js 20+
- Composer 2
- A running VICIdial/Asterisk server (MySQL `asterisk` database accessible)
- [Laravel Herd](https://herd.laravel.com/) (recommended) or any PHP server

## Installation

### 1. Clone and install dependencies

```bash
git clone <repo-url> vic-agent-ui
cd vic-agent-ui
composer run setup
```

`composer run setup` installs PHP and JS dependencies, copies `.env.example` to `.env`, generates an app key, runs migrations, and builds frontend assets.

### 2. Configure environment

Edit `.env` with your VICIdial connection details:

```env
# Application database (SQLite by default, no setup needed)
DB_CONNECTION=sqlite

# VICIdial / Asterisk database
VICIDIAL_SERVER_IP=192.168.1.100       # Asterisk server IP
VICIDIAL_DB_HOST=192.168.1.100
VICIDIAL_DB_PORT=3306
VICIDIAL_DB_DATABASE=asterisk
VICIDIAL_DB_USERNAME=cron
VICIDIAL_DB_PASSWORD=your_password

# VICIdial HTTP API (used for dispositions)
VICIDIAL_API_URL=http://192.168.1.100/agc/api.php

# Asterisk AMI (for real-time call events)
VICIDIAL_AMI_HOST=192.168.1.100
VICIDIAL_AMI_PORT=5038
VICIDIAL_AMI_USER=your_ami_user
VICIDIAL_AMI_SECRET=your_ami_secret

# Laravel Reverb (WebSocket server)
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Generate Reverb credentials:

```bash
php artisan reverb:install
```

### 3. Seed test data (optional)

If you want to seed a test campaign, agent, and leads into VICIdial:

```bash
php artisan db:seed --class=VicidialSeeder
```

This creates:
- Campaign `TESTCAMP` (manual dial)
- Agent user `agent1` / password `Test123!`
- Phone extension `1001`
- 8 call dispositions
- 10 sample leads in list 998

> **Note:** The seeder writes directly to your VICIdial `asterisk` database. Only run it against a development/test VICIdial instance.

## Running

### Development

Start all services with a single command:

```bash
composer run dev
```

This concurrently runs:
- Laravel development server
- Vite dev server (Hot Module Replacement)
- Laravel Reverb WebSocket server (port 8080)
- Asterisk AMI listener (`php artisan ami:listen`)
- Queue worker
- Log tailing (Pail)

### Production

```bash
npm run build
php artisan config:cache
php artisan route:cache

# Run these as persistent processes (e.g. via Supervisor):
php artisan reverb:start --port=8080
php artisan ami:listen
php artisan queue:work
```

## Architecture

### Agent Workflow

```
1. Agent logs in → /agent/campaigns
   └── VicidialAgentService::login()
       ├── Reserves conference room (vicidial_conferences)
       ├── Creates live agent record (vicidial_live_agents)
       ├── Rings agent's SIP phone via Asterisk Originate
       └── Opens agent log entry (vicidial_agent_log)

2. Agent sets status → PUT /agent/status
   ├── READY: VicidialAgentService::setReady()
   └── PAUSED: VicidialAgentService::setPaused(pause_code)

3. Agent dials → POST /agent/call/dial
   └── VicidialAgentService::manualDial()
       ├── Queues Asterisk Originate via vicidial_manager
       ├── Creates vicidial_auto_calls entry
       └── Updates vicidial_live_agents to INCALL

4. Call ends → agent submits disposition → POST /agent/call/disposition
   └── VICIdial HTTP API: disposition + status update

5. Agent logs out → DELETE /agent/session
   └── VicidialAgentService::logout()
       ├── Hangs up SIP call (Hangup + kickall Originate)
       ├── Releases conference room
       └── Removes vicidial_live_agents record
```

### Key Services

| Service | Purpose |
|---|---|
| `VicidialAgentService` | Direct DB writes to VICIdial — login, status, dial, logout |
| `VicidialApiService` | HTTP calls to VICIdial API — dispositions |
| `AsteriskAmiService` | Raw socket to Asterisk AMI — real-time call events |

### Real-Time Updates

The AMI listener (`php artisan ami:listen`) connects to Asterisk's Management Interface and broadcasts events to the frontend over WebSockets:

```
Asterisk AMI → AsteriskAmiListener → AgentCallStatusUpdated event
                                   → Reverb WebSocket → Laravel Echo → Vue
```

Events are broadcast on private channel `agent.{userId}`.

## Directory Structure

```
app/
├── Console/Commands/AsteriskAmiListener.php  # AMI event listener
├── Events/
│   ├── AgentStatusChanged.php
│   └── AgentCallStatusUpdated.php
├── Http/Controllers/Agent/
│   ├── AgentSessionController.php            # Login/logout/status
│   └── CallController.php                    # Dial/disposition
├── Models/
│   ├── AgentSession.php                      # Active session (app DB)
│   ├── VicidialCampaign.php                  # VICIdial DB (read)
│   └── VicidialDisposition.php               # VICIdial DB (read)
└── Services/
    ├── VicidialAgentService.php              # Core telephony logic
    ├── VicidialApiService.php                # HTTP API client
    └── AsteriskAmiService.php                # AMI socket client

resources/js/
├── pages/agent/
│   ├── CampaignSelect.vue                    # Campaign selection
│   └── Workspace.vue                         # Main agent UI
├── components/agent/
│   ├── AgentStatusBar.vue
│   ├── CallPanel.vue
│   ├── ManualDialer.vue
│   └── DispositionModal.vue
└── composables/
    ├── useAgentSession.ts
    └── useCallState.ts                       # WebSocket listener
```

## User Setup

Each agent needs:
1. A local account (register at `/register`)
2. VICIdial credentials saved at `/settings/profile` → VICIdial Credentials:
   - VICIdial username and password
   - Phone extension login and password (matching a `phones` record in VICIdial)

## Development Commands

```bash
# Run tests
php artisan test --compact

# Format PHP code
vendor/bin/pint

# Run all checks
composer run test

# Generate Wayfinder route types
php artisan wayfinder:generate
```

## Routes

| Method | Path | Name | Description |
|---|---|---|---|
| `GET` | `/agent/campaigns` | `agent.campaigns` | Campaign selection |
| `POST` | `/agent/session` | `agent.session.store` | Login to campaign |
| `DELETE` | `/agent/session` | `agent.session.destroy` | Logout |
| `PUT` | `/agent/status` | `agent.status.update` | Set ready/paused |
| `GET` | `/agent/workspace` | `agent.workspace` | Main agent view |
| `POST` | `/agent/call/dial` | `agent.call.dial` | Manual dial |
| `POST` | `/agent/call/disposition` | `agent.call.disposition` | Submit disposition |
