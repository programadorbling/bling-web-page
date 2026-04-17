# Bling Network — Project Context

## Stack
- Frontend: HTML + CSS + JS Vanilla (NO frameworks)
- Backend: PHP 8.4
- DB: MySQL via PDO (prepared statements mandatory)
- Email: PHPMailer 6.9 via IONOS SMTP
- Hosting: IONOS Shared Hosting
- CSS Variables: --blue: #030081, --green: #d1d70d, --white: #ffffff
- Font: Montserrat (Google Fonts, already loaded)

## Existing file structure
- css/base.css — shared styles (DO NOT MODIFY)
- js/base.js — shared JS with initCursorHover() and observeReveal() (DO NOT MODIFY)
- Pattern: each page has its own css/[page].css and js/[page].js

## Current pages
- index.html, about.html, benefits.html, social-media.html

## Implementation in progress
- Feature: become-a-member.html
- Full spec: become-a-member-implementation.md (READ THIS BEFORE CODING)
- Follow the 6 milestones in order. Do not skip ahead.

## Database schema (become-a-member)
- 6 normalized tables: membership_requests (parent), membership_company,
  membership_services, membership_contacts (contact_order 1/2),
  membership_ownership, membership_references (reference_order 1/2)
- All child tables cascade delete on parent
- Inserts must use a single PDO transaction

## Security pattern — config.php
Every PHP file that requires config.php MUST define('BLING_APP', true)
as its absolute first statement before the require_once call.
config.php itself must NEVER contain define('BLING_APP', true).