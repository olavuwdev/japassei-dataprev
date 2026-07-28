<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Autenticação
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::login');
$routes->get('registrar', 'AuthController::registerForm');
$routes->post('registrar', 'AuthController::register');
$routes->get('logout', 'AuthController::logout');

// Módulo de estudos (protegido)
$routes->group('estudos', ['namespace' => 'App\Controllers\Estudos', 'filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('/', 'DashboardController::index');
    $routes->get('hoje', 'HojeController::index');
    $routes->get('cronograma', 'CronogramaController::index');
    $routes->get('kanban', 'KanbanController::index');
    $routes->get('revisoes', 'RevisoesController::index');
    $routes->get('questoes', 'QuestoesController::index');
    $routes->get('provas', 'ProvasController::index');
    $routes->get('desempenho', 'DesempenhoController::index');
    $routes->get('historico', 'HistoricoController::index');
    $routes->get('configuracoes', 'ConfiguracoesController::index');
    $routes->post('configuracoes', 'ConfiguracoesController::save');

    // API interna (JSON)
    $routes->group('api', static function (RouteCollection $routes): void {
        // Timer / sessões de estudo
        $routes->get('sessao/ativa', 'SessaoController::active');
        $routes->post('sessao/iniciar', 'SessaoController::start');
        $routes->post('sessao/(:num)/pausar', 'SessaoController::pause/$1');
        $routes->post('sessao/(:num)/retomar', 'SessaoController::resume/$1');
        $routes->post('sessao/(:num)/concluir', 'SessaoController::finish/$1');
        $routes->post('sessao/(:num)/cancelar', 'SessaoController::cancel/$1');

        // Tarefas
        $routes->post('tarefas/(:num)/concluir', 'TarefasController::complete/$1');
        $routes->post('tarefas/(:num)/reagendar', 'TarefasController::reschedule/$1');
        $routes->get('tarefas/(:num)', 'TarefasController::show/$1');
        $routes->post('tarefas/(:num)/observacao', 'TarefasController::addNote/$1');

        // Checklist
        $routes->post('checklist/(:num)/alternar', 'ChecklistController::toggle/$1');
        $routes->post('checklist', 'ChecklistController::create');
        $routes->post('checklist/(:num)/editar', 'ChecklistController::update/$1');
        $routes->post('checklist/(:num)/excluir', 'ChecklistController::delete/$1');
        $routes->post('checklist/reordenar', 'ChecklistController::reorder');

        // Kanban
        $routes->get('kanban/board', 'KanbanController::board');
        $routes->post('kanban/mover', 'KanbanController::move');

        // Revisões
        $routes->post('revisoes/(:num)/concluir', 'RevisoesController::complete/$1');
        $routes->post('revisoes/(:num)/reagendar', 'RevisoesController::reschedule/$1');
        $routes->post('revisoes/(:num)/ignorar', 'RevisoesController::skip/$1');

        // Registro de questões
        $routes->post('questoes', 'QuestoesController::store');
        $routes->post('questoes/(:num)/editar', 'QuestoesController::update/$1');
        $routes->post('questoes/(:num)/excluir', 'QuestoesController::delete/$1');

        // Provas antigas / materiais
        $routes->post('provas', 'ProvasController::store');
        $routes->post('provas/(:num)/editar', 'ProvasController::update/$1');
        $routes->post('provas/(:num)/desativar', 'ProvasController::deactivate/$1');
        $routes->post('provas/(:num)/excluir', 'ProvasController::delete/$1');
        $routes->post('provas/(:num)/tentativa', 'ProvasController::registerAttempt/$1');

        // Dados para gráficos
        $routes->get('desempenho/dados', 'DesempenhoController::data');
    });
});
