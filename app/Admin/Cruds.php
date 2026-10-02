<?php

namespace App\Admin;

use App\Models\Faq;
use App\Models\HeroSlide;
use App\Models\Industry;
use App\Models\ServiceCategory;
use App\Models\Testimonial;

/**
 * Small list-with-modal managers (add / edit in a pop-up, drag to re-order, delete).
 * One generic controller serves all of them; each entry is its own component.
 */
class Cruds
{
    public static function all(): array
    {
        $icons = array_combine(Sections::icons(), Sections::icons());

        return [
            'slides' => [
                'model' => HeroSlide::class,
                'title' => 'Banner Slides',
                'singular' => 'slide',
                'thumb' => 'image',
                'primary' => 'title',
                'secondary' => 'kicker',
                'fields' => [
                    ['name' => 'kicker', 'label' => 'Small line (above title)', 'type' => 'text', 'rules' => 'nullable|string|max:150'],
                    ['name' => 'title', 'label' => 'Big title', 'type' => 'text', 'rules' => 'required|string|max:250'],
                    ['name' => 'text', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3, 'rules' => 'nullable|string|max:600'],
                    ['name' => 'btn1_label', 'label' => 'Button 1 text', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:60'],
                    ['name' => 'btn1_url', 'label' => 'Button 1 link', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:255', 'hint' => '/contact or https://…'],
                    ['name' => 'btn2_label', 'label' => 'Button 2 text', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:60'],
                    ['name' => 'btn2_url', 'label' => 'Button 2 link', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:255'],
                    ['name' => 'image', 'label' => 'Background image', 'type' => 'image', 'rules' => 'nullable|image|max:6144', 'required_on_create' => true, 'hint' => 'Wide image, at least 1920×900.'],
                    ['name' => 'is_active', 'label' => 'Show this slide', 'type' => 'switch'],
                ],
            ],

            'industries' => [
                'model' => Industry::class,
                'title' => 'Industries',
                'singular' => 'industry',
                'thumb' => 'image',
                'primary' => 'title',
                'fields' => [
                    ['name' => 'title', 'label' => 'Industry name', 'type' => 'text', 'col' => 8, 'rules' => 'required|string|max:120'],
                    ['name' => 'icon', 'label' => 'Icon', 'type' => 'select', 'options' => $icons, 'col' => 4, 'rules' => 'nullable|string|max:60'],
                    ['name' => 'image', 'label' => 'Image', 'type' => 'image', 'rules' => 'nullable|image|max:4096', 'required_on_create' => true],
                    ['name' => 'is_active', 'label' => 'Show on website', 'type' => 'switch'],
                ],
            ],

            'testimonials' => [
                'model' => Testimonial::class,
                'title' => 'Testimonials',
                'singular' => 'testimonial',
                'primary' => 'name',
                'secondary' => 'role',
                'fields' => [
                    ['name' => 'name', 'label' => 'Client name', 'type' => 'text', 'col' => 6, 'rules' => 'required|string|max:120'],
                    ['name' => 'role', 'label' => 'Role / company', 'type' => 'text', 'col' => 6, 'rules' => 'nullable|string|max:160'],
                    ['name' => 'text', 'label' => 'Review', 'type' => 'textarea', 'rows' => 4, 'rules' => 'required|string|max:600'],
                    ['name' => 'rating', 'label' => 'Rating', 'type' => 'select', 'options' => [5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'], 'col' => 6, 'rules' => 'required|integer|between:1,5'],
                    ['name' => 'is_active', 'label' => 'Show on website', 'type' => 'switch', 'col' => 6],
                ],
            ],

            'faqs' => [
                'model' => Faq::class,
                'title' => 'FAQs',
                'singular' => 'FAQ',
                'primary' => 'question',
                'fields' => [
                    ['name' => 'question', 'label' => 'Question', 'type' => 'text', 'rules' => 'required|string|max:300'],
                    ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rows' => 5, 'rules' => 'required|string|max:2000'],
                    ['name' => 'is_active', 'label' => 'Show on website', 'type' => 'switch'],
                ],
            ],

            'categories' => [
                'model' => ServiceCategory::class,
                'title' => 'Service Tags / Categories',
                'singular' => 'tag',
                'primary' => 'name',
                'secondary' => 'heading',
                'fields' => [
                    ['name' => 'name', 'label' => 'Tag name', 'type' => 'text', 'rules' => 'required|string|max:80|unique:service_categories,name', 'hint' => 'Shown on the service card, e.g. Electrical.'],
                    ['name' => 'heading', 'label' => 'Section heading on Services page', 'type' => 'text', 'rules' => 'nullable|string|max:150'],
                    ['name' => 'description', 'label' => 'Section sub text', 'type' => 'textarea', 'rows' => 2, 'rules' => 'nullable|string|max:300'],
                    ['name' => 'is_active', 'label' => 'Active', 'type' => 'switch'],
                ],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
