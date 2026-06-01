=== Formhammer ===
Contributors: oberhammert
Tags: spam, anti-spam, honeypot, contact-form, form-protection
Requires at least: 6.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
Formhammer blocks form spam on WordPress without CAPTCHA and without external APIs.

It works with Contact Form 7, WPForms, Elementor Forms, and Gravity Forms using three server-side checks:

* Honeypot field: a hidden input that real users never fill.
* Timing check: submissions faster than about 1.5 seconds are rejected as suspicious.
* HMAC token: a server-generated token validated on submit to prevent replay attacks.

Formhammer does not store form submissions and does not rely on services like Cloudflare Turnstile, reCAPTCHA, or hCaptcha.

It stops most automated spam bots while keeping the user experience simple and fast. It does not stop targeted headless browser attacks.

== Installation ==
1. Upload the `formhammer` folder to `/wp-content/plugins/`.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Review the Formhammer settings under `Settings > Formhammer`.
4. Ensure your supported forms are using the plugin's injected fields and validation hooks.

== Frequently Asked Questions ==
= Does Formhammer use CAPTCHA? =
No. Formhammer does not use CAPTCHA, challenge widgets, or external verification services.

= Does Formhammer store form submissions? =
No. Formhammer does not store submitted form payloads. It only evaluates submissions server-side.

= Which form plugins are supported? =
Formhammer currently supports Contact Form 7, WPForms, Elementor Forms, and Gravity Forms.

= What kind of spam does it block? =
It is designed to stop common automated spam bots and low-effort spam submissions.

= Will it stop advanced attacks? =
No. It is not a defense against targeted headless browser attacks or sophisticated manual abuse.

== Changelog ==
= 1.0.0 =
* Initial public release.
* Added spam protection for Contact Form 7, WPForms, Elementor Forms, and Gravity Forms.
* Added honeypot, timing check, and HMAC token validation.

== Upgrade Notice ==
= 1.0.0 =
Initial public release of Formhammer.
