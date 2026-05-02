# wg-membership-suite

WordPress plugin for WebGefährte membership, checkout, LMS access, user profiles, offers, orders, and integrations with GenerateBlocks and Contact Form 7.

## Scope

This plugin contains membership-specific functionality, including:

- user account and profile logic
- login/logout integrations
- checkout and order processing
- offer and order custom post types
- LMS/course access logic
- Contact Form 7 checkout integration
- GenerateBlocks placeholder and dynamic content integrations
- MediaElement video playback enhancements

## Architecture

- `wg-membership-suite`: Membership, checkout, LMS, login, orders, offers
- `custom-functionality-deployment`: shared site-wide functionality
- child theme `functions.php`: site-specific exceptions only

## Status

Private internal WordPress plugin.
