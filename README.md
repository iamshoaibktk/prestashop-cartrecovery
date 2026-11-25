# Abandoned Cart Recovery: free abandoned cart reminder module for PrestaShop 8 and 9

**Abandoned Cart Recovery** is a free, open-source **PrestaShop abandoned cart module**. It e-mails customers and guests who left products in their cart, with a button that logs them in and opens their exact cart, an optional personal discount code and an unsubscribe link. Up to three reminders per cart, sent by cron or by hand.

- Up to 3 reminders per cart, each with its own delay, text and discount
- Unique single-use discount codes, optionally applied to the cart already
- Opens, clicks, recovered carts and recovered revenue
- 10 languages, works without any third-party service

**[Download the latest release](https://github.com/iamshoaibktk/prestashop-cartrecovery/releases/latest)**

## Compatibility
- PrestaShop 8.0 to 9.x (tested on 8.2.9 and 9.2.1)
- PHP 7.2.5 or newer

## Features
- **Reminders**: up to 3 per cart, delays in hours from the last cart change, automatic (hourly cron URL) or manual, one by one or in bulk. A cart is never e-mailed twice by the cron, and two reminders are at least an hour apart.
- **Stops by itself** when the customer places an order (that cart or any order since), unsubscribes, or the cart is replaced by a newer one.
- **Guests too**: any guest who typed an e-mail at checkout can be reminded; the link restores their session.
- **E-mail content** per reminder and per language: subject, title and a rich-text message with variables ({firstname}, {cart_products}, {cart_link}, {discount_block}, {unsubscribe_url} and more).
- **4 designs** (Classic, Bold, Minimal, Showcase) with colours, button text, tagline, reassurance strip, footer and banner image, and a live desktop/mobile preview using a real cart.
- **Discount codes**: percentage, fixed amount or free shipping, validity in days, minimum cart, code prefix. One code per customer and cart, usable once, repeated by later reminders, deleted a day after expiry when unused.
- **One-off reminder**: write a personal e-mail for one cart (design, text, discount) without touching the saved settings.
- **Abandoned carts list** with status (not reminded, reminder N sent, recovered with the order, unsubscribed), language, guest tag, code, opened/clicked, filters and pagination.
- **Stats**: abandoned, reminded, opened, clicked, recovered, recovery rate and recovered revenue (last 30 days).
- **Unsubscribe**: link in every e-mail, confirmation page in the shop theme, RFC 8058 one-click header (Gmail, Apple Mail). Unsubscribed customers never get reminders, manual ones included.
- **Audience**: all customers, newsletter subscribers only, or partner-offer opt-ins only.
- **Privacy**: open/click tracking can be switched off; export and delete through PrestaShop's official GDPR module.
- **Multistore**: settings per shop, one cron URL per shop.

## Languages
English, French, Spanish, Italian, Polish, German, Czech, Dutch, Portuguese, Russian (back office, e-mails and default texts). User guides in all ten are in `docs/`.

## Installation
1. Download the release zip.
2. Back office › Modules › Module Manager › **Upload a module**.
3. Configure: check the texts, send a test e-mail, add the cron URL to your hosting (every hour) and switch automatic sending on.

## Author and support
- Email: iamshoaibktk@gmail.com
- WhatsApp: +923459016286
- Fiverr: https://www.fiverr.com/shoaibktk
- GitHub: https://github.com/iamshoaibktk

## License
[Academic Free License 3.0 (AFL-3.0)](https://opensource.org/licenses/AFL-3.0)
