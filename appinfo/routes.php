<?php
return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'client#index',  'url' => '/api/v1/clients', 'verb' => 'GET'],
        ['name' => 'client#show',   'url' => '/api/v1/clients/{uuid}', 'verb' => 'GET'],
        ['name' => 'client#create', 'url' => '/api/v1/clients', 'verb' => 'POST'],
        ['name' => 'client#update', 'url' => '/api/v1/clients/{uuid}', 'verb' => 'PUT'],
        ['name' => 'client#delete', 'url' => '/api/v1/clients/{uuid}', 'verb' => 'DELETE'],

        ['name' => 'addressi#index', 'url' => '/api/v1/clients/{clientUuid}/addresses', 'verb' => 'GET'],

        ['name' => 'activity#getClientActivities', 'url' => '/api/v1/clients/{uuid}/activities', 'verb' => 'GET'],

        ['name' => 'note#index',   'url' => '/api/v1/clients/{clientId}/notes', 'verb' => 'GET'],
        ['name' => 'note#create',  'url' => '/api/v1/clients/{clientId}/notes', 'verb' => 'POST'],
        ['name' => 'note#update',  'url' => '/api/v1/notes/{id}',               'verb' => 'PUT'],
        ['name' => 'note#destroy', 'url' => '/api/v1/notes/{id}',               'verb' => 'DELETE'],

        ['name' => 'settings#getSettings',  'url' => '/api/v1/settings', 'verb' => 'GET'],
        ['name' => 'settings#saveSettings', 'url' => '/api/v1/settings', 'verb' => 'POST'],
    ]
];