<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title( '|', true, 'right' ); ?><?php bloginfo('name'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div class="gradient-bg"></div>

<header class="main-header">
    <div class="header-inner">

        <!-- Left: Logo -->
        <a href="#top" class="logo">KYLAS PRO</a>

        <!-- Centre: Nav links -->
        <nav class="main-nav" aria-label="Primary navigation">
            <a href="#top" class="nav-link">Home</a>
            <a href="#features" class="nav-link">Features</a>
        </nav>

        <!-- Right: CTA -->
        <div class="nav-cta-wrap">
            <a href="#contact-form" class="btn-cta">Contact Us</a>
        </div>

    </div>
</header>
