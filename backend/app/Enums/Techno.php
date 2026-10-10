<?php

declare(strict_types=1);

namespace App\Enums;

enum Techno: string
{
    case Laravel = 'laravel';
    case Symfony = 'symfony';
    case Php = 'php';
    case Node = 'node';
    case Express = 'express';
    case Nestjs = 'nestjs';
    case Nextjs = 'nextjs';
    case React = 'react';
    case Vue = 'vue';
    case Angular = 'angular';
    case Svelte = 'svelte';
    case Django = 'django';
    case Flask = 'flask';
    case Fastapi = 'fastapi';
    case Spring = 'spring';
    case Dotnet = 'dotnet';
    case Go = 'go';
    case Postgresql = 'postgresql';
    case Mysql = 'mysql';
    case Mongodb = 'mongodb';
    case Redis = 'redis';
    case Autre = 'autre';
}
