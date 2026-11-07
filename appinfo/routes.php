<?php
return [
    'routes' => [
        ['name' => 'settings#getFollowUp', 'url' => '/api/v1/settings/follow-up', 'verb' => 'GET'],
        ['name' => 'settings#saveFollowUp', 'url' => '/api/v1/settings/follow-up', 'verb' => 'POST'],
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        ['name' => 'product_options#index', 'url' => '/api/v1/product-options', 'verb' => 'GET'],
        ['name' => 'product_options#save', 'url' => '/api/v1/product-options', 'verb' => 'POST'],

        ['name' => 'product#index', 'url' => '/api/v1/products', 'verb' => 'GET'],
        ['name' => 'product#show', 'url' => '/api/v1/products/{productUuid}', 'verb' => 'GET'],
        ['name' => 'product#create', 'url' => '/api/v1/products', 'verb' => 'POST'],
        ['name' => 'product#update', 'url' => '/api/v1/products/{productUuid}', 'verb' => 'PUT'],
        ['name' => 'product#delete', 'url' => '/api/v1/products/{productUuid}', 'verb' => 'DELETE'],

        ['name' => 'client#index',  'url' => '/api/v1/clients', 'verb' => 'GET'],
        ['name' => 'client#show',   'url' => '/api/v1/clients/{uuid}', 'verb' => 'GET'],
        ['name' => 'client#create', 'url' => '/api/v1/clients', 'verb' => 'POST'],
        ['name' => 'client#update', 'url' => '/api/v1/clients/{uuid}', 'verb' => 'PUT'],
        ['name' => 'client#delete', 'url' => '/api/v1/clients/{uuid}', 'verb' => 'DELETE'],

        ['name' => 'client_product#index', 'url' => '/api/v1/clients/{clientUuid}/products', 'verb' => 'GET'],
        ['name' => 'client_product#create', 'url' => '/api/v1/clients/{clientUuid}/products', 'verb' => 'POST'],
        ['name' => 'client_product#update', 'url' => '/api/v1/clients/{clientUuid}/products/{positionUuid}', 'verb' => 'PUT'],
        ['name' => 'client_product#delete', 'url' => '/api/v1/clients/{clientUuid}/products/{positionUuid}', 'verb' => 'DELETE'],

        ['name' => 'client#getContacts', 'url' => '/api/v1/clients/{uuid}/contacts', 'verb' => 'GET'],
        ['name' => 'client#linkContact', 'url' => '/api/v1/clients/{uuid}/contacts', 'verb' => 'POST'],
        ['name' => 'client#unlinkContact', 'url' => '/api/v1/clients/{uuid}/contacts/{cardId}', 'verb' => 'DELETE', 'requirements' => ['cardId' => '\d+']],

        ['name' => 'address#index', 'url' => '/api/v1/clients/{clientUuid}/addresses', 'verb' => 'GET'],
        ['name' => 'contact#search', 'url' => '/api/v1/contacts/search', 'verb' => 'GET'],

        ['name' => 'activity#getClientActivities', 'url' => '/api/v1/clients/{uuid}/activities', 'verb' => 'GET'],
        ['name' => 'activity#getProductActivities', 'url' => '/api/v1/products/{productUuid}/activities', 'verb' => 'GET'],

        ['name' => 'note#index',   'url' => '/api/v1/clients/{clientId}/notes', 'verb' => 'GET'],
        ['name' => 'note#create',  'url' => '/api/v1/clients/{clientId}/notes', 'verb' => 'POST'],
        ['name' => 'note#update',  'url' => '/api/v1/notes/{id}',               'verb' => 'PUT'],
        ['name' => 'note#destroy', 'url' => '/api/v1/notes/{id}',               'verb' => 'DELETE'],

        ['name' => 'settings#getSettings',  'url' => '/api/v1/settings', 'verb' => 'GET'],
        ['name' => 'settings#createAddressBook',  'url' => '/api/v1/settings/addressbook', 'verb' => 'POST'],
        ['name' => 'settings#saveSettings', 'url' => '/api/v1/settings', 'verb' => 'POST'],
    ]
];
