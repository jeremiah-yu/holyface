<?php
declare(strict_types=1);

/**
 * Promotional website configuration.
 * Swap the logo file at public/assets/img/hf-logo.png (or change school.logo).
 */
return [
    'api_base_url' => getenv('AMGS_API_URL') ?: 'http://localhost/holyface/api/public',
    /** Leave empty to auto-detect from the request (works under XAMPP and PHP built-in server). */
    'web_base' => getenv('AMGS_WEB_BASE') ?: '',
    'site_url' => getenv('AMGS_SITE_URL') ?: '',
    'portal_url' => 'https://www.holyface.school/php/landing/login-portal.php',
    'official_website' => 'https://www.holyface.school/',
    'contact_email' => 'registrar@holyface.school',
    'contact_phone' => '(02) 8546-8364',
    'dpo_name' => 'Registrar (acting Data Protection Officer until the School designates a named DPO)',
    'dpo_email' => 'registrar@holyface.school',
    'school' => [
        'name' => 'HOLY FACE OF JESUS LYCEUM OF SAN JOSE, INC.',
        'short' => 'Holy Face',
        'address' => 'R & J Bldg., Blk 09, Lot 6 & 8, Mayon Ave., Amityville, San Jose, Rodriguez, Rizal, Philippines',
        'logo' => 'assets/img/hf-seal.png',
        'logo_absolute' => 'https://www.holyface.school/assets/img/branding/hf-logo.png',
    ],
    'promise' => 'Track progress. Learn better.',
    'strands' => [
        [
            'code' => 'ABM',
            'name' => 'Accountancy, Business & Management',
            'blurb' => 'The ABM strand also helps students develop critical thinking and decision-making skills essential in the corporate world. It prepares learners to become future entrepreneurs, business leaders, or professionals in finance and management.',
        ],
        [
            'code' => 'HUMSS',
            'name' => 'Humanities & Social Science',
            'blurb' => 'The HUMSS strand also enhances students\' abilities in writing, speaking, and analyzing social issues. It encourages a deeper understanding of human behavior and societal development, preparing them to make a meaningful impact in their chosen fields.',
        ],
        [
            'code' => 'ICT',
            'name' => 'Information & Communication Technology',
            'blurb' => 'The ICT strand also strengthens problem-solving and logical thinking skills through hands-on technical training. It empowers students to innovate and adapt in the fast-evolving world of information and communication technology.',
        ],
        [
            'code' => 'HE',
            'name' => 'Home Economics',
            'blurb' => 'The HE strand also promotes creativity, entrepreneurship, and hands-on experience in various service industries. It equips students with real-world skills that can lead to immediate employment or successful small business ventures.',
        ],
        [
            'code' => 'STEM',
            'name' => 'Science, Technology, Engineering & Mathematics',
            'blurb' => 'The STEM strand focuses on science, technology, engineering, and mathematics, equipping students with critical skills for careers in fields like medicine, engineering, and computer science. It combines theory with hands-on learning experiences.',
        ],
    ],
];
