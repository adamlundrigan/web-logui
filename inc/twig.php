<?php

require_once 'locale.php';

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\YamlFileLoader;

$loader = new FilesystemLoader('./templates/');
$twig = new Environment($loader, ['cache' => '/tmp/twigcache/']);

$translator = new Translator('en_US');
$translator->addLoader('yaml', new YamlFileLoader());
$translator->addResource('yaml', 'locale/'.$preferred_language.'.yaml', 'en_US');

$twig->addExtension(new TranslationExtension($translator));

$twigGlobals = [
  'theme'               => $settings->getTheme(),
  'brand_logo'          => $settings->getBrandLogo(),
  'brand_logo_height'   => $settings->getBrandLogoHeight(),
  'navbar_hide'         => Session::Get()->getNavbarHide(),
  'pagename'            => $settings->getPageName(),
  'page_active'         => $_GET['page'] ?: 'index',
  'authenticated'       => Session::Get()->isAuthenticated() ?: false,
  'username'            => Session::Get()->getUsername() ?: '',
  'is_superadmin'       => Session::Get()->checkAccessAll(),
  'feature_stats'       => $settings->getDisplayStats()
];
