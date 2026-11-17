# PS Pen & Paper

[Deutsch](README.md) | **English**

[![Version](https://img.shields.io/badge/Version-1.0.0-2271b1?style=flat-square)](readme.txt)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=flat-square&logo=php&logoColor=white)
![WordPress](https://img.shields.io/badge/WordPress-up%20to%207.1.4-21759b?style=flat-square&logo=wordpress&logoColor=white)
![ClassicPress](https://img.shields.io/badge/ClassicPress-2.7.4-03768e?style=flat-square)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-2ea44f?style=flat-square)](https://www.gnu.org/licenses/gpl-2.0.html)

PS Pen & Paper is a system-independent toolkit for creating and running
pen-and-paper campaigns on WordPress and ClassicPress. It provides the tools;
game masters create their own worlds, rules, characters, and adventures.

> **Beta 1.0.0:** This release is intended for testing and feedback. Back up
> important game data before testing. Features and workflows may change.

## Documentation and demo

- [German plugin handbook with screenshots](docs/handbuch.md)
- [German PS Events integration guide](docs/erweiterung-ps-events.md)
- [German walkthrough of the demo campaign](docs/demokampagne.md)
- [Development roadmap](ROADMAP.md)

The demo is a campaign on the development site. It is not automatically
installed and does not define a required game system.

## Features

### Universes, campaigns, and rules

- Organize campaigns under universes. Every new campaign requires a universe;
  if you manage exactly one universe, it is assigned automatically.
- Keep campaign membership, permissions, and chat access separate from
  universe membership and management.
- Build system-independent rules with custom character fields, attributes,
  resources, dice, point allocation, and progression.
- Set universe rules as fixed or explicitly allow campaign-level changes.
  Compatible updates are inherited; conflicts can block new games until they
  are reviewed.
- Inherit status definitions and economy/trade rules under the same fixed or
  delegated policy. Stored character consequences and balances are not silently
  changed when policies change.

### Characters and game sessions

- Create character sheets with custom fields, equipment, inventory, and
  optional portraits.
- Choose whether players can edit fields directly, submit changes for approval,
  or leave editing to the game master.
- Use shared universe characters or explicitly continue with independent
  campaign copies.
- Roll common dice (d4, d6, d8, d10, d12, d20, and d100), with configurable
  expressions, visibility, and a protected game log.
- Run guided sessions, solo adventures, and prepared group adventures.
- Review a character's campaign results when a session ends.

### Story and adventure tools

- Prepare chapters, scenes, maps, NPCs, encounters, handouts, and secrets.
- Keep drafts and private notes separate from content explicitly released to
  players.
- Build adventures with choices, optional checks, costs, rewards, and
  configurable conditions.
- Use optional group decisions, voting, unanimity, or game-master approval.
- Configure character conditions, consequences, action restrictions, and
  resource thresholds.

### Economy and optional integrations

Economy, inventory, equipment, and player-to-player trade require the optional
**PS Community** plugin. When it is unavailable, existing data is retained but
related changes are disabled. The rest of the campaign and adventure tools
remain available.

- Configure currencies, balances, item binding, and trade rules.
- Share universe-level account balances only under the authority configured by
  the universe manager.
- Explicit economy changes do not transfer or convert existing balances.
  Existing accounts, shared character sheets, or open trades may prevent a
  change.
- Optionally connect **PS Chat** for campaign chat, **PS Wiki** for a public
  world encyclopedia, and **PS Events / Events & Bookings** for scheduling.
  These integrations are not required for core gameplay.

## Privacy and data safety

Campaigns and character data are private by default. A universe assignment does
not grant access to a campaign, its members, private notes, or chat. Player
access is checked against current membership and permissions.

Campaign export and restore tools provide private previews and explicit
confirmation. An export can contain secrets and private records; protect it
like a backup. It is **not** a complete website backup and does not include
user credentials or external plugin data.

Rule updates do not silently rewrite existing character values or balances.
Prepared round snapshots remain separate, while explicitly revoked universe
permissions can still apply to existing rounds. Some database-backed updates
require InnoDB and a standard `mysqli` connection. The full range of database
drop-ins, cache setups, and platform configurations has not yet been verified.

Exclude the game-area page, including its query parameters, from page caches and
CDNs. Private cache headers cannot prevent a cache that responds before
WordPress runs.

## Installation

1. Upload the `pen-and-classicpress` directory to `/wp-content/plugins/`, or
   install the plugin ZIP through the WordPress/ClassicPress admin area.
2. Activate **PS Pen & Paper**.
3. Publish a page containing `[ps_pen_paper]` to create the game area.
4. Grant campaign-creation permissions, create a universe, and configure its
   rules before creating campaigns.
5. Install and activate optional companion plugins only for the features that
   need them.

The plugin uses the current theme's page layout by default. A separate
**PS Pen & Paper – Game Area** page template is also available. The plugin does
not create a page automatically.

## Requirements and compatibility

- WordPress 4.9 or later; the plugin readme metadata lists WordPress 7.1.4.
- ClassicPress 2.7 or later; the plugin readme metadata lists ClassicPress
  2.7.4.
- PHP 8.0 or later.
- Some profile, friendship, inventory, economy, and trade features require
  PS Community. Other integrations are optional.

The metadata requirements are not a substitute for a complete compatibility
matrix. Testing across all supported WordPress, ClassicPress, PHP, database,
and integration combinations is still in progress.

## Development and tests

The repository includes focused WP-CLI integration tests under `tests/`.
Run database integration tests serially against a disposable development site;
tests create temporary users and campaign data and remove their fixtures.
Some integration tests also require a neighboring PS Community, PS Chat,
PS Wiki, or Events & Bookings installation.

Example:

```sh
wp --url=https://example.test/ --skip-plugins --skip-themes \
  eval-file wp-content/plugins/pen-and-classicpress/tests/rules-inheritance-integration.php
```

The test suites cover permissions and privacy, rules and inheritance,
characters and conditions, dice, equipment, economy and trade, adventures,
rounds, and campaign backup/restore. Passing targeted tests does not establish
compatibility with every hosting environment.

## Translations

The text domain is `ps-pen-paper`. The repository includes the POT template at
[`languages/ps-pen-paper.pot`](languages/ps-pen-paper.pot). This README is
available in English, but a complete English translation of the plugin
interface is not included.

## Beta scope and known limitations

- The plugin does not include a fixed rulebook, setting, or story.
- Automatic combat rules are not implemented.
- This is a beta release; report issues with reproduction steps and the
  WordPress or ClassicPress version in use.
- See the [roadmap](ROADMAP.md) for known open work and platform validation.

## Changelog

### 1.0.0 (Beta)

- First beta release of the universal pen-and-paper toolkit.
- Universes, campaigns, configurable rules, characters, dice, and protected
  game logs.
- Story preparation, solo and group adventures, and campaign sessions.
- Fixed or delegated inheritance for character rules, conditions, and economy.
- Private campaign export and restore with validation and explicit
  confirmation.
