<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'asms';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'Agency Submission Management System';
const BASE_URL = '/';
const UPLOAD_DIR = __DIR__ . '/../uploads';

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true);
}
