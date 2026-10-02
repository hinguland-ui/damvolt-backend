<?php

namespace App\Admin;

/**
 * Admin form schema. Every entry is one self-contained "component" (its own tab, its own form,
 * its own save + validation) stored as one JSON row in `settings`.
 *
 * Field types: text, textarea, url, email, number, image, select, repeater.
 */
class Sections
{
    /** Icon names the React site knows about (see frontend/src/components/Icon.jsx). */
    public static function icons(): array
    {
        return [
            'Zap', 'ShieldCheck', 'BadgeCheck', 'Clock', 'Headset', 'IndianRupee', 'Wrench', 'Activity', 'Anvil', 'Bot',
            'Building2', 'Cable', 'Car', 'Cpu', 'Factory', 'FlaskConical', 'Gauge', 'LayoutPanelTop', 'PlugZap', 'Sun',
            'Warehouse', 'Wind', 'Workflow', 'Award', 'Users', 'Hammer', 'Settings', 'Truck', 'ThumbsUp', 'Lightbulb',
            'Battery', 'Cog', 'HardHat', 'Shield', 'Star', 'Leaf', 'Boxes', 'Package', 'Plug', 'Power', 'Hospital', 'Ship',
            'Droplets', 'Flame', 'Fuel', 'Rocket', 'Handshake', 'Target', 'Globe', 'Layers', 'CircuitBoard',
            'ClipboardCheck', 'FileCheck', 'Timer', 'Sparkles', 'Microscope', 'Construction', 'Network', 'ServerCog',
        ];
    }

    private static function iconField(string $name = 'icon'): array
    {
        return ['name' => $name, 'label' => 'Icon', 'type' => 'select', 'options' => array_combine(self::icons(), self::icons()), 'col' => 4, 'rules' => 'nullable|string|max:60'];
    }

    public static function groups(): array
    {
        return [
            'home' => [
                'title' => 'Home Page',
                'subtitle' => 'Every section of the home page. Changes go live on the website within a minute.',
                'tabs' => [
                    ['key' => 'seo', 'store' => 'home.seo', 'title' => 'SEO', 'icon' => 'bi-search',
                        'help' => 'Meta tags for the home page (shown in Google results and when the link is shared).',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => 'meta_keywords', 'label' => 'Meta keywords', 'type' => 'text', 'rules' => 'nullable|string|max:500', 'hint' => 'Comma separated.'],
                            ['name' => 'og_image', 'label' => 'Share image (Open Graph)', 'type' => 'image', 'rules' => 'nullable|image|max:4096', 'hint' => 'Recommended 1200×630.'],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/', 'homepage' => true],
                        ]],

                    ['key' => 'slides', 'title' => 'Banner Slides', 'icon' => 'bi-images', 'crud' => 'slides',
                        'help' => 'Hero slider at the top of the home page. Drag to re-order. Add as many slides as you like — they loop automatically.'],

                    ['key' => 'trust', 'store' => 'home.trust', 'title' => 'Trust Bar', 'icon' => 'bi-shield-check',
                        'help' => 'The four short points shown right below the banner.',
                        'fields' => [
                            ['name' => 'items', 'label' => 'Points', 'type' => 'repeater', 'add' => 'Add point', 'max' => 6, 'fields' => [
                                self::iconField(),
                                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 4, 'rules' => 'required|string|max:80'],
                                ['name' => 'text', 'label' => 'Small text', 'type' => 'text', 'col' => 4, 'rules' => 'nullable|string|max:120'],
                            ]],
                        ]],

                    ['key' => 'about', 'store' => 'home.about', 'title' => 'About Section', 'icon' => 'bi-info-circle',
                        'help' => 'Image + intro text + bullet points + “years of experience” badge.',
                        'fields' => [
                            ['name' => 'eyebrow', 'label' => 'Small heading', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:80'],
                            ['name' => 'title', 'label' => 'Main title', 'type' => 'text', 'col' => 6, 'rules' => 'required|string|max:200'],
                            ['name' => 'lead', 'label' => 'Lead paragraph', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:600'],
                            ['name' => 'text', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:1200'],
                            ['name' => 'image', 'label' => 'Image', 'type' => 'image', 'rules' => 'nullable|image|max:4096'],
                            ['name' => 'badge_value', 'label' => 'Badge number (e.g. 10+)', 'type' => 'text', 'col' => 3, 'rules' => 'nullable|string|max:20'],
                            ['name' => 'badge_label', 'label' => 'Badge text', 'type' => 'text', 'col' => 3, 'rules' => 'nullable|string|max:60'],
                            ['name' => 'button_label', 'label' => 'Button text', 'type' => 'text', 'col' => 3, 'rules' => 'nullable|string|max:60'],
                            ['name' => 'button_url', 'label' => 'Button link', 'type' => 'text', 'col' => 3, 'rules' => 'nullable|string|max:255'],
                            ['name' => 'points', 'label' => 'Bullet points', 'type' => 'repeater', 'add' => 'Add point', 'max' => 10, 'fields' => [
                                ['name' => 'text', 'label' => 'Point', 'type' => 'text', 'col' => 12, 'rules' => 'required|string|max:120'],
                            ]],
                        ]],

                    ['key' => 'services', 'store' => 'home.services', 'title' => 'Services Heading', 'icon' => 'bi-gear',
                        'help' => 'Heading above the services carousel. The cards themselves come from Services → All Services.',
                        'fields' => [
                            ['name' => 'eyebrow', 'label' => 'Small heading', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:80'],
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 6, 'rules' => 'required|string|max:200'],
                            ['name' => 'text', 'label' => 'Sub text', 'type' => 'text', 'col' => 8, 'rules' => 'nullable|string|max:300'],
                            ['name' => 'link_label', 'label' => 'Link text', 'type' => 'text', 'col' => 4, 'rules' => 'nullable|string|max:60'],
                        ]],

                    ['key' => 'stats', 'store' => 'home.stats', 'title' => 'Stats / Numbers', 'icon' => 'bi-123',
                        'help' => 'Animated counters (also used on the About page).',
                        'fields' => [
                            ['name' => 'eyebrow', 'label' => 'Small heading', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:80'],
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:200'],
                            ['name' => 'items', 'label' => 'Counters', 'type' => 'repeater', 'add' => 'Add counter', 'max' => 8, 'fields' => [
                                ['name' => 'value', 'label' => 'Number', 'type' => 'number', 'col' => 3, 'rules' => 'required|integer|min:0'],
                                ['name' => 'suffix', 'label' => 'Suffix (+, /7, %)', 'type' => 'text', 'col' => 3, 'rules' => 'nullable|string|max:6'],
                                ['name' => 'label', 'label' => 'Label', 'type' => 'text', 'col' => 6, 'rules' => 'required|string|max:80'],
                            ]],
                        ]],

                    ['key' => 'why', 'store' => 'home.why', 'title' => 'Why Choose Us', 'icon' => 'bi-patch-check',
                        'help' => 'Safety first, Quality equipment … (also used on the About page).',
                        'fields' => [
                            ['name' => 'eyebrow', 'label' => 'Small heading', 'type' => 'text', 'col' => 4, 'rules' => 'nullable|string|max:80'],
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:200'],
                            ['name' => 'text', 'label' => 'Sub text', 'type' => 'text', 'rules' => 'nullable|string|max:300'],
                            ['name' => 'items', 'label' => 'Points', 'type' => 'repeater', 'add' => 'Add point', 'max' => 12, 'fields' => [
                                self::iconField(),
                                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:80'],
                                ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'rules' => 'nullable|string|max:300'],
                            ]],
                        ]],

                    ['key' => 'process', 'store' => 'home.process', 'title' => 'How We Work', 'icon' => 'bi-diagram-3',
                        'help' => 'Step by step process. Step numbers (01, 02 …) are generated from the order.',
                        'fields' => [
                            ['name' => 'eyebrow', 'label' => 'Small heading', 'type' => 'text', 'col' => 4, 'rules' => 'nullable|string|max:80'],
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:200'],
                            ['name' => 'items', 'label' => 'Steps', 'type' => 'repeater', 'add' => 'Add step', 'max' => 8, 'fields' => [
                                ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 4, 'rules' => 'required|string|max:80'],
                                ['name' => 'text', 'label' => 'Text', 'type' => 'text', 'col' => 8, 'rules' => 'nullable|string|max:300'],
                            ]],
                        ]],

                    ['key' => 'industries', 'store' => 'home.industries', 'title' => 'Industries', 'icon' => 'bi-buildings', 'crud' => 'industries',
                        'help' => 'Heading and the industry cards (also shown on the Industries page).',
                        'fields' => [
                            ['name' => 'eyebrow', 'label' => 'Small heading', 'type' => 'text', 'col' => 4, 'rules' => 'nullable|string|max:80'],
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:200'],
                            ['name' => 'text', 'label' => 'Sub text', 'type' => 'text', 'col' => 8, 'rules' => 'nullable|string|max:300'],
                            ['name' => 'link_label', 'label' => 'Link text', 'type' => 'text', 'col' => 4, 'rules' => 'nullable|string|max:60'],
                        ]],

                    ['key' => 'reviews', 'store' => 'home.reviews', 'title' => 'Testimonials', 'icon' => 'bi-chat-quote', 'crud' => 'testimonials',
                        'help' => 'Heading and the client reviews that scroll across the page.',
                        'fields' => [
                            ['name' => 'badge', 'label' => 'Badge text', 'type' => 'text', 'col' => 5, 'rules' => 'nullable|string|max:120'],
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'col' => 7, 'rules' => 'required|string|max:200'],
                        ]],

                    ['key' => 'cta', 'store' => 'home.cta', 'title' => 'Call-to-action', 'icon' => 'bi-megaphone',
                        'help' => 'The blue banner near the bottom of most pages.',
                        'fields' => [
                            ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'rules' => 'required|string|max:200'],
                            ['name' => 'text', 'label' => 'Text', 'type' => 'textarea', 'rows' => 2, 'rules' => 'nullable|string|max:400'],
                        ]],
                ],
            ],

            'settings' => [
                'title' => 'Site Settings',
                'subtitle' => 'Logos, contact details and business information used across the website and this panel.',
                'tabs' => [
                    ['key' => 'brand', 'store' => 'brand', 'title' => 'Brand & Logos', 'icon' => 'bi-palette',
                        'help' => 'Logo, footer logo and favicon are used on the website and in this admin panel.',
                        'fields' => [
                            ['name' => 'site_name', 'label' => 'Full site / company name', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:200'],
                            ['name' => 'short_name', 'label' => 'Short name', 'type' => 'text', 'col' => 4, 'rules' => 'required|string|max:60'],
                            ['name' => 'tagline', 'label' => 'Tagline', 'type' => 'text', 'rules' => 'nullable|string|max:200'],
                            ['name' => 'description', 'label' => 'Short description (footer & default SEO)', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:600'],
                            ['name' => 'logo', 'label' => 'Logo (header)', 'type' => 'image', 'col' => 4, 'rules' => 'nullable|image|max:4096', 'hint' => 'Dark/colour logo for light backgrounds.'],
                            ['name' => 'footer_logo', 'label' => 'Footer logo', 'type' => 'image', 'col' => 4, 'rules' => 'nullable|image|max:4096', 'hint' => 'Light/white logo for dark backgrounds — also used in this panel’s sidebar.', 'dark' => true],
                            ['name' => 'favicon', 'label' => 'Favicon', 'type' => 'image', 'col' => 4, 'rules' => 'nullable|image|max:2048', 'hint' => 'Square PNG, at least 64×64.'],
                        ]],

                    ['key' => 'contact', 'store' => 'contact', 'title' => 'Contact', 'icon' => 'bi-telephone',
                        'help' => 'Two emails and two phone numbers are supported.',
                        'fields' => [
                            ['name' => 'email_1', 'label' => 'Email 1 (primary)', 'type' => 'email', 'col' => 6, 'rules' => 'nullable|email|max:150'],
                            ['name' => 'email_2', 'label' => 'Email 2', 'type' => 'email', 'col' => 6, 'rules' => 'nullable|email|max:150'],
                            ['name' => 'phone_1', 'label' => 'Phone 1 (primary)', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:30'],
                            ['name' => 'phone_2', 'label' => 'Phone 2', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:30'],
                            ['name' => 'whatsapp', 'label' => 'WhatsApp number', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:20', 'hint' => 'Digits only with country code, e.g. 919634184251.'],
                        ]],

                    ['key' => 'business', 'store' => 'business', 'title' => 'Business Info', 'icon' => 'bi-briefcase',
                        'help' => 'Registration details and working hours.',
                        'fields' => [
                            ['name' => 'gst_number', 'label' => 'GST number', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:30'],
                            ['name' => 'cin', 'label' => 'CIN / registration no.', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:40'],
                            ['name' => 'hours', 'label' => 'Working hours', 'type' => 'text', 'rules' => 'nullable|string|max:150', 'hint' => 'e.g. Mon – Sat, 9:30 AM – 6:30 PM'],
                        ]],

                    ['key' => 'offices', 'store' => 'offices', 'title' => 'Offices', 'icon' => 'bi-geo-alt',
                        'help' => 'The first office is used for the map and legal-page address.',
                        'fields' => [
                            ['name' => 'items', 'label' => 'Offices', 'type' => 'repeater', 'add' => 'Add office', 'max' => 6, 'fields' => [
                                ['name' => 'label', 'label' => 'Label', 'type' => 'text', 'col' => 4, 'rules' => 'required|string|max:80'],
                                ['name' => 'address', 'label' => 'Address', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:300'],
                                ['name' => 'map', 'label' => 'Google Maps embed URL', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:1000'],
                                ['name' => 'map_link', 'label' => 'Directions link', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:1000'],
                            ]],
                        ]],

                    ['key' => 'social', 'store' => 'social', 'title' => 'Social Links', 'icon' => 'bi-share',
                        'help' => 'Social profile links shown in the website footer.',
                        'fields' => [
                            ['name' => 'facebook', 'label' => 'Facebook', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:255'],
                            ['name' => 'instagram', 'label' => 'Instagram', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:255'],
                            ['name' => 'linkedin', 'label' => 'LinkedIn', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:255'],
                            ['name' => 'youtube', 'label' => 'YouTube', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:255'],
                        ]],

                    ['key' => 'smtp', 'store' => 'smtp', 'title' => 'SMTP & Email', 'icon' => 'bi-envelope-at', 'after' => 'admin.sections.smtp-test',
                        'help' => 'Every contact-form enquiry from the website is e-mailed to you, and the customer gets a confirmation. Enter your mail server below, then use “Send test email”.',
                        'fields' => [
                            ['name' => 'receive_email', 'label' => 'Receive form emails at', 'type' => 'text', 'rules' => 'nullable|string|max:300', 'hint' => 'Your address for new enquiries. Separate several with commas.'],
                            ['name' => 'host', 'label' => 'SMTP host', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:200', 'hint' => 'e.g. smtp.gmail.com, smtp.zoho.in'],
                            ['name' => 'port', 'label' => 'Port', 'type' => 'number', 'col' => 3, 'rules' => 'nullable|integer|between:1,65535', 'hint' => '587 (TLS) or 465 (SSL)'],
                            ['name' => 'encryption', 'label' => 'Encryption', 'type' => 'select', 'col' => 3, 'options' => ['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'none' => 'None'], 'rules' => 'required|in:tls,ssl,none'],
                            ['name' => 'username', 'label' => 'SMTP username', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:200'],
                            ['name' => 'password', 'label' => 'SMTP password', 'type' => 'password', 'col' => 6, 'rules' => 'nullable|string|max:200', 'hint' => 'Stored encrypted. Click the eye to view it. Gmail needs an App Password.'],
                            ['name' => 'from_email', 'label' => 'From email', 'type' => 'email', 'col' => 6, 'rules' => 'nullable|email|max:150', 'hint' => 'Usually the same as the username.'],
                            ['name' => 'from_name', 'label' => 'From name', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:100'],
                            ['name' => 'send_confirmation', 'label' => 'Also send a confirmation email to the customer', 'type' => 'switch'],
                        ]],

                    ['key' => 'recaptcha', 'store' => 'recaptcha', 'title' => 'reCAPTCHA', 'icon' => 'bi-shield-lock', 'after' => 'admin.sections.recaptcha-preview',
                        'help' => 'Adds Google’s “I’m not a robot” check to the website contact form and to this admin login, to stop spam and bots.',
                        'fields' => [
                            ['name' => 'enabled', 'label' => 'Enable reCAPTCHA (v2 checkbox)', 'type' => 'switch', 'default' => false],
                            ['name' => 'site_key', 'label' => 'Site key', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:200', 'hint' => 'Public key — used in the browser.'],
                            ['name' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'col' => 6, 'rules' => 'nullable|string|max:200', 'hint' => 'Private key — stored encrypted. Click the eye to view it.'],
                        ]],
                ],
            ],
            'seo' => [
                'title' => 'Page SEO',
                'subtitle' => 'Meta title and description for the main pages. Services and legal pages have their own SEO box on their edit screen; the home page SEO is in Home Page → SEO.',
                'tabs' => [
                    ['key' => 'about', 'store' => 'seo.about', 'title' => 'About Us', 'icon' => 'bi-info-circle',
                        'help' => 'Title and description Google shows for /about. Leave blank to use the default.',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/about', 'fallbackTitle' => 'About Us'],
                        ]],

                    ['key' => 'services', 'store' => 'seo.services', 'title' => 'Services page', 'icon' => 'bi-gear',
                        'help' => 'Title and description Google shows for /services. Leave blank to use the default.',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/services', 'fallbackTitle' => 'Services page'],
                        ]],

                    ['key' => 'industries', 'store' => 'seo.industries', 'title' => 'Industries', 'icon' => 'bi-buildings',
                        'help' => 'Title and description Google shows for /industries. Leave blank to use the default.',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/industries', 'fallbackTitle' => 'Industries'],
                        ]],

                    ['key' => 'contact', 'store' => 'seo.contact', 'title' => 'Contact', 'icon' => 'bi-telephone',
                        'help' => 'Title and description Google shows for /contact. Leave blank to use the default.',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/contact', 'fallbackTitle' => 'Contact'],
                        ]],

                    ['key' => 'careers', 'store' => 'seo.careers', 'title' => 'Careers', 'icon' => 'bi-briefcase',
                        'help' => 'Title and description Google shows for /careers. Leave blank to use the default.',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/careers', 'fallbackTitle' => 'Careers'],
                        ]],

                    ['key' => 'faq', 'store' => 'seo.faq', 'title' => 'FAQs', 'icon' => 'bi-question-circle',
                        'help' => 'Title and description Google shows for /faq. Leave blank to use the default.',
                        'fields' => [
                            ['name' => 'meta_title', 'label' => 'Meta title', 'type' => 'text', 'rules' => 'nullable|string|max:120', 'hint' => 'Ideal: 50–60 characters.', 'counter' => 60],
                            ['name' => 'meta_description', 'label' => 'Meta description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:320', 'hint' => 'Ideal: 120–160 characters.', 'counter' => 160],
                            ['name' => '_serp', 'type' => 'serp', 'titleInput' => '[name=meta_title]', 'descInput' => '[name=meta_description]', 'path' => '/faq', 'fallbackTitle' => 'FAQs'],
                        ]],
                ],
            ],
        ];
    }

    public static function group(string $key): ?array
    {
        return self::groups()[$key] ?? null;
    }

    public static function tab(string $group, string $key): ?array
    {
        foreach (self::group($group)['tabs'] ?? [] as $tab) {
            if ($tab['key'] === $key) {
                return $tab;
            }
        }

        return null;
    }
}
