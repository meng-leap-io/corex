<?php

// Override env before Laravel loads
putenv('JWT_SECRET=test-jwt-secret-key');
putenv('JWT_ALGORITHM=HS256');
$_ENV['JWT_SECRET'] = 'test-jwt-secret-key';
$_SERVER['JWT_SECRET'] = 'test-jwt-secret-key';
