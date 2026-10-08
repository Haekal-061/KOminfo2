<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');
$routes->get('login', 'AuthController::login');
$routes->post('login', 'AuthController::authenticate');
$routes->post('logout', 'AuthController::logout', ['filter' => 'auth']);
$routes->post('api/webhooks/openwa', 'OpenWAWebhookController::receive');

$routes->group('admin', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'DashboardController::index', ['filter' => 'permission:dashboard.view']);
    $routes->get('tickets', 'TicketController::index', ['filter' => 'permission:ticket.view']);
    $routes->get('tickets/datatables', 'TicketController::datatable', ['filter' => 'permission:ticket.view']);
    $routes->get('tickets/create', 'TicketController::create', ['filter' => 'permission:ticket.create']);
    $routes->post('tickets', 'TicketController::store', ['filter' => 'permission:ticket.create']);
    $routes->get('tickets/(:num)', 'TicketController::show/$1', ['filter' => 'permission:ticket.view']);
    $routes->post('tickets/(:num)', 'TicketController::update/$1', ['filter' => 'permission:ticket.update']);
    $routes->post('tickets/(:num)/status', 'TicketController::status/$1', ['filter' => 'permission:ticket.change_status']);
    $routes->post('tickets/(:num)/assignment', 'TicketController::assign/$1', ['filter' => 'permission:ticket.assign']);
    $routes->post('tickets/(:num)/messages', 'TicketController::comment/$1', ['filter' => 'permission:ticket.message.create']);
    $routes->get('reports', 'ReportController::index', ['filter' => 'permission:report.view']);
    $routes->get('reports/tickets.csv', 'ReportController::export', ['filter' => 'permission:report.export']);
    $routes->get('master/(:segment)/datatable', 'MasterDataController::datatable/$1');
    $routes->get('master/(:segment)', 'MasterDataController::index/$1');
    $routes->post('master/(:segment)', 'MasterDataController::save/$1');
});
